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
     * @return list<array{id: int|string, group_id: int|string, subject: string, created_at: string, updated_at: string, group_name: string, first_name: string, last_name: string, first_post_content: string|null, reply_count: int|string}>
     */
    public function latestForUserGroups(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $statement = $this->pdo->prepare(
            "SELECT
                d.id,
                d.group_id,
                d.subject,
                d.created_at,
                d.updated_at,
                g.name AS group_name,
                u.first_name,
                u.last_name,
                first_post.content AS first_post_content,
                GREATEST(COUNT(p.id) - 1, 0) AS reply_count
            FROM discussions d
            INNER JOIN groups g ON g.id = d.group_id
            INNER JOIN group_memberships gm
                ON gm.group_id = d.group_id
                AND gm.user_id = ?
            INNER JOIN users u ON u.id = d.created_by
            LEFT JOIN posts first_post
                ON first_post.id = (
                    SELECT p2.id
                    FROM posts p2
                    WHERE p2.discussion_id = d.id
                    ORDER BY p2.id ASC
                    LIMIT 1
                )
            LEFT JOIN posts p ON p.discussion_id = d.id
            GROUP BY
                d.id,
                d.group_id,
                d.subject,
                d.created_at,
                d.updated_at,
                g.name,
                u.first_name,
                u.last_name,
                first_post.content
            ORDER BY d.updated_at DESC, d.id DESC
            LIMIT {$limit}"
        );
        $statement->execute([$userId]);

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

    public function existsInGroup(int $groupId, int $discussionId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM discussions WHERE id = ? AND group_id = ?');
        $statement->execute([$discussionId, $groupId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function addReply(int $groupId, int $discussionId, int $userId, string $content): int
    {
        $this->pdo->beginTransaction();

        try {
            $post = $this->pdo->prepare(
                'INSERT INTO posts (discussion_id, created_by, content) VALUES (?, ?, ?)'
            );
            $post->execute([$discussionId, $userId, $content]);
            $postId = (int) $this->pdo->lastInsertId();

            $discussion = $this->pdo->prepare(
                'UPDATE discussions SET updated_at = CURRENT_TIMESTAMP WHERE id = ? AND group_id = ?'
            );
            $discussion->execute([$discussionId, $groupId]);

            $this->pdo->commit();

            return $postId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
