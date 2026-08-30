<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Discussions\DiscussionCreationService;
use Samtli\Discussions\ReplyCreationService;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\DiscussionController;
use Samtli\Http\GroupAdminController;
use Samtli\Http\GroupController;
use Samtli\Http\InvitationController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Invitations\InvitationAcceptanceService;
use Samtli\Invitations\InvitationCreationService;
use Samtli\Memberships\MemberRoleService;
use Samtli\Memberships\MembershipApprovalService;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Repositories\DiscussionRepository;
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
$discussions = new DiscussionRepository($pdo);
$invitations = new InvitationRepository($pdo);

cleanup($pdo);

$alphaAdminId = createUser($pdo, 'access-alpha-admin@example.test', 'Alpha', 'Admin');
$alphaMemberId = createUser($pdo, 'access-alpha-member@example.test', 'Alpha', 'Member');
$betaAdminId = createUser($pdo, 'access-beta-admin@example.test', 'Beta', 'Admin');
$outsiderId = createUser($pdo, 'access-outsider@example.test', 'Outside', 'Visitor');

$groupCreation = new GroupCreationService($groups);
$alphaGroupId = (int) $groupCreation->create([
    'name' => 'Access Alpha Group',
    'description' => 'Private alpha group.',
], $alphaAdminId)->groupId();
$betaGroupId = (int) $groupCreation->create([
    'name' => 'Access Beta Group',
    'description' => 'Private beta group.',
], $betaAdminId)->groupId();
addMember($pdo, $alphaGroupId, $alphaMemberId, 'member');

$discussionCreation = new DiscussionCreationService($memberships, $discussions);
$replyCreation = new ReplyCreationService($memberships, $discussions);
$alphaDiscussionId = (int) $discussionCreation->create($alphaGroupId, $alphaMemberId, [
    'subject' => 'Alpha private discussion',
    'content' => 'Only alpha members should see this.',
])->discussionId();
$betaDiscussionId = (int) $discussionCreation->create($betaGroupId, $betaAdminId, [
    'subject' => 'Beta private discussion',
    'content' => 'Only beta members should see this.',
])->discussionId();

createPendingRequest($pdo, $alphaGroupId, $outsiderId);
$alphaRequestId = pendingRequestId($pdo, $alphaGroupId, $outsiderId);

$invitationCreation = new InvitationCreationService($groups, $memberships, $invitations);
$alphaInvite = $invitationCreation->create($alphaGroupId, $alphaAdminId);
$alphaToken = (string) $alphaInvite->token();

$session = [];
$_SESSION = &$session;
$csrf = new CsrfTokenManager($session);
$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');
$authenticator = new SessionAuthenticator($session);
$groupController = new GroupController(
    $groupCreation,
    new MembershipRequestService($groups, $memberships),
    $groups,
    $memberships,
    $discussions,
    $authenticator,
    $csrf,
    $templates
);
$discussionController = new DiscussionController(
    $discussionCreation,
    $replyCreation,
    $memberships,
    $discussions,
    $authenticator,
    $csrf,
    $templates
);
$adminController = new GroupAdminController(
    new MembershipApprovalService($memberships),
    new MemberRoleService($memberships),
    $invitationCreation,
    $memberships,
    $authenticator,
    $csrf,
    $templates
);
$invitationController = new InvitationController(
    new InvitationAcceptanceService($memberships, $invitations),
    $invitations,
    $authenticator,
    $csrf,
    $templates
);

$guestGroup = $groupController->show($alphaGroupId);
assertTrue($guestGroup instanceof RedirectResponse, 'guest group access redirects');
assertSame('/login', $guestGroup->location(), 'guest group access redirects to login');

$guestDiscussion = $discussionController->show($alphaGroupId, $alphaDiscussionId);
assertTrue($guestDiscussion instanceof RedirectResponse, 'guest discussion access redirects');
assertSame('/login', $guestDiscussion->location(), 'guest discussion access redirects to login');

$guestInviteAccept = $invitationController->accept($alphaToken, ['_csrf' => 'invalid-token']);
assertTrue($guestInviteAccept instanceof RedirectResponse, 'guest invitation accept redirects');
assertSame('/login', $guestInviteAccept->location(), 'guest invitation accept redirects to login');

$authenticator->login($outsiderId);

$outsiderGroup = $groupController->show($alphaGroupId);
assertTrue($outsiderGroup instanceof Response, 'outsider group access returns response');
assertSame(403, $outsiderGroup->statusCode(), 'outsider group access is forbidden');
assertTrue(!str_contains($outsiderGroup->body(), 'Private alpha group.'), 'outsider group access does not leak group content');

$outsiderDiscussion = $discussionController->show($alphaGroupId, $alphaDiscussionId);
assertTrue($outsiderDiscussion instanceof Response, 'outsider discussion access returns response');
assertSame(403, $outsiderDiscussion->statusCode(), 'outsider discussion access is forbidden');
assertTrue(!str_contains($outsiderDiscussion->body(), 'Only alpha members should see this.'), 'outsider discussion access does not leak post content');

