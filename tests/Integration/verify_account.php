<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Http\AccountController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$users = new UserRepository($pdo);

cleanupUsers($pdo, ['account-owner@example.test', 'account-other@example.test', 'account-updated@example.test']);

$ownerId = createUser($pdo, 'Account', 'Owner', 'account-owner@example.test');
createUser($pdo, 'Other', 'Owner', 'account-other@example.test');

$guestSession = [];
$guestController = new AccountController(
    $users,
    new SessionAuthenticator($guestSession),
    new CsrfTokenManager($guestSession),
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestAccount = $guestController->show();
assertTrue($guestAccount instanceof RedirectResponse, 'guest account page redirects');
assertSame('/login', $guestAccount->location(), 'guest account redirects to login');

$session = [];
$authenticator = new SessionAuthenticator($session);
$authenticator->login($ownerId);
$csrf = new CsrfTokenManager($session);
$controller = new AccountController(
    $users,
    $authenticator,
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$accountPage = $controller->show();
assertTrue($accountPage instanceof Response, 'authenticated account page renders');
assertTrue(str_contains($accountPage->body(), 'Account settings'), 'account page renders heading');
assertTrue(str_contains($accountPage->body(), 'Member since'), 'account page renders member since');
assertTrue(str_contains($accountPage->body(), 'Log out'), 'account page exposes logout');
assertTrue(str_contains($accountPage->body(), 'Save changes'), 'account page exposes save action');

$invalidCsrf = $controller->update([
    '_csrf' => 'invalid-token',
    'first_name' => 'Changed',
    'last_name' => 'Owner',
    'email' => 'account-updated@example.test',
]);
assertTrue($invalidCsrf instanceof Response, 'invalid account CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid account CSRF uses status 419');
assertSame('account-owner@example.test', fetchEmail($pdo, $ownerId), 'invalid account CSRF does not update email');

$duplicateEmail = $controller->update([
    '_csrf' => $csrf->token('account.update'),
    'first_name' => 'Changed',
    'last_name' => 'Owner',
    'email' => 'account-other@example.test',
]);
assertTrue($duplicateEmail instanceof Response, 'duplicate account email re-renders form');
assertSame(422, $duplicateEmail->statusCode(), 'duplicate account email uses validation status');
assertTrue(str_contains($duplicateEmail->body(), 'An account with this email already exists.'), 'duplicate account email shows field error');

$updated = $controller->update([
    '_csrf' => $csrf->token('account.update'),
    'first_name' => 'Updated',
    'last_name' => 'Member',
    'email' => 'ACCOUNT-UPDATED@EXAMPLE.TEST',
]);
assertTrue($updated instanceof RedirectResponse, 'valid account update redirects');
assertSame('/account', $updated->location(), 'valid account update redirects to account page');
$profile = $users->findPublicById($ownerId);
assertSame('Updated', $profile['first_name'] ?? null, 'account update stores first name');
assertSame('Member', $profile['last_name'] ?? null, 'account update stores last name');
assertSame('account-updated@example.test', $profile['email'] ?? null, 'account update normalizes email');
assertSame('Updated', $session['auth_user']['first_name'] ?? null, 'account update refreshes session identity');

$invalidLogout = $controller->logout(['_csrf' => 'invalid-token']);
assertTrue($invalidLogout instanceof Response, 'invalid logout CSRF returns response');
assertSame(419, $invalidLogout->statusCode(), 'invalid logout CSRF uses status 419');
assertSame($ownerId, $authenticator->id(), 'invalid logout CSRF keeps authentication');

$logout = $controller->logout(['_csrf' => $csrf->token('logout')]);
assertTrue($logout instanceof RedirectResponse, 'valid logout redirects');
assertSame('/', $logout->location(), 'valid logout redirects home');
assertSame(null, $authenticator->id(), 'valid logout clears authenticated id');
assertTrue(!isset($session['auth_user']), 'valid logout clears session identity');

cleanupUsers($pdo, ['account-owner@example.test', 'account-other@example.test', 'account-updated@example.test']);

echo "Account verification passed.\n";

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

function createUser(PDO $pdo, string $firstName, string $lastName, string $email): int
{
    $statement = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$firstName, $lastName, $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT), '2026-08-31 09:00:00', '2026-08-31 09:00:00']);

    return (int) $pdo->lastInsertId();
}

function fetchEmail(PDO $pdo, int $userId): string
{
    $statement = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $statement->execute([$userId]);

    return (string) $statement->fetchColumn();
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
