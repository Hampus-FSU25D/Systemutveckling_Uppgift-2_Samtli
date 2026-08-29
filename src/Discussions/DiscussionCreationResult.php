<?php

declare(strict_types=1);

namespace Samtli\Discussions;

final class DiscussionCreationResult
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    private function __construct(
        private readonly bool $success,
        private readonly ?int $discussionId = null,
        private readonly array $fieldErrors = []
    ) {
    }

    public static function success(int $discussionId): self
    {
        return new self(true, $discussionId);
    }

    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public static function failure(array $fieldErrors): self
    {
        return new self(false, null, $fieldErrors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function discussionId(): ?int
    {
        return $this->discussionId;
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
