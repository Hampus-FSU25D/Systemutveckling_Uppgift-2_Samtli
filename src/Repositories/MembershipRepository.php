<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;
use Throwable;

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

    public function isAdministrator(int $groupId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM group_memberships WHERE group_id = ? AND user_id = ? AND role = 'administrator'"
        );
        $statement->execute([$groupId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @return list<array{id: int|string, user_id: int|string, first_name: string, last_name: string, email: string, created_at: string}>
     */
    public function pendingJoinRequestsForGroup(int $groupId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                jr.id,
                jr.user_id,
                u.first_name,
                u.last_name,
                u.email,
                jr.created_at
            FROM group_join_requests jr
            INNER JOIN users u ON u.id = jr.user_id
            WHERE jr.group_id = ?
                AND jr.status = 'pending'
            ORDER BY jr.created_at ASC, jr.id ASC"
        );
        $statement->execute([$groupId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approvePendingJoinRequest(int $groupId, int $requestId, int $administratorId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $request = $this->pdo->prepare(
                "SELECT user_id FROM group_join_requests WHERE id = ? AND group_id = ? AND status = 'pending' FOR UPDATE"
            );
            $request->execute([$requestId, $groupId]);
            $userId = $request->fetchColumn();

            if ($userId === false) {
                $this->pdo->rollBack();

                return false;
            }

            $membership = $this->pdo->prepare(
                'INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)'
            );
            $membership->execute([$groupId, (int) $userId, 'member']);

            $approval = $this->pdo->prepare(
                "UPDATE group_join_requests
                SET status = 'approved', handled_at = CURRENT_TIMESTAMP, handled_by = ?
                WHERE id = ?"
            );
            $approval->execute([$administratorId, $requestId]);

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
