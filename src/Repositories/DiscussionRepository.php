<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;
use Throwable;

final class DiscussionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createWithFirstPost(int $groupId, int $userId, string $subject, string $content): int
    {
        $this->pdo->beginTransaction();

        try {
            $discussion = $this->pdo->prepare(
                'INSERT INTO discussions (group_id, created_by, subject) VALUES (?, ?, ?)'
            );
            $discussion->execute([$groupId, $userId, $subject]);
            $discussionId = (int) $this->pdo->lastInsertId();

            $post = $this->pdo->prepare(
                'INSERT INTO posts (discussion_id, created_by, content) VALUES (?, ?, ?)'
            );
            $post->execute([$discussionId, $userId, $content]);

            $this->pdo->commit();

            return $discussionId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<array{id: int|string, subject: string, created_at: string, first_name: string, last_name: string, reply_count: int|string}>
     */
    public function forGroup(int $groupId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                d.id,
                d.subject,
                d.created_at,
                u.first_name,
                u.last_name,
                GREATEST(COUNT(p.id) - 1, 0) AS reply_count
            FROM discussions d
            INNER JOIN users u ON u.id = d.created_by
            LEFT JOIN posts p ON p.discussion_id = d.id
            WHERE d.group_id = ?
            GROUP BY d.id, d.subject, d.created_at, u.first_name, u.last_name
            ORDER BY d.updated_at DESC, d.id DESC"
        );
        $statement->execute([$groupId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{id: int|string, group_id: int|string, subject: string, first_name: string, last_name: string, posts: list<array{id: int|string, content: string, first_name: string, last_name: string, created_at: string}>}|null
     */
    public function findInGroupWithPosts(int $groupId, int $discussionId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.id, d.group_id, d.subject, u.first_name, u.last_name
            FROM discussions d
            INNER JOIN users u ON u.id = d.created_by
            WHERE d.id = ?
                AND d.group_id = ?"
        );
        $statement->execute([$discussionId, $groupId]);
        $discussion = $statement->fetch(PDO::FETCH_ASSOC);

        if ($discussion === false) {
            return null;
        }

        $posts = $this->pdo->prepare(
            "SELECT p.id, p.content, p.created_at, u.first_name, u.last_name
            FROM posts p
            INNER JOIN users u ON u.id = p.created_by
            WHERE p.discussion_id = ?
            ORDER BY p.created_at ASC, p.id ASC"
        );
        $posts->execute([$discussionId]);
        $discussion['posts'] = $posts->fetchAll(PDO::FETCH_ASSOC);

        return $discussion;
    }
}
