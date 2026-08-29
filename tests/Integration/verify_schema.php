<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Database\Migrator;

require __DIR__ . '/../../vendor/autoload.php';

$appEnvironment = getenv('APP_ENV') ?: 'local';

if ($appEnvironment === 'production') {
    fwrite(STDERR, "Refusing to reset schema while APP_ENV=production.\n");
    exit(1);
}

$pdo = Connection::fromEnvironment();
$migrationsPath = dirname(__DIR__, 2) . '/database/migrations';

$tables = [
    'group_invitations',
    'posts',
    'discussions',
    'group_join_requests',
    'group_memberships',
    'groups',
    'users',
    'schema_migrations',
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$migrator = new Migrator($pdo, $migrationsPath);
$firstRun = $migrator->migrate();
$secondRun = $migrator->migrate();

assertTrue(count($firstRun['applied']) === 7, 'first migration run applies seven migrations');
assertTrue($secondRun['applied'] === [], 'second migration run applies nothing');

foreach (array_reverse(array_slice($tables, 0, 7)) as $table) {
    assertTableExists($pdo, $table);
    assertTableCharset($pdo, $table, 'utf8mb4');
}
assertTableExists($pdo, 'schema_migrations');

assertForeignKey($pdo, 'groups', 'fk_groups_created_by');
assertForeignKey($pdo, 'group_memberships', 'fk_group_memberships_group');
assertForeignKey($pdo, 'group_memberships', 'fk_group_memberships_user');
assertForeignKey($pdo, 'group_join_requests', 'fk_group_join_requests_group');
assertForeignKey($pdo, 'group_join_requests', 'fk_group_join_requests_user');
assertForeignKey($pdo, 'group_join_requests', 'fk_group_join_requests_handled_by');
assertForeignKey($pdo, 'discussions', 'fk_discussions_group');
assertForeignKey($pdo, 'discussions', 'fk_discussions_created_by');
assertForeignKey($pdo, 'posts', 'fk_posts_discussion');
assertForeignKey($pdo, 'posts', 'fk_posts_created_by');
assertForeignKey($pdo, 'group_invitations', 'fk_group_invitations_group');
assertForeignKey($pdo, 'group_invitations', 'fk_group_invitations_created_by');
assertForeignKey($pdo, 'group_invitations', 'fk_group_invitations_used_by');

assertUniqueIndex($pdo, 'users', 'uq_users_email');
assertUniqueIndex($pdo, 'group_memberships', 'PRIMARY');
assertUniqueIndex($pdo, 'group_join_requests', 'uq_group_join_requests_group_user');
assertUniqueIndex($pdo, 'group_invitations', 'uq_group_invitations_token_hash');

assertIndex($pdo, 'group_memberships', 'idx_group_memberships_user');
assertIndex($pdo, 'group_join_requests', 'idx_group_join_requests_group_status');
assertIndex($pdo, 'group_join_requests', 'idx_group_join_requests_user');
assertIndex($pdo, 'discussions', 'idx_discussions_group_updated');
assertIndex($pdo, 'posts', 'idx_posts_discussion_created');
assertIndex($pdo, 'group_invitations', 'idx_group_invitations_group');
assertIndex($pdo, 'group_invitations', 'idx_group_invitations_expires_unused');

$userId = insertUser($pdo, 'ada@example.test');
$adminId = insertUser($pdo, 'admin@example.test');

assertRejected(fn () => insertUser($pdo, 'ada@example.test'), 'duplicate email is rejected');
assertRejected(
    fn () => $pdo->exec("INSERT INTO groups (name, created_by) VALUES ('Invalid owner', 999999)"),
    'invalid group creator foreign key is rejected'
);

$pdo->prepare('INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)')->execute([
    'Photography',
    'A group for photographers.',
    $adminId,
]);
$groupId = (int) $pdo->lastInsertId();

$membership = $pdo->prepare('INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)');
$membership->execute([$groupId, $userId, 'member']);
assertRejected(fn () => $membership->execute([$groupId, $userId, 'administrator']), 'duplicate group membership is rejected');

$pdo->prepare('INSERT INTO discussions (group_id, created_by, subject) VALUES (?, ?, ?)')->execute([
    $groupId,
    $userId,
    'Favorite lenses',
]);
$discussionId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO posts (discussion_id, created_by, content) VALUES (?, ?, ?)')->execute([
    $discussionId,
    $userId,
    'What lens do you carry every day?',
]);

$tokenHash = hash('sha256', 'example-token');
$invite = $pdo->prepare(
    'INSERT INTO group_invitations (group_id, created_by, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))'
);
$invite->execute([$groupId, $adminId, $tokenHash]);
assertRejected(
    fn () => $invite->execute([$groupId, $adminId, $tokenHash]),
    'duplicate invitation token hash is rejected'
);

echo "Schema verification passed.\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertTableExists(PDO $pdo, string $table): void
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    assertTrue((int) $statement->fetchColumn() === 1, "table {$table} exists");
}

function assertTableCharset(PDO $pdo, string $table, string $charset): void
{
    $statement = $pdo->prepare(
        'SELECT CCSA.CHARACTER_SET_NAME
         FROM information_schema.TABLES T
         JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA
           ON CCSA.COLLATION_NAME = T.TABLE_COLLATION
         WHERE T.TABLE_SCHEMA = DATABASE() AND T.TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    assertTrue($statement->fetchColumn() === $charset, "table {$table} uses {$charset}");
}

function assertForeignKey(PDO $pdo, string $table, string $constraint): void
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?
           AND CONSTRAINT_TYPE = ?'
    );
    $statement->execute([$table, $constraint, 'FOREIGN KEY']);

    assertTrue((int) $statement->fetchColumn() === 1, "foreign key {$constraint} exists");
}

function assertUniqueIndex(PDO $pdo, string $table, string $index): void
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?
           AND NON_UNIQUE = 0'
    );
    $statement->execute([$table, $index]);

    assertTrue((int) $statement->fetchColumn() >= 1, "unique index {$table}.{$index} exists");
}

function assertIndex(PDO $pdo, string $table, string $index): void
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );
    $statement->execute([$table, $index]);

    assertTrue((int) $statement->fetchColumn() >= 1, "index {$table}.{$index} exists");
}

function assertRejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        echo "PASS: {$message}\n";
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function insertUser(PDO $pdo, string $email): int
{
    $statement = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([
        'Ada',
        'Lovelace',
        $email,
        password_hash('password-for-schema-test', PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}
