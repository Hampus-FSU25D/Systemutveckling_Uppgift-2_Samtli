<?php

declare(strict_types=1);

namespace Samtli\Repositories;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);

        return $statement->fetchColumn() !== false;
    }

    public function emailExistsForAnotherUser(string $email, int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $statement->execute([$email, $userId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array{id: int|string, first_name: string, last_name: string, email: string, password_hash: string}|null
     */
    public function findForAuthentication(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, first_name, last_name, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array{id: int|string, first_name: string, last_name: string, email: string, created_at: string}|null
     */
    public function findPublicById(int $userId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, first_name, last_name, email, created_at FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateProfile(int $userId, string $firstName, string $lastName, string $email): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?'
        );

        try {
            $statement->execute([$firstName, $lastName, $email, $userId]);
        } catch (PDOException $exception) {
            if ($this->isDuplicateEmailViolation($exception)) {
                throw new DuplicateEmailException('Email address already exists.', 0, $exception);
            }

            throw $exception;
        }
    }

    public function create(string $firstName, string $lastName, string $email, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)'
        );

        try {
            $statement->execute([$firstName, $lastName, $email, $passwordHash]);
        } catch (PDOException $exception) {
            if ($this->isDuplicateEmailViolation($exception)) {
                throw new DuplicateEmailException('Email address already exists.', 0, $exception);
            }

            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function isDuplicateEmailViolation(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? null) === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062
            && str_contains((string) ($errorInfo[2] ?? ''), 'uq_users_email');
    }
}
