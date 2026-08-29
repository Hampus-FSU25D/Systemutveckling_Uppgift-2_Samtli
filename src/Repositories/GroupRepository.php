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
     * @return list<array{id: int|string, name: string, description: string|null, created_at: string, join_request_status: string|null}>
     */
    public function discoverableForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                g.id,
                g.name,
                g.description,
                g.created_at,
                jr.status AS join_request_status
            FROM groups g
            LEFT JOIN group_memberships gm
                ON gm.group_id = g.id
                AND gm.user_id = ?
            LEFT JOIN group_join_requests jr
                ON jr.group_id = g.id
                AND jr.user_id = ?
            WHERE gm.user_id IS NULL
            ORDER BY g.created_at DESC, g.id DESC"
        );
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
