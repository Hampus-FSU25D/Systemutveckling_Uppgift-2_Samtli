<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Discussions\DiscussionCreationService;
use Samtli\Discussions\ReplyCreationService;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\DiscussionController;
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
$discussions = new DiscussionRepository($pdo);
$service = new DiscussionCreationService($memberships, $discussions);

cleanup($pdo);

$adminId = createUser($pdo, 'discussion-admin@example.test', 'Admin');
$memberId = createUser($pdo, 'discussion-member@example.test', 'Member');
$nonMemberId = createUser($pdo, 'discussion-non-member@example.test', 'Visitor');

$groupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Discussion Test Group',
    'description' => 'Used for discussion creation verification.',
], $adminId)->groupId();
addMember($pdo, $groupId, $memberId);

$emptyGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Empty Discussion Test Group',
    'description' => 'Used for empty group layout verification.',
], $adminId)->groupId();
addMember($pdo, $emptyGroupId, $memberId);

$result = $service->create($groupId, $memberId, [
    'subject' => ' Everyday image thread ',
    'content' => ' Share the frame that made you stop today. ',
]);

assertTrue($result->isSuccess(), 'member can create discussion');
assertTrue($result->discussionId() !== null && $result->discussionId() > 0, 'discussion creation returns id');

$discussion = discussionById($pdo, (int) $result->discussionId());
assertSame('Everyday image thread', $discussion['subject'] ?? null, 'discussion subject is trimmed before persistence');
assertSame($groupId, (int) ($discussion['group_id'] ?? 0), 'discussion belongs to group');
assertSame($memberId, (int) ($discussion['created_by'] ?? 0), 'discussion stores creator');
assertSame('Share the frame that made you stop today.', firstPostContent($pdo, (int) $result->discussionId()), 'first post content is trimmed before persistence');
assertSame($memberId, firstPostCreator($pdo, (int) $result->discussionId()), 'first post stores creator');

assertDiscussionRejected($service, $pdo, $groupId, $memberId, [
    'subject' => '',
    'content' => 'Body',
], 'missing subject is rejected');

assertDiscussionRejected($service, $pdo, $groupId, $memberId, [
    'subject' => str_repeat('a', 181),
    'content' => 'Body',
], 'too-long subject is rejected');

assertDiscussionRejected($service, $pdo, $groupId, $memberId, [
    'subject' => 'Subject',
    'content' => '',
], 'missing first post is rejected');

assertDiscussionRejected($service, $pdo, $groupId, $nonMemberId, [
    'subject' => 'Unauthorized',
    'content' => 'Should not persist.',
], 'non-member discussion creation is rejected');

$session = [];
$csrf = new CsrfTokenManager($session);
$discussionController = new DiscussionController(
    $service,
    new ReplyCreationService($memberships, $discussions),
    $memberships,
    $discussions,
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);
$groupController = new GroupController(
    new GroupCreationService($groups),
    new MembershipRequestService($groups, $memberships),
    $groups,
    $memberships,
    $discussions,
    new SessionAuthenticator($session),
    $csrf,
    new TemplateRenderer(dirname(__DIR__, 2) . '/templates')
);

