<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\Response;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$groups = new GroupRepository($pdo);
$memberships = new MembershipRepository($pdo);
$membershipRequests = new MembershipRequestService($groups, $memberships);

cleanup($pdo);

$creatorId = createUser($pdo, 'membership-owner@example.test', 'Owner');
$applicantId = createUser($pdo, 'membership-applicant@example.test', 'Applicant');

$creation = new GroupCreationService($groups);
$publicGroupId = (int) $creation->create([
    'name' => 'Street Photography <script>alert("xss")</script>',
    'description' => 'A place for everyday image-making.',
], $creatorId)->groupId();
$ownedGroupId = (int) $creation->create([
    'name' => 'Applicant Private Group',
    'description' => 'Owned by the applicant and hidden from their discovery list.',
], $applicantId)->groupId();

$discoverable = $groups->discoverableForUser($applicantId);
$discoverableById = groupsById($discoverable);
assertTrue(isset($discoverableById[$publicGroupId]), 'discoverable groups include groups the user can request');
assertTrue(!isset($discoverableById[$ownedGroupId]), 'discoverable groups exclude current memberships');
assertSame(null, $discoverableById[$publicGroupId]['join_request_status'] ?? null, 'discoverable groups expose missing request status as null');

$request = $membershipRequests->request($publicGroupId, $applicantId);
assertTrue($request->isSuccess(), 'non-member can request membership');
assertSame('pending', fetchJoinRequestStatus($pdo, $publicGroupId, $applicantId), 'membership request is persisted as pending');

$discoverableAfterRequest = $groups->discoverableForUser($applicantId);
$discoverableAfterRequestById = groupsById($discoverableAfterRequest);
assertSame('pending', $discoverableAfterRequestById[$publicGroupId]['join_request_status'] ?? null, 'discoverable groups expose pending request status');

$duplicate = $membershipRequests->request($publicGroupId, $applicantId);
assertTrue(!$duplicate->isSuccess(), 'duplicate membership request is rejected');
assertSame('You already have a pending request for this group.', $duplicate->message(), 'duplicate membership request returns clear message');
assertSame(1, countJoinRequests($pdo, $publicGroupId, $applicantId), 'duplicate membership request does not create a second row');

$memberRequest = $membershipRequests->request($ownedGroupId, $applicantId);
assertTrue(!$memberRequest->isSuccess(), 'current member cannot request membership');
assertSame('You are already a member of this group.', $memberRequest->message(), 'member request returns clear message');

$missingGroupRequest = $membershipRequests->request(999999, $applicantId);
assertTrue(!$missingGroupRequest->isSuccess(), 'requesting missing group is rejected');
assertSame('Group not found.', $missingGroupRequest->message(), 'missing group request returns clear message');

$session = [];
$csrf = new CsrfTokenManager($session);
$controller = new GroupController(
    new GroupCreationService($groups),
    $membershipRequests,
    $groups,
    $memberships,
    new DiscussionRepository($pdo),
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestIndex = $controller->index(null);
assertTrue($guestIndex instanceof RedirectResponse, 'guest discover page redirects');
assertSame('/login', $guestIndex->location(), 'guest discover page redirects to login');

$guestRequest = $controller->requestMembership($publicGroupId, ['_csrf' => 'invalid-token']);
assertTrue($guestRequest instanceof RedirectResponse, 'guest membership request redirects');
assertSame('/login', $guestRequest->location(), 'guest membership request redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($creatorId);

$invalidCsrf = $controller->requestMembership($ownedGroupId, ['_csrf' => 'invalid-token']);
assertTrue($invalidCsrf instanceof Response, 'invalid membership CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid membership CSRF is rejected with status 419');
assertSame(0, countJoinRequests($pdo, $ownedGroupId, $creatorId), 'invalid membership CSRF does not create request');

$authenticator->login($applicantId);
$discoverPage = $controller->index('Membership request sent.');
assertTrue($discoverPage instanceof Response, 'authenticated discover page renders');
assertTrue(str_contains($discoverPage->body(), 'Membership request sent.'), 'discover page renders flash message');
assertTrue(!str_contains($discoverPage->body(), '<script>alert("xss")</script>'), 'discover page does not render raw group HTML');
assertTrue(str_contains($discoverPage->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'discover page escapes group names');
assertTrue(str_contains($discoverPage->body(), 'Request pending'), 'discover page renders pending request state');

$authenticator->login($creatorId);
$validCsrf = $csrf->token('groups.join_request');
$controllerRequest = $controller->requestMembership($ownedGroupId, ['_csrf' => $validCsrf]);
assertTrue($controllerRequest instanceof RedirectResponse, 'valid membership request redirects after submit');
assertSame('/groups', $controllerRequest->location(), 'valid membership request redirects to discover page');
assertSame('pending', fetchJoinRequestStatus($pdo, $ownedGroupId, $creatorId), 'controller request persists pending status');

cleanup($pdo);

echo "Membership request verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name IN ('Street Photography <script>alert(\"xss\")</script>', 'Applicant Private Group')");
    $pdo->exec("DELETE FROM users WHERE email IN ('membership-owner@example.test', 'membership-applicant@example.test')");
}

function createUser(PDO $pdo, string $email, string $firstName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, 'Tester', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function fetchJoinRequestStatus(PDO $pdo, int $groupId, int $userId): ?string
{
    $statement = $pdo->prepare('SELECT status FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);
    $status = $statement->fetchColumn();

    return $status === false ? null : (string) $status;
}

function countJoinRequests(PDO $pdo, int $groupId, int $userId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM group_join_requests WHERE group_id = ? AND user_id = ?');
    $statement->execute([$groupId, $userId]);

    return (int) $statement->fetchColumn();
}

/**
 * @param list<array{id: int|string, name: string, description: string|null, created_at: string, join_request_status: string|null}> $groups
 * @return array<int, array{id: int|string, name: string, description: string|null, created_at: string, join_request_status: string|null}>
 */
function groupsById(array $groups): array
{
    $indexed = [];

    foreach ($groups as $group) {
        $indexed[(int) $group['id']] = $group;
    }

    return $indexed;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}
