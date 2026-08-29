<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;

final class MembershipRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isMember(int $groupId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM group_memberships WHERE group_id = ? AND user_id = ?'
        );
        $statement->execute([$groupId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function pendingRequestExists(int $groupId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM group_join_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'"
        );
        $statement->execute([$groupId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createJoinRequest(int $groupId, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO group_join_requests (group_id, user_id, status) VALUES (?, ?, ?)'
        );
        $statement->execute([$groupId, $userId, 'pending']);
    }
}
