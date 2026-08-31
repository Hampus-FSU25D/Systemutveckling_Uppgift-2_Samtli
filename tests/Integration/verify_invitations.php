<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupAdminController;
use Samtli\Http\InvitationController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Invitations\InvitationAcceptanceService;
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
$invitations = new InvitationRepository($pdo);

cleanup($pdo);

$adminId = createUser($pdo, 'invite-admin@example.test', 'Invite', 'Admin');
$memberId = createUser($pdo, 'invite-member@example.test', 'Invite', 'Member');
$outsiderId = createUser($pdo, 'invite-outsider@example.test', '<script>alert("xss")</script>', 'Outsider');
$secondOutsiderId = createUser($pdo, 'invite-second@example.test', 'Second', 'Outsider');
$pendingUserId = createUser($pdo, 'invite-pending@example.test', 'Pending', 'Outsider');

$groupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Invitation Test Group',
    'description' => 'Used for invitation verification.',
], $adminId)->groupId();
addMember($pdo, $groupId, $memberId, 'member');

$creation = new InvitationCreationService($groups, $memberships, $invitations);
$acceptance = new InvitationAcceptanceService($memberships, $invitations);

$nonAdminCreate = $creation->create($groupId, $memberId);
assertTrue(!$nonAdminCreate->isSuccess(), 'non-admin cannot create invitation');
assertSame('Only group administrators can create invitations.', $nonAdminCreate->message(), 'non-admin create returns clear message');
assertSame(0, invitationCount($pdo, $groupId), 'non-admin create does not store invitation');

$missingGroupCreate = $creation->create(999999, $adminId);
assertTrue(!$missingGroupCreate->isSuccess(), 'missing group invitation is rejected');
assertSame('Group not found.', $missingGroupCreate->message(), 'missing group create returns clear message');

$created = $creation->create($groupId, $adminId);
assertTrue($created->isSuccess(), 'administrator can create invitation');
assertSame('Invitation link created.', $created->message(), 'create returns success message');
assertTrue(is_string($created->token()) && strlen((string) $created->token()) === 64, 'create returns opaque token');
assertSame(1, invitationCount($pdo, $groupId), 'create stores invitation');
assertSame(0, rawTokenCount($pdo, (string) $created->token()), 'raw invitation token is not stored');
assertTrue(invitationExpiresNear24Hours($pdo, (string) $created->token()), 'invitation expires near 24 hours');

$accept = $acceptance->accept((string) $created->token(), $outsiderId);
assertTrue($accept->isSuccess(), 'valid invitation can be accepted');
assertSame('You joined the group.', $accept->message(), 'accept returns success message');
assertSame($groupId, $accept->groupId(), 'accept reports joined group');
assertTrue($memberships->isMember($groupId, $outsiderId), 'accepted user becomes member');
assertSame('member', membershipRole($pdo, $groupId, $outsiderId), 'accepted user joins as member role');
assertTrue(invitationUsedBy($pdo, (string) $created->token(), $outsiderId), 'accepted invitation is marked used by user');

$reuse = $acceptance->accept((string) $created->token(), $secondOutsiderId);
assertTrue(!$reuse->isSuccess(), 'used invitation cannot be accepted again');
assertSame('Invitation is no longer available.', $reuse->message(), 'used invitation returns unavailable message');
assertTrue(!$memberships->isMember($groupId, $secondOutsiderId), 'reuse does not create membership');

$expired = $creation->create($groupId, $adminId);
expireInvitation($pdo, (string) $expired->token());
$expiredAccept = $acceptance->accept((string) $expired->token(), $secondOutsiderId);
assertTrue(!$expiredAccept->isSuccess(), 'expired invitation is rejected');
assertSame('Invitation is no longer available.', $expiredAccept->message(), 'expired invitation returns unavailable message');
assertTrue(!$memberships->isMember($groupId, $secondOutsiderId), 'expired invitation does not create membership');

