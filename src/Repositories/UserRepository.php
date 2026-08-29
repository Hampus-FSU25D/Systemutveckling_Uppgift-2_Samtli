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

    /**
     * @return array{id: int|string, email: string, password_hash: string}|null
     */
    public function findForAuthentication(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
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
