<?php

declare(strict_types=1);

use Samtli\Auth\RegistrationService;
use Samtli\Database\Connection;
use Samtli\Http\RegisterController;
use Samtli\Http\Response;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\View\Html;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$repository = new UserRepository($pdo);
$service = new RegistrationService($repository);

$testEmails = [
    'registration-test@example.test',
    'invalid-email-test@example.test',
    'missing-name-test@example.test',
    'short-password-test@example.test',
    'mismatch-password-test@example.test',
    'xss-registration@example.test',
];

cleanupUsers($pdo, $testEmails);

$result = $service->register([
    'first_name' => ' Ada ',
    'last_name' => ' Lovelace ',
    'email' => ' REGISTRATION-TEST@EXAMPLE.TEST ',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
]);

assertTrue($result->isSuccess(), 'valid registration succeeds');

$user = fetchUser($pdo, 'registration-test@example.test');
assertTrue($user !== null, 'valid registration creates a user row');
assertSame('Ada', $user['first_name'], 'first name is trimmed before persistence');
assertSame('Lovelace', $user['last_name'], 'last name is trimmed before persistence');
assertSame('registration-test@example.test', $user['email'], 'email is normalized before persistence');
assertTrue($user['password_hash'] !== 'correct horse battery staple', 'plaintext password is not stored');
assertTrue(password_verify('correct horse battery staple', $user['password_hash']), 'stored password hash verifies with password_verify');
assertTrue($user['created_at'] !== null && $user['updated_at'] !== null, 'registration populates timestamps');

$duplicate = $service->register([
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'registration-test@example.test',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
]);
assertTrue(!$duplicate->isSuccess(), 'duplicate email registration is rejected safely');
assertSame('An account with this email already exists.', $duplicate->fieldErrors()['email'][0] ?? null, 'duplicate email returns controlled field error');
assertSame(1, countUsers($pdo, 'registration-test@example.test'), 'duplicate email does not create a second account');

assertRegistrationRejected($service, $pdo, [
    'first_name' => 'Invalid',
    'last_name' => 'Email',
    'email' => 'not-an-email',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
], 'invalid email is rejected');

assertRegistrationRejected($service, $pdo, [
    'first_name' => '',
    'last_name' => 'Name',
    'email' => 'missing-name-test@example.test',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
], 'missing first name is rejected');

assertRegistrationRejected($service, $pdo, [
    'first_name' => 'Short',
    'last_name' => 'Password',
    'email' => 'short-password-test@example.test',
    'password' => 'short',
    'password_confirmation' => 'short',
], 'short password is rejected');

assertRegistrationRejected($service, $pdo, [
    'first_name' => 'Mismatch',
    'last_name' => 'Password',
    'email' => 'mismatch-password-test@example.test',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'different horse battery staple',
], 'password confirmation mismatch is rejected');

$session = [];
$csrf = new CsrfTokenManager($session);
$token = $csrf->token('register');
assertTrue($token !== '', 'CSRF token is generated');
assertTrue($csrf->isValid('register', $token), 'valid CSRF token is accepted');
assertTrue(!$csrf->isValid('register', 'invalid-token'), 'invalid CSRF token is rejected');
assertTrue(!$csrf->isValid('register', ''), 'missing CSRF token is rejected');

$requestSession = [];
$requestCsrf = new CsrfTokenManager($requestSession);
$controller = new RegisterController(
    $service,
    $requestCsrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);
$beforeInvalidCsrf = totalUsers($pdo);
$invalidCsrfResponse = $controller->store([
    '_csrf' => 'invalid-token',
    'first_name' => 'Invalid',
    'last_name' => 'Csrf',
    'email' => 'invalid-csrf@example.test',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
]);
assertTrue($invalidCsrfResponse instanceof Response, 'invalid browser CSRF submission returns a response');
assertSame(419, $invalidCsrfResponse->statusCode(), 'invalid browser CSRF submission is rejected with status 419');
assertSame($beforeInvalidCsrf, totalUsers($pdo), 'invalid browser CSRF submission does not create a user');

$requestCsrfToken = $requestCsrf->token('register');
$xssResponse = $controller->store([
    '_csrf' => $requestCsrfToken,
    'first_name' => '<script>alert("xss")</script>',
    'last_name' => '',
    'email' => 'xss-registration@example.test',
    'password' => 'correct horse battery staple',
    'password_confirmation' => 'correct horse battery staple',
]);
assertTrue($xssResponse instanceof Response, 'invalid form submission re-renders registration page');
assertTrue(!str_contains($xssResponse->body(), '<script>alert("xss")</script>'), 'script-like submitted value is not rendered as raw HTML');
assertTrue(str_contains($xssResponse->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'script-like submitted value is escaped in the registration page');

$escaped = Html::escape('<script>alert("xss")</script>');
assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $escaped, 'HTML helper escapes script-like form values');

cleanupUsers($pdo, $testEmails);

echo "Registration verification passed.\n";

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

/**
 * @return array<string, string>|null
 */
function fetchUser(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT first_name, last_name, email, password_hash, created_at, updated_at FROM users WHERE email = ?');
    $statement->execute([$email]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function countUsers(PDO $pdo, string $email): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $statement->execute([$email]);

    return (int) $statement->fetchColumn();
}

/**
 * @param array<string, string> $input
 */
function assertRegistrationRejected(RegistrationService $service, PDO $pdo, array $input, string $message): void
{
    $before = totalUsers($pdo);
    $result = $service->register($input);
    $after = totalUsers($pdo);

    assertTrue(!$result->isSuccess(), $message);
    assertSame($before, $after, "{$message} without creating a user");
}

function totalUsers(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
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
