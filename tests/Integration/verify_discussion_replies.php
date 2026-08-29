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
$discussionCreation = new DiscussionCreationService($memberships, $discussions);
$replyCreation = new ReplyCreationService($memberships, $discussions);

cleanup($pdo);

$adminId = createUser($pdo, 'reply-admin@example.test', 'Admin');
$memberId = createUser($pdo, 'reply-member@example.test', 'Member');
$nonMemberId = createUser($pdo, 'reply-non-member@example.test', 'Visitor');

$groupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Reply Test Group',
    'description' => 'Used for reply verification.',
], $adminId)->groupId();
addMember($pdo, $groupId, $memberId);

$discussionId = (int) $discussionCreation->create($groupId, $adminId, [
    'subject' => 'Reply target',
    'content' => 'Original post.',
])->discussionId();

$reply = $replyCreation->create($groupId, $discussionId, $memberId, [
    'content' => ' First reply. ',
]);
assertTrue($reply->isSuccess(), 'member can reply to discussion');
assertTrue($reply->postId() !== null && $reply->postId() > 0, 'reply creation returns post id');
assertSame('First reply.', postContent($pdo, (int) $reply->postId()), 'reply content is trimmed before persistence');
assertSame($memberId, postCreator($pdo, (int) $reply->postId()), 'reply stores creator');
assertSame(2, countPosts($pdo, $discussionId), 'reply adds a post after the original post');

assertReplyRejected($replyCreation, $pdo, $groupId, $discussionId, $memberId, [
    'content' => '',
], 'missing reply body is rejected');

assertReplyRejected($replyCreation, $pdo, $groupId, $discussionId, $nonMemberId, [
    'content' => 'Should not persist.',
], 'non-member reply is rejected');

assertReplyRejected($replyCreation, $pdo, $groupId, 999999, $memberId, [
    'content' => 'Missing discussion.',
], 'reply to missing discussion is rejected');

$session = [];
$csrf = new CsrfTokenManager($session);
$discussionController = new DiscussionController(
    $discussionCreation,
    $replyCreation,
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

$guestReply = $discussionController->storeReply($groupId, $discussionId, ['_csrf' => 'invalid-token']);
assertTrue($guestReply instanceof RedirectResponse, 'guest reply redirects');
assertSame('/login', $guestReply->location(), 'guest reply redirects to login');

$authenticator = new SessionAuthenticator($session);
$authenticator->login($nonMemberId);

$forbiddenReply = $discussionController->storeReply($groupId, $discussionId, [
    '_csrf' => $csrf->token('discussions.reply'),
    'content' => 'Forbidden.',
]);
assertTrue($forbiddenReply instanceof Response, 'non-member reply returns response');
assertSame(403, $forbiddenReply->statusCode(), 'non-member reply is forbidden');

$authenticator->login($memberId);

$invalidCsrf = $discussionController->storeReply($groupId, $discussionId, [
    '_csrf' => 'invalid-token',
    'content' => 'Invalid CSRF.',
]);
assertTrue($invalidCsrf instanceof Response, 'invalid reply CSRF returns response');
assertSame(419, $invalidCsrf->statusCode(), 'invalid reply CSRF is rejected with status 419');

$invalidForm = $discussionController->storeReply($groupId, $discussionId, [
    '_csrf' => $csrf->token('discussions.reply'),
    'content' => '<script>alert("reply")</script>',
]);
assertTrue($invalidForm instanceof RedirectResponse, 'valid script-like reply redirects because content is escaped on display');

$xssPostId = latestPostId($pdo, $discussionId);
$detail = $discussionController->show($groupId, $discussionId);
assertTrue($detail instanceof Response, 'discussion detail renders with replies');
assertTrue(str_contains($detail->body(), 'Reply to discussion'), 'discussion detail renders reply composer');
assertTrue(!str_contains($detail->body(), '<script>alert("reply")</script>'), 'discussion detail does not render raw reply HTML');
assertTrue(str_contains($detail->body(), '&lt;script&gt;alert(&quot;reply&quot;)&lt;/script&gt;'), 'discussion detail escapes reply content');
assertSame('<script>alert("reply")</script>', postContent($pdo, $xssPostId), 'script-like reply content is stored as text');

$validReply = $discussionController->storeReply($groupId, $discussionId, [
    '_csrf' => $csrf->token('discussions.reply'),
    'content' => 'Controller reply.',
]);
assertTrue($validReply instanceof RedirectResponse, 'valid reply redirects');
assertSame("/groups/{$groupId}/discussions/{$discussionId}", $validReply->location(), 'valid reply redirects to discussion detail');

$groupPage = $groupController->show($groupId);
assertTrue($groupPage instanceof Response, 'group page renders after replies');
assertTrue(str_contains($groupPage->body(), '3 replies'), 'group page shows reply count excluding original post');

cleanup($pdo);

echo "Discussion reply verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name = 'Reply Test Group'");
    $pdo->exec("DELETE FROM users WHERE email IN ('reply-admin@example.test', 'reply-member@example.test', 'reply-non-member@example.test')");
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

function postContent(PDO $pdo, int $postId): ?string
{
    $statement = $pdo->prepare('SELECT content FROM posts WHERE id = ?');
    $statement->execute([$postId]);
    $content = $statement->fetchColumn();

    return $content === false ? null : (string) $content;
}

function postCreator(PDO $pdo, int $postId): ?int
{
    $statement = $pdo->prepare('SELECT created_by FROM posts WHERE id = ?');
    $statement->execute([$postId]);
    $creator = $statement->fetchColumn();

    return $creator === false ? null : (int) $creator;
}

function countPosts(PDO $pdo, int $discussionId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE discussion_id = ?');
    $statement->execute([$discussionId]);

    return (int) $statement->fetchColumn();
}

function latestPostId(PDO $pdo, int $discussionId): int
{
    $statement = $pdo->prepare('SELECT id FROM posts WHERE discussion_id = ? ORDER BY id DESC LIMIT 1');
    $statement->execute([$discussionId]);

    return (int) $statement->fetchColumn();
}

/**
 * @param array<string, string> $input
 */
function assertReplyRejected(ReplyCreationService $service, PDO $pdo, int $groupId, int $discussionId, int $userId, array $input, string $message): void
{
    $before = countPosts($pdo, $discussionId);
    $result = $service->create($groupId, $discussionId, $userId, $input);

    assertTrue(!$result->isSuccess(), $message);
    assertSame($before, countPosts($pdo, $discussionId), "{$message} without creating post");
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
