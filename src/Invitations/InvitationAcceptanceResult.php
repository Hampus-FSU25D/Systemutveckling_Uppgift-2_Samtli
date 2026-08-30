<?php

declare(strict_types=1);

namespace Samtli\Invitations;

final class InvitationAcceptanceResult
{
    private function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly ?int $groupId = null
    ) {
    }

    public static function success(int $groupId, string $message = 'You joined the group.'): self
    {
        return new self(true, $message, $groupId);
    }

    public static function failure(string $message, ?int $groupId = null): self
    {
        return new self(false, $message, $groupId);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function groupId(): ?int
    {
        return $this->groupId;
    }
}
