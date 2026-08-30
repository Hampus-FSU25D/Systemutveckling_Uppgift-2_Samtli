<?php

declare(strict_types=1);

namespace Samtli\Invitations;

final class InvitationCreationResult
{
    private function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly ?string $token = null
    ) {
    }

    public static function success(string $token, string $message = 'Invitation link created.'): self
    {
        return new self(true, $message, $token);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function token(): ?string
    {
        return $this->token;
    }
}
