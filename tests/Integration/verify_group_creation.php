<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$users = new UserRepository($pdo);
$groups = new GroupRepository($pdo);
$service = new GroupCreationService($groups);

cleanup($pdo);
$userId = createUser($pdo, 'group-creator@example.test');

$result = $service->create([
    'name' => ' Nordic Architecture ',
    'description' => ' A focused group about human-scale buildings. ',
], $userId);

assertTrue($result->isSuccess(), 'valid group creation succeeds');
assertTrue($result->groupId() !== null && $result->groupId() > 0, 'valid group creation returns group id');

$group = fetchGroup($pdo, (int) $result->groupId());
assertSame('Nordic Architecture', $group['name'] ?? null, 'group name is trimmed before persistence');
assertSame('A focused group about human-scale buildings.', $group['description'] ?? null, 'group description is trimmed before persistence');
assertSame((string) $userId, (string) ($group['created_by'] ?? null), 'group stores creating user');
assertTrue(($group['created_at'] ?? null) !== null && ($group['updated_at'] ?? null) !== null, 'group timestamps are populated');
assertSame('administrator', fetchMembershipRole($pdo, (int) $result->groupId(), $userId), 'creator receives administrator membership');

assertGroupCreationRejected($service, $pdo, [
    'name' => '',
    'description' => 'Description',
], $userId, 'missing group name is rejected');

assertGroupCreationRejected($service, $pdo, [
    'name' => str_repeat('a', 121),
    'description' => 'Description',
], $userId, 'too-long group name is rejected');

assertGroupCreationRejected($service, $pdo, [
    'name' => 'Empty Description',
    'description' => '',
], $userId, 'missing group description is rejected');

$session = [];
$csrf = new CsrfTokenManager($session);
$controller = new GroupController(
    $service,
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestCreatePage = $controller->create();
assertTrue($guestCreatePage instanceof RedirectResponse, 'guest create page redirects');
assertSame('/login', $guestCreatePage->location(), 'guest create page redirects to login');

$guestStore = $controller->store([
    '_csrf' => 'invalid-token',
    'name' => 'Guest Group',
    'description' => 'Should not persist',
]);
assertTrue($guestStore instanceof RedirectResponse, 'guest group submission redirects');
assertSame('/login', $guestStore->location(), 'guest group submission redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($userId);

$invalidCsrf = $controller->store([
    '_csrf' => 'invalid-token',
    'name' => 'Invalid CSRF',
    'description' => 'Should not persist',
]);
assertTrue($invalidCsrf instanceof Response, 'invalid CSRF group submission returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid CSRF group submission is rejected with status 419');
assertSame(0, countGroupsByName($pdo, 'Invalid CSRF'), 'invalid CSRF group submission does not create group');

$csrfToken = $csrf->token('groups.create');
$invalidForm = $controller->store([
    '_csrf' => $csrfToken,
    'name' => '<script>alert("xss")</script>',
    'description' => '',
]);
assertTrue($invalidForm instanceof Response, 'invalid group form re-renders page');
assertSame(422, $invalidForm->statusCode(), 'invalid group form uses status 422');
assertTrue(!str_contains($invalidForm->body(), '<script>alert("xss")</script>'), 'script-like group name is not rendered as raw HTML');
assertTrue(str_contains($invalidForm->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'script-like group name is escaped');

$csrfToken = $csrf->token('groups.create');
$validResponse = $controller->store([
    '_csrf' => $csrfToken,
    'name' => 'Controller Created Group',
    'description' => 'Created through the browser controller path.',
]);
assertTrue($validResponse instanceof RedirectResponse, 'successful group form redirects');
assertTrue(str_starts_with($validResponse->location(), '/groups/'), 'successful group form redirects to group page');
assertSame('administrator', fetchMembershipRole($pdo, latestGroupId($pdo, 'Controller Created Group'), $userId), 'controller-created group grants administrator membership');

cleanup($pdo);

echo "Group creation verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name IN ('Nordic Architecture', 'Controller Created Group', 'Invalid CSRF', 'Guest Group', 'Empty Description')");
    $pdo->exec("DELETE FROM users WHERE email = 'group-creator@example.test'");
}

function createUser(PDO $pdo, string $email): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute(['Group', 'Creator', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return array<string, string>|null
 */
function fetchGroup(PDO $pdo, int $groupId): ?array
{
    $statement = $pdo->prepare('SELECT name, description, created_by, created_at, updated_at FROM groups WHERE id = ?');
    $statement->execute([$groupId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function fetchMembershipRole(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT role FROM group_memberships WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $role = $statement->fetchColumn();

    return $role === false ? null : (string) $role;
}

/**
 * @param array<string, string> $input
 */
function assertGroupCreationRejected(GroupCreationService $service, PDO $pdo, array $input, int $userId, string $message): void
{
    $before = totalGroups($pdo);
    $result = $service->create($input, $userId);
    $after = totalGroups($pdo);

    assertTrue(!$result->isSuccess(), $message);
    assertSame($before, $after, "{$message} without creating a group");
}

function totalGroups(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM groups')->fetchColumn();
}

function countGroupsByName(PDO $pdo, string $name): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM groups WHERE name = ?');
    $statement->execute([$name]);

    return (int) $statement->fetchColumn();
}

function latestGroupId(PDO $pdo, string $name): int
{
    $statement = $pdo->prepare('SELECT id FROM groups WHERE name = ? ORDER BY id DESC LIMIT 1');
    $statement->execute([$name]);

    return (int) $statement->fetchColumn();
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
