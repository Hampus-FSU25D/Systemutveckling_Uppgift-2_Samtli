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
}