$guestCreate = $discussionController->create($groupId);
assertTrue($guestCreate instanceof RedirectResponse, 'guest discussion form redirects');
assertSame('/login', $guestCreate->location(), 'guest discussion form redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($nonMemberId);

$forbiddenGroup = $groupController->show($groupId);
assertTrue($forbiddenGroup instanceof Response, 'non-member group page returns response');
assertSame(403, $forbiddenGroup->statusCode(), 'non-member group page is forbidden');

$forbiddenCreate = $discussionController->create($groupId);
assertTrue($forbiddenCreate instanceof Response, 'non-member discussion form returns response');
assertSame(403, $forbiddenCreate->statusCode(), 'non-member discussion form is forbidden');

$authenticator->login($memberId);
$emptyMemberGroupPage = $groupController->show($emptyGroupId);
assertTrue($emptyMemberGroupPage instanceof Response, 'member empty group page renders');
assertTrue(str_contains($emptyMemberGroupPage->body(), 'group-hero'), 'group page uses polished hero layout');
assertTrue(str_contains($emptyMemberGroupPage->body(), 'empty-state--centered'), 'empty group state is compact and centered');
assertSame(1, substr_count($emptyMemberGroupPage->body(), 'Start discussion'), 'empty group page renders one primary start discussion action');
assertTrue(!str_contains($emptyMemberGroupPage->body(), 'admin-link-row'), 'group page does not render raw admin text links');

$invalidCsrf = $discussionController->store($groupId, [
    '_csrf' => 'invalid-token',
    'subject' => 'Invalid CSRF',
    'content' => 'Should not persist.',
]);
assertTrue($invalidCsrf instanceof Response, 'invalid discussion CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid discussion CSRF is rejected with status 419');
assertSame(0, countDiscussionsBySubject($pdo, 'Invalid CSRF'), 'invalid discussion CSRF does not create discussion');

$csrfToken = $csrf->token('discussions.create');
$invalidForm = $discussionController->store($groupId, [
    '_csrf' => $csrfToken,
    'subject' => '<script>alert("xss")</script>',
    'content' => '',
]);
assertTrue($invalidForm instanceof Response, 'invalid discussion form re-renders');
assertSame(422, $invalidForm->statusCode(), 'invalid discussion form uses status 422');
assertTrue(!str_contains($invalidForm->body(), '<script>alert("xss")</script>'), 'discussion form does not render raw submitted subject');
assertTrue(str_contains($invalidForm->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'discussion form escapes submitted subject');

$csrfToken = $csrf->token('discussions.create');
$validResponse = $discussionController->store($groupId, [
    '_csrf' => $csrfToken,
    'subject' => 'Controller Discussion',
    'content' => 'Created through controller.',
]);
assertTrue($validResponse instanceof RedirectResponse, 'successful discussion form redirects');
assertTrue(str_starts_with($validResponse->location(), "/groups/{$groupId}/discussions/"), 'successful discussion form redirects to discussion page');
$controllerDiscussionId = (int) substr($validResponse->location(), strrpos($validResponse->location(), '/') + 1);

$detailPage = $discussionController->show($groupId, $controllerDiscussionId);
assertTrue($detailPage instanceof Response, 'member discussion detail renders');
assertTrue(str_contains($detailPage->body(), 'Controller Discussion'), 'discussion detail renders subject');
assertTrue(str_contains($detailPage->body(), 'Created through controller.'), 'discussion detail renders first post');

$xssDiscussion = $service->create($groupId, $memberId, [
    'subject' => '<script>alert("xss")</script>',
    'content' => '<script>alert("post")</script>',
]);
$xssDetail = $discussionController->show($groupId, (int) $xssDiscussion->discussionId());
assertTrue($xssDetail instanceof Response, 'member discussion detail renders script-like discussion');
assertTrue(!str_contains($xssDetail->body(), '<script>alert("xss")</script>'), 'discussion detail does not render raw subject HTML');
assertTrue(str_contains($xssDetail->body(), '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'), 'discussion detail escapes subject');
assertTrue(!str_contains($xssDetail->body(), '<script>alert("post")</script>'), 'discussion detail does not render raw post HTML');
assertTrue(str_contains($xssDetail->body(), '&lt;script&gt;alert(&quot;post&quot;)&lt;/script&gt;'), 'discussion detail escapes post content');

$authenticator->login($nonMemberId);
$forbiddenDetail = $discussionController->show($groupId, $controllerDiscussionId);
assertTrue($forbiddenDetail instanceof Response, 'non-member discussion detail returns response');
assertSame(403, $forbiddenDetail->statusCode(), 'non-member discussion detail is forbidden');

$authenticator->login($memberId);
$groupPage = $groupController->show($groupId);
assertTrue($groupPage instanceof Response, 'member group page renders');
assertTrue(str_contains($groupPage->body(), 'group-actions'), 'group page groups action controls when discussions exist');
assertTrue(str_contains($groupPage->body(), 'Member group'), 'member group page renders meaningful membership label');
assertTrue(str_contains($groupPage->body(), 'Recent discussions'), 'group page labels the discussion list');
assertTrue(str_contains($groupPage->body(), 'discussion-row__avatar'), 'group page renders author initials in discussion rows');
assertTrue(str_contains($groupPage->body(), 'Controller Discussion'), 'group page lists created discussion');
assertTrue(str_contains($groupPage->body(), 'Start discussion'), 'group page links to discussion creation');

cleanup($pdo);

echo "Discussion creation verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name IN ('Discussion Test Group', 'Empty Discussion Test Group')");
    $pdo->exec("DELETE FROM users WHERE email IN ('discussion-admin@example.test', 'discussion-member@example.test', 'discussion-non-member@example.test')");
}

function createUser(PDO $pdo, string $email, string $firstName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, 'Tester', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function addMember(PDO $pdo, int $groupId, int $userId): void
{
    $statement = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)');
    $statement->execute([$groupId, $userId, 'member']);
}

/**
 * @return array<string, string>|null
 */
function discussionById(PDO $pdo, int $discussionId): ?array
{
    $statement = $pdo->prepare('SELECT group_id, created_by, subject FROM discussions WHERE id = ?');
    $statement->execute([$discussionId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function firstPostContent(PDO $pdo, int $discussionId): ?string
{
    $statement = $pdo->prepare('SELECT content FROM posts WHERE discussion_id = ? ORDER BY id ASC LIMIT 1');
    $statement->execute([$discussionId]);
    $content = $statement->fetchColumn();

    return $content === false ? null : (string) $content;
}

function firstPostCreator(PDO $pdo, int $discussionId): ?int
{
    $statement = $pdo->prepare('SELECT created_by FROM posts WHERE discussion_id = ? ORDER BY id ASC LIMIT 1');
    $statement->execute([$discussionId]);
    $creator = $statement->fetchColumn();

    return $creator === false ? null : (int) $creator;
}

/**
 * @param array<string, string> $input
 */
function assertDiscussionRejected(DiscussionCreationService $service, PDO $pdo, int $groupId, int $userId, array $input, string $message): void
{
    $beforeDiscussions = totalDiscussions($pdo);
    $beforePosts = totalPosts($pdo);
    $result = $service->create($groupId, $userId, $input);

    assertTrue(!$result->isSuccess(), $message);
    assertSame($beforeDiscussions, totalDiscussions($pdo), "{$message} without creating discussion");
    assertSame($beforePosts, totalPosts($pdo), "{$message} without creating post");
}

function totalDiscussions(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn();
}

function totalPosts(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
}

function countDiscussionsBySubject(PDO $pdo, string $subject): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM discussions WHERE subject = ?');
    $statement->execute([$subject]);

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
