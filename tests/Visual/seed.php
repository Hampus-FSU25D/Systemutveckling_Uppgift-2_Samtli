<?php

declare(strict_types=1);

use Samtli\Database\Connection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$pdo = Connection::fromEnvironment();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$passwordHash = password_hash('visual-password', PASSWORD_DEFAULT);
$validToken = str_repeat('a', 64);
$expiredToken = str_repeat('b', 64);
$usedToken = str_repeat('c', 64);

$pdo->beginTransaction();

try {
    cleanupVisualData($pdo);

    $adminId = createUser($pdo, 'Hampus', 'Andersson', 'visual.admin@samtli.test', $passwordHash);
    $memberId = createUser($pdo, 'Mira', 'Stone', 'visual.member@samtli.test', $passwordHash);
    $outsiderId = createUser($pdo, 'Noah', 'River', 'visual.outsider@samtli.test', $passwordHash);
    $requesterId = createUser($pdo, 'Elin', 'Berg', 'visual.requester@samtli.test', $passwordHash);
    $secondRequesterId = createUser($pdo, 'Jonas', 'Lind', 'visual.requester2@samtli.test', $passwordHash);

    $photographyId = createGroup($pdo, 'Photography', 'A community for lens craft, image walks, editing notes and everyday photo critique.', $adminId);
    addMembership($pdo, $photographyId, $adminId, 'administrator', '2026-01-04 09:00:00');
    addMembership($pdo, $photographyId, $memberId, 'member', '2026-01-05 10:00:00');

    $emptyGroupId = createGroup($pdo, 'Empty Photography', 'A quiet photo room ready for its first discussion.', $adminId);
    addMembership($pdo, $emptyGroupId, $adminId, 'administrator', '2026-01-06 10:00:00');

    $designGroupId = createGroup($pdo, 'Nordic Design', 'For Scandinavian interiors, product craft and visual culture.', $outsiderId);
    addMembership($pdo, $designGroupId, $outsiderId, 'administrator', '2026-01-07 10:00:00');

    $coffeeGroupId = createGroup($pdo, 'Coffee Shops', 'Local cafe finds, brewing notes and good tables for conversation.', $outsiderId);
    addMembership($pdo, $coffeeGroupId, $outsiderId, 'administrator', '2026-01-08 10:00:00');
    createJoinRequest($pdo, $coffeeGroupId, $memberId, 'pending', '2026-02-01 12:00:00');

    createJoinRequest($pdo, $photographyId, $requesterId, 'pending', '2026-02-02 08:00:00');
    createJoinRequest($pdo, $photographyId, $secondRequesterId, 'pending', '2026-02-03 08:00:00');

    $everydayLensId = createDiscussion(
        $pdo,
        $photographyId,
        $memberId,
        'Everyday lens recommendations for a small camera bag?',
        'I am looking for one reliable everyday lens for street walks and family moments. What focal lengths have actually stayed in your bag?',
        '2026-02-10 10:00:00'
    );
    addPost($pdo, $everydayLensId, $adminId, 'A compact 35mm equivalent is hard to beat. It keeps the camera small and makes you move your feet.', '2026-02-10 11:00:00');
    addPost($pdo, $everydayLensId, $memberId, 'The 28mm pancake has been my favorite for travel because it handles indoors better.', '2026-02-10 12:00:00');

    createDiscussion(
        $pdo,
        $photographyId,
        $adminId,
        'How are you archiving your winter photo walks?',
        'I want a calmer structure for selects, backups and exported galleries before the folder gets too large.',
        '2026-02-09 10:00:00'
    );

    createInvitation($pdo, $photographyId, $adminId, $validToken, '2026-12-31 23:59:59');
    createInvitation($pdo, $photographyId, $adminId, $expiredToken, '2026-01-01 00:00:00');
    createInvitation($pdo, $photographyId, $adminId, $usedToken, '2026-12-31 23:59:59', '2026-02-04 09:00:00', $memberId);

    $pdo->commit();

    echo json_encode([
        'basePassword' => 'visual-password',
        'users' => [
            'admin' => ['id' => $adminId, 'email' => 'visual.admin@samtli.test'],
            'member' => ['id' => $memberId, 'email' => 'visual.member@samtli.test'],
            'outsider' => ['id' => $outsiderId, 'email' => 'visual.outsider@samtli.test'],
            'emptyAdmin' => ['id' => $adminId, 'email' => 'visual.admin@samtli.test'],
        ],
        'routes' => [
            'photographyGroup' => "/groups/{$photographyId}",
            'emptyGroup' => "/groups/{$emptyGroupId}",
            'startDiscussion' => "/groups/{$photographyId}/discussions/create",
            'everydayLensDiscussion' => "/groups/{$photographyId}/discussions/{$everydayLensId}",
            'joinRequests' => "/groups/{$photographyId}/admin/join-requests",
            'joinRequestsEmpty' => "/groups/{$emptyGroupId}/admin/join-requests",
            'members' => "/groups/{$photographyId}/admin/members",
            'invitationsAdmin' => "/groups/{$photographyId}/admin/invitations",
            'validInvitation' => "/invitations/{$validToken}",
            'expiredInvitation' => "/invitations/{$expiredToken}",
        ],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function cleanupVisualData(PDO $pdo): void
{
    $groupIds = $pdo->query("SELECT id FROM groups WHERE name IN ('Photography', 'Empty Photography', 'Nordic Design', 'Coffee Shops')")->fetchAll(PDO::FETCH_COLUMN);
    $userIds = $pdo->query("SELECT id FROM users WHERE email LIKE 'visual.%@samtli.test'")->fetchAll(PDO::FETCH_COLUMN);

    if ($groupIds !== []) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $discussionIds = ids($pdo, "SELECT id FROM discussions WHERE group_id IN ({$placeholders})", $groupIds);

        if ($discussionIds !== []) {
            executeIn($pdo, 'DELETE FROM posts WHERE discussion_id IN (%s)', $discussionIds);
        }

        executeIn($pdo, 'DELETE FROM discussions WHERE group_id IN (%s)', $groupIds);
        executeIn($pdo, 'DELETE FROM group_invitations WHERE group_id IN (%s)', $groupIds);
        executeIn($pdo, 'DELETE FROM group_join_requests WHERE group_id IN (%s)', $groupIds);
        executeIn($pdo, 'DELETE FROM group_memberships WHERE group_id IN (%s)', $groupIds);
        executeIn($pdo, 'DELETE FROM groups WHERE id IN (%s)', $groupIds);
    }

    if ($userIds !== []) {
        executeIn($pdo, 'DELETE FROM group_join_requests WHERE user_id IN (%s)', $userIds);
        executeIn($pdo, 'DELETE FROM group_memberships WHERE user_id IN (%s)', $userIds);
        executeIn($pdo, 'DELETE FROM users WHERE id IN (%s)', $userIds);
    }
}

/**
 * @param list<int|string> $params
 * @return list<int|string>
 */
function ids(PDO $pdo, string $sql, array $params): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * @param list<int|string> $ids
 */
function executeIn(PDO $pdo, string $sqlTemplate, array $ids): void
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(sprintf($sqlTemplate, $placeholders));
    $statement->execute($ids);
}

function createUser(PDO $pdo, string $firstName, string $lastName, string $email, string $passwordHash): int
{
    $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute([$firstName, $lastName, $email, $passwordHash, '2026-01-01 09:00:00', '2026-01-01 09:00:00']);

    return (int) $pdo->lastInsertId();
}

function createGroup(PDO $pdo, string $name, string $description, int $createdBy): int
{
    $statement = $pdo->prepare('INSERT INTO groups (name, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$name, $description, $createdBy, '2026-01-02 09:00:00', '2026-01-02 09:00:00']);

    return (int) $pdo->lastInsertId();
}

function addMembership(PDO $pdo, int $groupId, int $userId, string $role, string $joinedAt): void
{
    $statement = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)');
    $statement->execute([$groupId, $userId, $role, $joinedAt]);
}

function createJoinRequest(PDO $pdo, int $groupId, int $userId, string $status, string $createdAt): void
{
    $statement = $pdo->prepare('INSERT INTO group_join_requests (group_id, user_id, status, created_at) VALUES (?, ?, ?, ?)');
    $statement->execute([$groupId, $userId, $status, $createdAt]);
}

function createDiscussion(PDO $pdo, int $groupId, int $createdBy, string $subject, string $content, string $createdAt): int
{
    $statement = $pdo->prepare('INSERT INTO discussions (group_id, created_by, subject, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$groupId, $createdBy, $subject, $createdAt, $createdAt]);
    $discussionId = (int) $pdo->lastInsertId();
    addPost($pdo, $discussionId, $createdBy, $content, $createdAt);

    return $discussionId;
}

function addPost(PDO $pdo, int $discussionId, int $createdBy, string $content, string $createdAt): void
{
    $statement = $pdo->prepare('INSERT INTO posts (discussion_id, created_by, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$discussionId, $createdBy, $content, $createdAt, $createdAt]);
}

function createInvitation(PDO $pdo, int $groupId, int $createdBy, string $token, string $expiresAt, ?string $usedAt = null, ?int $usedBy = null): void
{
    $statement = $pdo->prepare('INSERT INTO group_invitations (group_id, created_by, token_hash, expires_at, used_at, used_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([$groupId, $createdBy, hash('sha256', $token), $expiresAt, $usedAt, $usedBy, '2026-02-04 08:00:00']);
}