$memberInvite = $creation->create($groupId, $adminId);
$alreadyMember = $acceptance->accept((string) $memberInvite->token(), $memberId);
assertTrue(!$alreadyMember->isSuccess(), 'existing member does not consume invitation');
assertSame('You are already a member of this group.', $alreadyMember->message(), 'existing member gets clear message');
assertTrue(!invitationIsUsed($pdo, (string) $memberInvite->token()), 'existing member does not mark invitation used');

$pendingInvite = $creation->create($groupId, $adminId);
createPendingRequest($pdo, $groupId, $pendingUserId);
$pendingAccept = $acceptance->accept((string) $pendingInvite->token(), $pendingUserId);
assertTrue($pendingAccept->isSuccess(), 'pending requester can accept invitation');
assertTrue($memberships->isMember($groupId, $pendingUserId), 'pending requester becomes member through invitation');
assertSame('approved', joinRequestStatus($pdo, $groupId, $pendingUserId), 'invitation acceptance resolves pending request as approved');

$session = [];
$_SESSION = &$session;
$csrf = new CsrfTokenManager($session);
$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');
$adminController = new GroupAdminController(
    new MembershipApprovalService($memberships),
    new MemberRoleService($memberships),
    $creation,
    $memberships,
    new SessionAuthenticator($session),
    $csrf,
    $templates
);
$invitationController = new InvitationController(
    $acceptance,
    $invitations,
    new SessionAuthenticator($session),
    $csrf,
    $templates
);

$guestManage = $adminController->invitations($groupId);
assertTrue($guestManage instanceof RedirectResponse, 'guest invitation management redirects');
assertSame('/login', $guestManage->location(), 'guest invitation management redirects to login');

$guestCreate = $adminController->createInvitation($groupId, ['_csrf' => 'invalid-token']);
assertTrue($guestCreate instanceof RedirectResponse, 'guest invitation create redirects');
assertSame('/login', $guestCreate->location(), 'guest invitation create redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($memberId);

$forbiddenManage = $adminController->invitations($groupId);
assertTrue($forbiddenManage instanceof Response, 'non-admin invitation management returns response');
assertSame(403, $forbiddenManage->statusCode(), 'non-admin invitation management is forbidden');

$forbiddenCreate = $adminController->createInvitation($groupId, ['_csrf' => $csrf->token('admin.invitations')]);
assertTrue($forbiddenCreate instanceof Response, 'non-admin invitation create returns response');
assertSame(403, $forbiddenCreate->statusCode(), 'non-admin invitation create is forbidden');

$authenticator->login($adminId);
$invalidCsrf = $adminController->createInvitation($groupId, ['_csrf' => 'invalid-token']);
assertTrue($invalidCsrf instanceof Response, 'invalid invitation CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid invitation CSRF is rejected with status 419');

$managePage = $adminController->invitations($groupId);
assertTrue($managePage instanceof Response, 'admin invitation page renders');
assertTrue(str_contains($managePage->body(), 'Invitations'), 'admin invitation page has heading');
assertTrue(str_contains($managePage->body(), 'group-admin-nav'), 'admin invitation page renders shared admin navigation');
assertTrue(str_contains($managePage->body(), '/admin/invitations" aria-current="page"'), 'admin invitation page marks invitations tab active');
assertTrue(str_contains($managePage->body(), 'Create invitation link'), 'admin invitation page has create action');

$createdResponse = $adminController->createInvitation($groupId, ['_csrf' => $csrf->token('admin.invitations')]);
assertTrue($createdResponse instanceof RedirectResponse, 'valid invitation create redirects');
assertSame("/groups/{$groupId}/admin/invitations", $createdResponse->location(), 'valid invitation create redirects to invitation list');
assertTrue(isset($session['_flash']['invite_url']) && is_string($session['_flash']['invite_url']), 'valid invitation create flashes hot link');
assertTrue(str_contains((string) $session['_flash']['invite_url'], '/invitations/'), 'flashed hot link uses invitation route');
assertTrue(str_starts_with((string) $session['_flash']['invite_url'], 'http'), 'flashed hot link is absolute');
$controllerToken = basename((string) $session['_flash']['invite_url']);

$authenticator->login($secondOutsiderId);
$showInvite = $invitationController->show($controllerToken);
assertTrue($showInvite instanceof Response, 'valid invitation page renders');
assertTrue(str_contains($showInvite->body(), 'Invitation Test Group'), 'valid invitation page shows group name');
assertTrue(!str_contains($showInvite->body(), '<script>alert("xss")</script>'), 'valid invitation page escapes authenticated user data');

$invalidAcceptCsrf = $invitationController->accept($controllerToken, ['_csrf' => 'invalid-token']);
assertTrue($invalidAcceptCsrf instanceof Response, 'invalid accept CSRF returns response');
assertSame(419, $invalidAcceptCsrf->statusCode(), 'invalid accept CSRF is rejected with status 419');
assertTrue(!$memberships->isMember($groupId, $secondOutsiderId), 'invalid accept CSRF does not create membership');

$acceptResponse = $invitationController->accept($controllerToken, ['_csrf' => $csrf->token('invitations.accept')]);
assertTrue($acceptResponse instanceof RedirectResponse, 'valid invite accept redirects');
assertSame("/groups/{$groupId}", $acceptResponse->location(), 'valid invite accept redirects to group');
assertTrue($memberships->isMember($groupId, $secondOutsiderId), 'valid invite accept creates membership through controller');

$unavailablePage = $invitationController->show($controllerToken);
assertTrue($unavailablePage instanceof Response, 'used invitation page renders unavailable state');
assertSame(410, $unavailablePage->statusCode(), 'used invitation page returns gone status');
assertTrue(str_contains($unavailablePage->body(), 'Invitation unavailable'), 'used invitation page shows unavailable message');

cleanup($pdo);

echo "Invitation verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name = 'Invitation Test Group'");
    $pdo->exec("DELETE FROM users WHERE email IN ('invite-admin@example.test', 'invite-member@example.test', 'invite-outsider@example.test', 'invite-second@example.test', 'invite-pending@example.test')");
}

