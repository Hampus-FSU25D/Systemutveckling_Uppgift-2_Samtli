<?php

declare(strict_types=1);

namespace Samtli\Security;

final class SessionAuthenticator
{
    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(array &$session)
    {
        $this->session = &$session;
    }

    /**
     * @param array{first_name: string, last_name: string, email: string}|null $user
     */
    public function login(int $userId, ?array $user = null): void
    {
        $this->session['auth_user_id'] = $userId;

        if ($user !== null) {
            $this->session['auth_user'] = $user;
        }
    }

    /**
     * @param array{first_name: string, last_name: string, email: string} $user
     */
    public function updateUserSnapshot(array $user): void
    {
        $this->session['auth_user'] = $user;
    }

    public function logout(): void
    {
        unset($this->session['auth_user_id'], $this->session['auth_user']);
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        $userId = $this->session['auth_user_id'] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    /**
     * @return array{first_name: string, last_name: string, email: string}|null
     */
    public function userSnapshot(): ?array
    {
        $user = $this->session['auth_user'] ?? null;

        if (!is_array($user)) {
            return null;
        }

        $firstName = $user['first_name'] ?? null;
        $lastName = $user['last_name'] ?? null;
        $email = $user['email'] ?? null;

        if (!is_string($firstName) || !is_string($lastName) || !is_string($email)) {
            return null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ];
    }
}
