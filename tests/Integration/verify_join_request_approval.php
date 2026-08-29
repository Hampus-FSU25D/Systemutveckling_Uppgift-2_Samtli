<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupAdminController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Memberships\MemberRoleService;
use Samtli\Memberships\MembershipApprovalService;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$groups = new GroupRepository($pdo);
$memberships = new MembershipRepository($pdo);
$approval = new MembershipApprovalService($memberships);

cleanup($pdo);

$adminId = createUser($pdo, 'approval-admin@example.test', 'Admin');
$applicantId = createUser($pdo, 'approval-applicant@example.test', '<script>alert("xss")</script>');
$secondApplicantId = createUser($pdo, 'approval-second@example.test', '<script>alert("xss")</script>');
$nonAdminId = createUser($pdo, 'approval-non-admin@example.test', 'Visitor');

$groupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Approval Test Group',
    'description' => 'Used for administrator approval verification.',
], $adminId)->groupId();

$requests = new MembershipRequestService($groups, $memberships);
$requests->request($groupId, $applicantId);
$requests->request($groupId, $secondApplicantId);

$requestId = requestIdFor($pdo, $groupId, $applicantId);
$secondRequestId = requestIdFor($pdo, $groupId, $secondApplicantId);

$nonAdminApproval = $approval->approve($groupId, $requestId, $nonAdminId);
assertTrue(!$nonAdminApproval->isSuccess(), 'non-admin approval is rejected');
assertSame('Only group administrators can approve membership requests.', $nonAdminApproval->message(), 'non-admin approval returns clear message');
assertSame(null, membershipRole($pdo, $groupId, $applicantId), 'non-admin approval does not create membership');

$adminApproval = $approval->approve($groupId, $requestId, $adminId);
assertTrue($adminApproval->isSuccess(), 'administrator can approve pending request');
assertSame('Membership request approved.', $adminApproval->message(), 'administrator approval returns success message');
assertSame('member', membershipRole($pdo, $groupId, $applicantId), 'approved applicant becomes group member');
assertSame('approved', requestStatus($pdo, $requestId), 'approved request status is persisted');
assertSame($adminId, handledBy($pdo, $requestId), 'approved request stores handling administrator');
assertTrue(handledAt($pdo, $requestId) !== null, 'approved request stores handled timestamp');

$duplicateApproval = $approval->approve($groupId, $requestId, $adminId);
assertTrue(!$duplicateApproval->isSuccess(), 'already handled request cannot be approved again');
assertSame('Pending request not found.', $duplicateApproval->message(), 'already handled request returns clear message');

$session = [];
$csrf = new CsrfTokenManager($session);
$controller = new GroupAdminController(
    $approval,
    new MemberRoleService($memberships),
    $memberships,
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestList = $controller->joinRequests($groupId);
assertTrue($guestList instanceof RedirectResponse, 'guest admin request list redirects');
assertSame('/login', $guestList->location(), 'guest admin request list redirects to login');

$guestApprove = $controller->approveJoinRequest($groupId, $secondRequestId, ['_csrf' => 'invalid-token']);
assertTrue($guestApprove instanceof RedirectResponse, 'guest approval redirects');
assertSame('/login', $guestApprove->location(), 'guest approval redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($nonAdminId);

$forbiddenList = $controller->joinRequests($groupId);
assertTrue($forbiddenList instanceof Response, 'non-admin request list returns response');
assertSame(403, $forbiddenList->statusCode(), 'non-admin request list is forbidden');

$forbiddenApprove = $controller->approveJoinRequest($groupId, $secondRequestId, ['_csrf' => $csrf->token('admin.join_requests')]);
assertTrue($forbiddenApprove instanceof Response, 'non-admin approval returns response');
assertSame(403, $forbiddenApprove->statusCode(), 'non-admin approval is forbidden');
assertSame(null, membershipRole($pdo, $groupId, $secondApplicantId), 'non-admin controller approval does not create membership');

$authenticator->login($adminId);

$invalidCsrf = $controller->approveJoinRequest($groupId, $secondRequestId, ['_csrf' => 'invalid-token']);
assertTrue($invalidCsrf instanceof Response, 'invalid admin approval CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid admin approval CSRF is rejected with status 419');
assertSame(null, membershipRole($pdo, $groupId, $secondApplicantId), 'invalid admin approval CSRF does not create membership');

$listPage = $controller->joinRequests($groupId, 'Saved.');
assertTrue($listPage instanceof Response, 'admin request list renders');
assertTrue(str_contains($listPage->body(), 'Pending requests'), 'admin request list has pending heading');
assertTrue(str_contains($listPage->body(), 'approval-second@example.test'), 'admin request list includes applicant email');
assertTrue(!str_contains($listPage->body(), '<script>alert("xss")</script>'), 'admin request list does not render raw applicant HTML');
assertTrue(str_contains($listPage->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'admin request list escapes applicant names');

$validCsrf = $csrf->token('admin.join_requests');
$controllerApproval = $controller->approveJoinRequest($groupId, $secondRequestId, ['_csrf' => $validCsrf]);
assertTrue($controllerApproval instanceof RedirectResponse, 'valid controller approval redirects');
assertSame("/groups/{$groupId}/admin/join-requests", $controllerApproval->location(), 'valid controller approval redirects to admin request list');
assertSame('member', membershipRole($pdo, $groupId, $secondApplicantId), 'controller approval creates member role');
assertSame('approved', requestStatus($pdo, $secondRequestId), 'controller approval persists approved status');

cleanup($pdo);

echo "Join request approval verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name = 'Approval Test Group'");
    $pdo->exec("DELETE FROM users WHERE email IN ('approval-admin@example.test', 'approval-applicant@example.test', 'approval-second@example.test', 'approval-non-admin@example.test')");
}

function createUser(PDO $pdo, string $email, string $firstName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, 'Tester', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function requestIdFor(PDO $pdo, int $groupId, int $userId): int
{
    $statement = $pdo->prepare('SELECT id FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);

    return (int) $statement->fetchColumn();
}

function membershipRole(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT role FROM group_memberships WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $role = $statement->fetchColumn();

    return $role === false ? null : (string) $role;
}

function requestStatus(PDO $pdo, int $requestId): ?string
{
    $statement = $pdo->prepare('SELECT status FROM group_join_requests WHERE id = ?');
    $statement->execute([$requestId]);
    $status = $statement->fetchColumn();

    return $status === false ? null : (string) $status;
}

function handledBy(PDO $pdo, int $requestId): ?int
{
    $statement = $pdo->prepare('SELECT handled_by FROM group_join_requests WHERE id = ?');
    $statement->execute([$requestId]);
    $handledBy = $statement->fetchColumn();

    return $handledBy === false || $handledBy === null ? null : (int) $handledBy;
}

function handledAt(PDO $pdo, int $requestId): ?string
{
    $statement = $pdo->prepare('SELECT handled_at FROM group_join_requests WHERE id = ?');
    $statement->execute([$requestId]);
    $handledAt = $statement->fetchColumn();

    return $handledAt === false || $handledAt === null ? null : (string) $handledAt;
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
