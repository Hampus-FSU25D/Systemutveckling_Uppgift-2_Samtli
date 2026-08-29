<?php

declare(strict_types=1);

namespace Samtli\Memberships;

final class MembershipRequestResult
{
    private function __construct(
        private readonly bool $success,
        private readonly string $message
    ) {
    }

    public static function success(string $message = 'Membership request sent.'): self
    {
        return new self(true, $message);
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
}
