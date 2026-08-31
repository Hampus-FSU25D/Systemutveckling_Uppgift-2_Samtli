<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupAdminController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Invitations\InvitationCreationService;
use Samtli\Memberships\MemberRoleService;
use Samtli\Memberships\MembershipApprovalService;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\InvitationRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$groups = new GroupRepository($pdo);
$memberships = new MembershipRepository($pdo);
$roles = new MemberRoleService($memberships);

cleanup($pdo);

$adminId = createUser($pdo, 'role-admin@example.test', 'Admin');
$memberId = createUser($pdo, 'role-member@example.test', '<script>alert("xss")</script>');
$secondMemberId = createUser($pdo, 'role-second@example.test', 'Second');
$outsiderId = createUser($pdo, 'role-outsider@example.test', 'Outsider');

$groupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Role Test Group',
    'description' => 'Used for role management verification.',
], $adminId)->groupId();
addMember($pdo, $groupId, $memberId, 'member');
addMember($pdo, $groupId, $secondMemberId, 'member');

$nonAdminChange = $roles->changeRole($groupId, $secondMemberId, $memberId, 'administrator');
assertTrue(!$nonAdminChange->isSuccess(), 'non-admin role change is rejected');
assertSame('Only group administrators can change member roles.', $nonAdminChange->message(), 'non-admin role change returns clear message');
assertSame('member', membershipRole($pdo, $groupId, $secondMemberId), 'non-admin role change does not alter role');

$selfChange = $roles->changeRole($groupId, $adminId, $adminId, 'member');
assertTrue(!$selfChange->isSuccess(), 'administrator cannot change own role');
assertSame('Administrators cannot change their own role.', $selfChange->message(), 'self role change returns clear message');
assertSame('administrator', membershipRole($pdo, $groupId, $adminId), 'self role change does not alter role');

$invalidRole = $roles->changeRole($groupId, $memberId, $adminId, 'owner');
assertTrue(!$invalidRole->isSuccess(), 'invalid role is rejected');
assertSame('Choose a valid role.', $invalidRole->message(), 'invalid role returns clear message');
assertSame('member', membershipRole($pdo, $groupId, $memberId), 'invalid role does not alter role');

$outsiderChange = $roles->changeRole($groupId, $outsiderId, $adminId, 'administrator');
assertTrue(!$outsiderChange->isSuccess(), 'cannot change role for non-member');
assertSame('Member not found.', $outsiderChange->message(), 'non-member target returns clear message');

$promote = $roles->changeRole($groupId, $memberId, $adminId, 'administrator');
assertTrue($promote->isSuccess(), 'administrator can promote member');
assertSame('Member role updated.', $promote->message(), 'role update returns success message');
assertSame('administrator', membershipRole($pdo, $groupId, $memberId), 'promoted member becomes administrator');

$demote = $roles->changeRole($groupId, $memberId, $adminId, 'member');
assertTrue($demote->isSuccess(), 'administrator can demote another administrator');
assertSame('member', membershipRole($pdo, $groupId, $memberId), 'demoted administrator becomes member');

$session = [];
$csrf = new CsrfTokenManager($session);
$controller = new GroupAdminController(
    new MembershipApprovalService($memberships),
    $roles,
    new InvitationCreationService($groups, $memberships, new InvitationRepository($pdo)),
    $memberships,
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestList = $controller->members($groupId);
assertTrue($guestList instanceof RedirectResponse, 'guest member list redirects');
assertSame('/login', $guestList->location(), 'guest member list redirects to login');

$guestChange = $controller->updateMemberRole($groupId, $secondMemberId, ['_csrf' => 'invalid-token', 'role' => 'administrator']);
assertTrue($guestChange instanceof RedirectResponse, 'guest role update redirects');
assertSame('/login', $guestChange->location(), 'guest role update redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($secondMemberId);

$forbiddenList = $controller->members($groupId);
assertTrue($forbiddenList instanceof Response, 'non-admin member list returns response');
assertSame(403, $forbiddenList->statusCode(), 'non-admin member list is forbidden');

$forbiddenChange = $controller->updateMemberRole($groupId, $memberId, [
    '_csrf' => $csrf->token('admin.member_roles'),
    'role' => 'administrator',
]);
assertTrue($forbiddenChange instanceof Response, 'non-admin role update returns response');
assertSame(403, $forbiddenChange->statusCode(), 'non-admin role update is forbidden');

$authenticator->login($adminId);

$invalidCsrf = $controller->updateMemberRole($groupId, $secondMemberId, [
    '_csrf' => 'invalid-token',
    'role' => 'administrator',
]);
assertTrue($invalidCsrf instanceof Response, 'invalid role CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid role CSRF is rejected with status 419');
assertSame('member', membershipRole($pdo, $groupId, $secondMemberId), 'invalid role CSRF does not alter role');

$listPage = $controller->members($groupId, 'Saved.');
assertTrue($listPage instanceof Response, 'admin member list renders');
assertTrue(str_contains($listPage->body(), 'Members'), 'admin member list has heading');
assertTrue(str_contains($listPage->body(), 'group-admin-nav'), 'admin member list renders shared admin navigation');
assertTrue(str_contains($listPage->body(), '/admin/members" aria-current="page"'), 'admin member list marks members tab active');
assertTrue(str_contains($listPage->body(), 'role-second@example.test'), 'admin member list includes member email');
assertTrue(!str_contains($listPage->body(), '<script>alert("xss")</script>'), 'admin member list does not render raw member HTML');
assertTrue(str_contains($listPage->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'admin member list escapes member names');

$validChange = $controller->updateMemberRole($groupId, $secondMemberId, [
    '_csrf' => $csrf->token('admin.member_roles'),
    'role' => 'administrator',
]);
assertTrue($validChange instanceof RedirectResponse, 'valid role update redirects');
assertSame("/groups/{$groupId}/admin/members", $validChange->location(), 'valid role update redirects to member list');
assertSame('administrator', membershipRole($pdo, $groupId, $secondMemberId), 'valid role update persists administrator role');

cleanup($pdo);

echo "Member role verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name = 'Role Test Group'");
    $pdo->exec("DELETE FROM users WHERE email IN ('role-admin@example.test', 'role-member@example.test', 'role-second@example.test', 'role-outsider@example.test')");
}

function createUser(PDO $pdo, string $email, string $firstName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, 'Tester', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function addMember(PDO $pdo, int $groupId, int $userId, string $role): void
{
    $statement = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)');
    $statement->execute([$groupId, $userId, $role]);
}

function membershipRole(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT role FROM group_memberships WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $role = $statement->fetchColumn();

    return $role === false ? null : (string) $role;
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