function createUser(PDO $pdo, string $email, string $firstName, string $lastName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, $lastName, $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function addMember(PDO $pdo, int $groupId, int $userId, string $role): void
{
    $statement = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)');
    $statement->execute([$groupId, $userId, $role]);
}

function createPendingRequest(PDO $pdo, int $groupId, int $userId): void
{
    $statement = $pdo->prepare('INSERT INTO group_join_requests (group_id, user_id, status) VALUES (?, ?, ?)');
    $statement->execute([$groupId, $userId, 'pending']);
}

function invitationCount(PDO $pdo, int $groupId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM group_invitations WHERE group_id = ?');
    $statement->execute([$groupId]);

    return (int) $statement->fetchColumn();
}

function rawTokenCount(PDO $pdo, string $token): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM group_invitations WHERE token_hash = ?');
    $statement->execute([$token]);

    return (int) $statement->fetchColumn();
}

function invitationExpiresNear24Hours(PDO $pdo, string $token): bool
{
    $statement = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), expires_at) FROM group_invitations WHERE token_hash = ?'
    );
    $statement->execute([hash('sha256', $token)]);
    $hours = $statement->fetchColumn();

    return is_numeric($hours) && (int) $hours >= 23 && (int) $hours <= 24;
}

function invitationUsedBy(PDO $pdo, string $token, int $userId): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM group_invitations WHERE token_hash = ? AND used_by = ? AND used_at IS NOT NULL'
    );
    $statement->execute([hash('sha256', $token), $userId]);

    return (int) $statement->fetchColumn() === 1;
}

function expireInvitation(PDO $pdo, string $token): void
{
    $statement = $pdo->prepare('UPDATE group_invitations SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE token_hash = ?');
    $statement->execute([hash('sha256', $token)]);
}

function invitationIsUsed(PDO $pdo, string $token): bool
{
    $statement = $pdo->prepare('SELECT used_at IS NOT NULL FROM group_invitations WHERE token_hash = ?');
    $statement->execute([hash('sha256', $token)]);

    return (bool) $statement->fetchColumn();
}

function membershipRole(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT role FROM group_memberships WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $role = $statement->fetchColumn();

    return $role === false ? null : (string) $role;
}

function joinRequestStatus(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT status FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $status = $statement->fetchColumn();

    return $status === false ? null : (string) $status;
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
