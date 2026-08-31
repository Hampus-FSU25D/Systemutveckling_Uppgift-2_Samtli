<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Discussions\DiscussionCreationService;
use Samtli\Groups\GroupCreationService;
use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$groups = new GroupRepository($pdo);
$memberships = new MembershipRepository($pdo);
$discussions = new DiscussionRepository($pdo);
$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');

cleanup($pdo);

$ownerId = createUser($pdo, 'home-feed-owner@example.test', 'Photo');
$outsiderId = createUser($pdo, 'home-feed-outsider@example.test', 'Hidden');

$photoGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Photo Group',
    'description' => 'A group for everyday photography.',
], $ownerId)->groupId();

$hiddenGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Hidden Group',
    'description' => 'A group this user has not joined.',
], $outsiderId)->groupId();

$smallGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Small Public Group',
    'description' => 'A smaller public community.',
], $ownerId)->groupId();
addMember($pdo, $smallGroupId, $outsiderId);

$topOneOwnerId = createUser($pdo, 'home-feed-top-one-owner@example.test', 'TopOne');
$topTwoOwnerId = createUser($pdo, 'home-feed-top-two-owner@example.test', 'TopTwo');
$topThreeOwnerId = createUser($pdo, 'home-feed-top-three-owner@example.test', 'TopThree');
$topOneGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Top Public One',
    'description' => 'The largest public community in this fixture.',
], $topOneOwnerId)->groupId();
$topTwoGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Top Public Two',
    'description' => 'The second largest public community in this fixture.',
], $topTwoOwnerId)->groupId();
$topThreeGroupId = (int) (new GroupCreationService($groups))->create([
    'name' => 'Top Public Three',
    'description' => 'The third largest public community in this fixture.',
], $topThreeOwnerId)->groupId();

for ($index = 1; $index <= 7; $index++) {
    addMember($pdo, $topOneGroupId, createUser($pdo, "home-feed-top-one-{$index}@example.test", 'TopOne'));
}

for ($index = 1; $index <= 6; $index++) {
    addMember($pdo, $topTwoGroupId, createUser($pdo, "home-feed-top-two-{$index}@example.test", 'TopTwo'));
}

for ($index = 1; $index <= 5; $index++) {
    addMember($pdo, $topThreeGroupId, createUser($pdo, "home-feed-top-three-{$index}@example.test", 'TopThree'));
}

$discussionCreation = new DiscussionCreationService($memberships, $discussions);
$photoDiscussion = $discussionCreation->create($photoGroupId, $ownerId, [
    'subject' => 'Street photo thread',
    'content' => 'Share the frames you noticed today.',
]);
$hiddenDiscussion = $discussionCreation->create($hiddenGroupId, $outsiderId, [
    'subject' => 'Private planning thread',
    'content' => 'This should stay out of another user home feed.',
]);

assertTrue($photoDiscussion->isSuccess(), 'fixture discussion is created in member group');
assertTrue($hiddenDiscussion->isSuccess(), 'fixture discussion is created in outsider group');

$homeGroups = $groups->forUser($ownerId);
$homeDiscussions = $discussions->latestForUserGroups($ownerId, 5);
$featuredGroups = $groups->topByMemberCount(3);

assertSame(['Small Public Group', 'Photo Group'], array_column($homeGroups, 'name'), 'home groups only include memberships for the current user');
assertSame(['Street photo thread'], array_column($homeDiscussions, 'subject'), 'home discussions only include threads from current user groups');
assertSame(['Top Public One', 'Top Public Two', 'Top Public Three'], array_column($featuredGroups, 'name'), 'guest home featured groups use top real communities');
assertSame([8, 7, 6], array_map('intval', array_column($featuredGroups, 'member_count')), 'guest home featured groups include real member counts');

$authenticatedHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => $ownerId,
    'homeGroups' => $homeGroups,
    'homeDiscussions' => $homeDiscussions,
]);

assertContains($authenticatedHome, 'Photo Group', 'authenticated home renders the real user group');
assertContains($authenticatedHome, 'Street photo thread', 'authenticated home renders the real group discussion');
$expectedUpdatedDate = date('M j, Y', strtotime((string) $homeDiscussions[0]['updated_at']));
assertContains($authenticatedHome, $expectedUpdatedDate, 'authenticated home renders a readable discussion date');
assertNotContains($authenticatedHome, (string) $homeDiscussions[0]['updated_at'], 'authenticated home does not render raw database timestamps');
assertNotContains($authenticatedHome, 'Urban Gardens', 'authenticated home does not render placeholder groups');
assertNotContains($authenticatedHome, 'Alvar Aalto', 'authenticated home does not render placeholder threads');
assertNotContains($authenticatedHome, 'Hidden Group', 'authenticated home does not leak groups from other users');
assertNotContains($authenticatedHome, 'Private planning thread', 'authenticated home does not leak discussions from other users');

$emptyUserId = createUser($pdo, 'home-feed-empty@example.test', 'Empty');
$emptyHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => $emptyUserId,
    'homeGroups' => $groups->forUser($emptyUserId),
    'homeDiscussions' => $discussions->latestForUserGroups($emptyUserId, 5),
]);

assertContains($emptyHome, 'No groups yet', 'authenticated home has an honest empty groups state');
assertContains($emptyHome, 'No threads yet', 'authenticated home has an honest empty discussions state');

$guestHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => null,
    'homeFeaturedGroups' => $featuredGroups,
]);

assertContains($guestHome, 'Top Public One', 'guest home renders top real community');
assertContains($guestHome, 'Top Public Two', 'guest home renders second real community');
assertContains($guestHome, 'Top Public Three', 'guest home renders third real community');
assertContains($guestHome, '21 members already connecting', 'guest home renders real member total from featured groups');
assertNotContains($guestHome, '4.2k members', 'guest home does not render fake landing member count');
assertNotContains($guestHome, '>Web Dev<', 'guest home does not render fake landing community');
assertNotContains($guestHome, '>Golf<', 'guest home does not render fake landing community');

cleanup($pdo);

echo "Home feed verification passed.\n";

function cleanup(PDO $pdo): void
{
    $pdo->exec("DELETE FROM groups WHERE name IN ('Photo Group', 'Hidden Group', 'Small Public Group', 'Top Public One', 'Top Public Two', 'Top Public Three')");
    $pdo->exec("DELETE FROM users WHERE email IN ('home-feed-owner@example.test', 'home-feed-outsider@example.test', 'home-feed-empty@example.test', 'home-feed-top-one-owner@example.test', 'home-feed-top-two-owner@example.test', 'home-feed-top-three-owner@example.test')");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'home-feed-top-one-%@example.test' OR email LIKE 'home-feed-top-two-%@example.test' OR email LIKE 'home-feed-top-three-%@example.test'");
}

function createUser(PDO $pdo, string $email, string $firstName): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$firstName, 'Tester', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertNotContains(string $haystack, string $needle, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
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

function addMember(PDO $pdo, int $groupId, int $userId): void
{
    $statement = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)');
    $statement->execute([$groupId, $userId, 'member']);
}
