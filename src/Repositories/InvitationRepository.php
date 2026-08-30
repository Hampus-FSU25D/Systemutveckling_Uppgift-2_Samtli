<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;
use Throwable;

final class InvitationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $groupId, int $createdBy, string $token): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO group_invitations (group_id, created_by, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))'
        );
        $statement->execute([$groupId, $createdBy, $this->hashToken($token)]);
    }

    /**
     * @return array{id: int|string, group_id: int|string, name: string, description: string|null, expires_at: string}|null
     */
    public function findAvailableByToken(string $token): ?array
    {
        if (!$this->isPlausibleToken($token)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            "SELECT
                gi.id,
                gi.group_id,
                g.name,
                g.description,
                gi.expires_at
            FROM group_invitations gi
            INNER JOIN groups g ON g.id = gi.group_id
            WHERE gi.token_hash = ?
                AND gi.used_at IS NULL
                AND gi.expires_at > UTC_TIMESTAMP()
            LIMIT 1"
        );
        $statement->execute([$this->hashToken($token)]);
        $invitation = $statement->fetch(PDO::FETCH_ASSOC);

        return $invitation === false ? null : $invitation;
    }

    /**
     * @return list<array{id: int|string, expires_at: string, used_at: string|null, used_by: int|string|null, created_at: string}>
     */
    public function recentForGroup(int $groupId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, expires_at, used_at, used_by, created_at
            FROM group_invitations
            WHERE group_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 10'
        );
        $statement->execute([$groupId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accept(string $token, int $groupId, int $userId): bool
    {
        if (!$this->isPlausibleToken($token)) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $invitation = $this->pdo->prepare(
                'SELECT id FROM group_invitations
                WHERE token_hash = ?
                    AND group_id = ?
                    AND used_at IS NULL
                    AND expires_at > UTC_TIMESTAMP()
                FOR UPDATE'
            );
            $invitation->execute([$this->hashToken($token), $groupId]);
            $invitationId = $invitation->fetchColumn();

            if ($invitationId === false) {
                $this->pdo->rollBack();

                return false;
            }

            $membership = $this->pdo->prepare(
                'INSERT INTO group_memberships (group_id, user_id, role) VALUES (?, ?, ?)'
            );
            $membership->execute([$groupId, $userId, 'member']);

            $request = $this->pdo->prepare(
                "UPDATE group_join_requests
                SET status = 'approved', handled_at = UTC_TIMESTAMP(), handled_by = NULL
                WHERE group_id = ? AND user_id = ? AND status = 'pending'"
            );
            $request->execute([$groupId, $userId]);

            $used = $this->pdo->prepare(
                'UPDATE group_invitations SET used_at = UTC_TIMESTAMP(), used_by = ? WHERE id = ?'
            );
            $used->execute([$userId, (int) $invitationId]);

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function isPlausibleToken(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
    }
}
