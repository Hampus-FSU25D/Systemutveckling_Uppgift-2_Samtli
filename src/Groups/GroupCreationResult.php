<?php

declare(strict_types=1);

namespace Samtli\Groups;

final class GroupCreationResult
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    private function __construct(
        private readonly bool $success,
        private readonly array $fieldErrors = [],
        private readonly ?int $groupId = null
    ) {
    }

    public static function success(int $groupId): self
    {
        return new self(true, [], $groupId);
    }

    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public static function validationFailed(array $fieldErrors): self
    {
        return new self(false, $fieldErrors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function groupId(): ?int
    {
        return $this->groupId;
    }
}
