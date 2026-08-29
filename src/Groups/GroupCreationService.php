<?php

declare(strict_types=1);

namespace Samtli\Groups;

use Samtli\Repositories\GroupRepository;
use Throwable;

final class GroupCreationService
{
    private const NAME_MAX_LENGTH = 120;

    public function __construct(private readonly GroupRepository $groups)
    {
    }

    /**
     * @param array<string, string> $input
     */
    public function create(array $input, int $creatorId): GroupCreationResult
    {
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        $errors = $this->validate($name, $description);

        if ($errors !== []) {
            return GroupCreationResult::validationFailed($errors);
        }

        try {
            return GroupCreationResult::success($this->groups->createWithAdministrator($name, $description, $creatorId));
        } catch (Throwable $exception) {
            error_log('Group creation failed: ' . $exception->getMessage());

            return GroupCreationResult::validationFailed([
                '_form' => ['We could not create the group right now. Please try again.'],
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function validate(string $name, string $description): array
    {
        $errors = [];

        if ($name === '') {
            $errors['name'][] = 'Group name is required.';
        } elseif ($this->length($name) > self::NAME_MAX_LENGTH) {
            $errors['name'][] = 'Group name must be 120 characters or fewer.';
        }

        if ($description === '') {
            $errors['description'][] = 'Description is required.';
        }

        return $errors;
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