$outsiderReply = $discussionController->storeReply($alphaGroupId, $alphaDiscussionId, [
    '_csrf' => $csrf->token('discussions.reply'),
    'content' => 'Unauthorized reply.',
]);
assertTrue($outsiderReply instanceof Response, 'outsider reply returns response');
assertSame(403, $outsiderReply->statusCode(), 'outsider reply is forbidden');
assertSame(1, countPosts($pdo, $alphaDiscussionId), 'outsider reply does not create post');

$outsiderRequests = $adminController->joinRequests($alphaGroupId);
assertTrue($outsiderRequests instanceof Response, 'outsider join-request admin page returns response');
assertSame(403, $outsiderRequests->statusCode(), 'outsider join-request admin page is forbidden');
assertTrue(!str_contains($outsiderRequests->body(), 'access-outsider@example.test'), 'outsider cannot see pending request email through admin page');

$outsiderMembers = $adminController->members($alphaGroupId);
assertTrue($outsiderMembers instanceof Response, 'outsider member admin page returns response');
assertSame(403, $outsiderMembers->statusCode(), 'outsider member admin page is forbidden');
assertTrue(!str_contains($outsiderMembers->body(), 'access-alpha-member@example.test'), 'outsider cannot see member email through admin page');

$outsiderInvitations = $adminController->invitations($alphaGroupId);
assertTrue($outsiderInvitations instanceof Response, 'outsider invitation admin page returns response');
assertSame(403, $outsiderInvitations->statusCode(), 'outsider invitation admin page is forbidden');
assertTrue(!str_contains($outsiderInvitations->body(), 'Active invitation'), 'outsider cannot see invitation rows through admin page');

$authenticator->login($betaAdminId);

$crossGroupDetail = $discussionController->show($alphaGroupId, $betaDiscussionId);
assertTrue($crossGroupDetail instanceof Response, 'cross-group discussion id returns response');
assertSame(403, $crossGroupDetail->statusCode(), 'cross-group discussion id is blocked by group membership');
assertTrue(!str_contains($crossGroupDetail->body(), 'Only beta members should see this.'), 'cross-group discussion id does not leak other group content');

$crossGroupApproval = $adminController->approveJoinRequest($alphaGroupId, $alphaRequestId, [
    '_csrf' => $csrf->token('admin.join_requests'),
]);
assertTrue($crossGroupApproval instanceof Response, 'other group admin approval returns response');
assertSame(403, $crossGroupApproval->statusCode(), 'other group admin approval is forbidden');
assertSame('pending', joinRequestStatus($pdo, $alphaGroupId, $outsiderId), 'other group admin approval does not approve request');

$crossGroupRole = $adminController->updateMemberRole($alphaGroupId, $alphaMemberId, [
    '_csrf' => $csrf->token('admin.member_roles'),
    'role' => 'administrator',
]);
assertTrue($crossGroupRole instanceof Response, 'other group admin role change returns response');
assertSame(403, $crossGroupRole->statusCode(), 'other group admin role change is forbidden');
assertSame('member', membershipRole($pdo, $alphaGroupId, $alphaMemberId), 'other group admin role change does not alter role');

$crossGroupInviteCreate = $adminController->createInvitation($alphaGroupId, [
    '_csrf' => $csrf->token('admin.invitations'),
]);
assertTrue($crossGroupInviteCreate instanceof Response, 'other group admin invitation create returns response');
assertSame(403, $crossGroupInviteCreate->statusCode(), 'other group admin invitation create is forbidden');
assertSame(1, invitationCount($pdo, $alphaGroupId), 'other group admin invitation create does not store invitation');

$authenticator->login($alphaMemberId);

$memberGroup = $groupController->show($alphaGroupId);
assertTrue($memberGroup instanceof Response, 'member group access returns response');
assertSame(200, $memberGroup->statusCode(), 'member group access is allowed');
assertTrue(str_contains($memberGroup->body(), 'Alpha private discussion'), 'member group access includes group discussion');

$wrongGroupDiscussion = $discussionController->show($alphaGroupId, $betaDiscussionId);
assertTrue($wrongGroupDiscussion instanceof Response, 'member wrong-group discussion id returns response');
assertSame(404, $wrongGroupDiscussion->statusCode(), 'member wrong-group discussion id is not found inside authorized group');
assertTrue(!str_contains($wrongGroupDiscussion->body(), 'Only beta members should see this.'), 'member wrong-group discussion id does not leak foreign post content');

cleanup($pdo);

echo "Group access control verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name IN ('Access Alpha Group', 'Access Beta Group')");
    $pdo->exec("DELETE FROM users WHERE email IN ('access-alpha-admin@example.test', 'access-alpha-member@example.test', 'access-beta-admin@example.test', 'access-outsider@example.test')");
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

function pendingRequestId(PDO $pdo, int $groupId, int $userId): int
{
    $statement = $pdo->prepare('SELECT id FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);

    return (int) $statement->fetchColumn();
}

function countPosts(PDO $pdo, int $discussionId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE discussion_id = ?');
    $statement->execute([$discussionId]);

    return (int) $statement->fetchColumn();
}

function joinRequestStatus(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT status FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $status = $statement->fetchColumn();

    return $status === false ? null : (string) $status;
}

function membershipRole(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT role FROM group_memberships WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $role = $statement->fetchColumn();

    return $role === false ? null : (string) $role;
}

function invitationCount(PDO $pdo, int $groupId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM group_invitations WHERE group_id = ?');
    $statement->execute([$groupId]);

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
