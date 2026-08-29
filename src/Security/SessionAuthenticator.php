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

    public function login(int $userId): void
    {
        $this->session['auth_user_id'] = $userId;
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
}
