<?php

declare(strict_types=1);

namespace Samtli\Auth;

final class AuthenticationResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?int $userId = null,
        private readonly ?string $formError = null,
        /** @var array{first_name: string, last_name: string, email: string}|null */
        private readonly ?array $user = null
    ) {
    }

    /**
     * @param array{first_name: string, last_name: string, email: string} $user
     */
    public static function success(int $userId, array $user): self
    {
        return new self(true, $userId, null, $user);
    }

    public static function failed(): self
    {
        return new self(false, null, 'The email or password you entered is incorrect. Please try again.');
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function formError(): ?string
    {
        return $this->formError;
    }

    /**
     * @return array{first_name: string, last_name: string, email: string}|null
     */
    public function user(): ?array
    {
        return $this->user;
    }
}
