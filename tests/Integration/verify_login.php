<?php

declare(strict_types=1);

use Samtli\Auth\AuthenticationService;
use Samtli\Database\Connection;
use Samtli\Http\LoginController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$repository = new UserRepository($pdo);

cleanupUsers($pdo, ['login-test@example.test', 'xss-login@example.test']);
$userId = createUser($pdo, 'Login', 'Tester', 'login-test@example.test', 'correct horse battery staple');

$service = new AuthenticationService($repository);

$valid = $service->authenticate([
    'email' => ' LOGIN-TEST@EXAMPLE.TEST ',
    'password' => 'correct horse battery staple',
]);
assertTrue($valid->isSuccess(), 'valid credentials authenticate');
assertSame($userId, $valid->userId(), 'valid credentials return authenticated user id');

$invalidPassword = $service->authenticate([
    'email' => 'login-test@example.test',
    'password' => 'wrong password',
]);
assertTrue(!$invalidPassword->isSuccess(), 'invalid password is rejected');
assertSame('The email or password you entered is incorrect. Please try again.', $invalidPassword->formError(), 'invalid password returns generic login error');

$unknownEmail = $service->authenticate([
    'email' => 'unknown-login@example.test',
    'password' => 'correct horse battery staple',
]);
assertTrue(!$unknownEmail->isSuccess(), 'unknown email is rejected');
assertSame('The email or password you entered is incorrect. Please try again.', $unknownEmail->formError(), 'unknown email returns the same generic login error');

$invalidEmail = $service->authenticate([
    'email' => 'not-an-email',
    'password' => 'correct horse battery staple',
]);
assertTrue(!$invalidEmail->isSuccess(), 'invalid email input is rejected');

$session = [];
$authenticator = new SessionAuthenticator($session);
assertTrue(!$authenticator->check(), 'empty session is unauthenticated');
$authenticator->login($userId);
assertTrue($authenticator->check(), 'login marks session as authenticated');
assertSame($userId, $authenticator->id(), 'authenticated session exposes user id');

$requestSession = [];
$csrf = new CsrfTokenManager($requestSession);
$controller = new LoginController(
    $service,
    new SessionAuthenticator($requestSession),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$invalidCsrfResponse = $controller->store([
    '_csrf' => 'invalid-token',
    'email' => 'login-test@example.test',
    'password' => 'correct horse battery staple',
]);
assertTrue($invalidCsrfResponse instanceof Response, 'invalid login CSRF submission returns a response');
assertSame(419, $invalidCsrfResponse->statusCode(), 'invalid login CSRF submission is rejected with status 419');
assertTrue(!isset($requestSession['auth_user_id']), 'invalid login CSRF submission does not authenticate');

$csrfToken = $csrf->token('login');
$failedLoginResponse = $controller->store([
    '_csrf' => $csrfToken,
    'email' => 'login-test@example.test',
    'password' => 'wrong password',
]);
assertTrue($failedLoginResponse instanceof Response, 'failed login re-renders login page');
assertSame(422, $failedLoginResponse->statusCode(), 'failed login uses validation status 422');
assertTrue(str_contains($failedLoginResponse->body(), 'The email or password you entered is incorrect. Please try again.'), 'failed login displays generic error');
assertTrue(!str_contains($failedLoginResponse->body(), 'wrong password'), 'failed login does not re-render submitted password');
assertTrue(!isset($requestSession['auth_user_id']), 'failed login does not authenticate');

$csrfToken = $csrf->token('login');
$xssResponse = $controller->store([
    '_csrf' => $csrfToken,
    'email' => '<script>alert("xss")</script>',
    'password' => 'correct horse battery staple',
]);
assertTrue($xssResponse instanceof Response, 'invalid login input re-renders login page');
assertTrue(!str_contains($xssResponse->body(), '<script>alert("xss")</script>'), 'script-like login email is not rendered as raw HTML');
assertTrue(str_contains($xssResponse->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'script-like login email is escaped');

$csrfToken = $csrf->token('login');
$successfulLoginResponse = $controller->store([
    '_csrf' => $csrfToken,
    'email' => 'login-test@example.test',
    'password' => 'correct horse battery staple',
]);
assertTrue($successfulLoginResponse instanceof RedirectResponse, 'successful login redirects');
assertSame('/', $successfulLoginResponse->location(), 'successful login redirects to home');
assertSame($userId, $requestSession['auth_user_id'] ?? null, 'successful login stores authenticated user id in session');
assertTrue(!isset($requestSession['auth_password']), 'successful login does not store plaintext password');

$homeHtml = (new TemplateRenderer(dirname(__DIR__, 2) . '/templates'))->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => $userId,
]);
assertTrue(str_contains($homeHtml, 'You are logged in.'), 'home page can render a minimal authenticated state');

cleanupUsers($pdo, ['login-test@example.test', 'xss-login@example.test']);

echo "Login verification passed.\n";

/**
 * @param list<string> $emails
 */
function cleanupUsers(PDO $pdo, array $emails): void
{
    $statement = $pdo->prepare(
        sprintf('DELETE FROM users WHERE email IN (%s)', implode(', ', array_fill(0, count($emails), '?')))
    );
    $statement->execute($emails);
}

function createUser(PDO $pdo, string $firstName, string $lastName, string $email, string $password): int
{
    $statement = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}
