<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;
use Throwable;

final class GroupRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createWithAdministrator(string $name, string $description, int $creatorId): int
    {
        $this->pdo->beginTransaction();

        try {
            $group = $this->pdo->prepare(
                'INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)'
            );
            $group->execute([$name, $description, $creatorId]);
            $groupId = (int) $this->pdo->lastInsertId();

            $membership = $this->pdo->prepare(
                'INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)'
            );
            $membership->execute([$groupId, $creatorId, 'administrator']);

            $this->pdo->commit();

            return $groupId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function exists(int $groupId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM groups WHERE id = ?');
        $statement->execute([$groupId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @return array{id: int|string, name: string, description: string|null}|null
     */
    public function find(int $groupId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, description FROM groups WHERE id = ?');
        $statement->execute([$groupId]);
        $group = $statement->fetch(PDO::FETCH_ASSOC);

        return $group === false ? null : $group;
    }

    /**
     * @return list<array{id: int|string, name: string, description: string|null, role: string, joined_at: string}>
     */
    public function forUser(int $userId, int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $statement = $this->pdo->prepare(
            "SELECT
                g.id,
                g.name,
                g.description,
                gm.role,
                gm.joined_at
            FROM group_memberships gm
            INNER JOIN groups g ON g.id = gm.group_id
            WHERE gm.user_id = ?
            ORDER BY gm.joined_at DESC, g.id DESC
            LIMIT {$limit}"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int|string, name: string, description: string|null, member_count: int|string}>
     */
    public function topByMemberCount(int $limit = 3): array
    {
        $limit = max(1, min(6, $limit));
        $statement = $this->pdo->query(
            "SELECT
                g.id,
                g.name,
                g.description,
                COUNT(gm.user_id) AS member_count
            FROM groups g
            LEFT JOIN group_memberships gm ON gm.group_id = g.id
            GROUP BY g.id, g.name, g.description, g.created_at
            ORDER BY member_count DESC, g.created_at DESC, g.id DESC
            LIMIT {$limit}"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int|string, name: string, description: string|null, created_at: string, join_request_status: string|null, member_count: int|string}>
     */
    public function discoverableForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                g.id,
                g.name,
                g.description,
                g.created_at,
                jr.status AS join_request_status,
                COUNT(all_memberships.user_id) AS member_count
            FROM groups g
            LEFT JOIN group_memberships current_membership
                ON current_membership.group_id = g.id
                AND current_membership.user_id = ?
            LEFT JOIN group_join_requests jr
                ON jr.group_id = g.id
                AND jr.user_id = ?
            LEFT JOIN group_memberships all_memberships
                ON all_memberships.group_id = g.id
            WHERE current_membership.user_id IS NULL
            GROUP BY g.id, g.name, g.description, g.created_at, jr.status
            ORDER BY g.created_at DESC, g.id DESC"
        );
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
