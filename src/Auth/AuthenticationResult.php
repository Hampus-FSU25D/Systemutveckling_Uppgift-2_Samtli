<?php

declare(strict_types=1);

namespace Samtli\Auth;

final class AuthenticationResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?int $userId = null,
        private readonly ?string $formError = null
    ) {
    }

    public static function success(int $userId): self
    {
        return new self(true, $userId);
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
}
