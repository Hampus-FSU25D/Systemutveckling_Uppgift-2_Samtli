<?php

declare(strict_types=1);

namespace Samtli\Discussions;

use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\MembershipRepository;

final class DiscussionCreationService
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly DiscussionRepository $discussions
    ) {
    }

    /**
     * @param array<string, string> $input
     */
    public function create(int $groupId, int $userId, array $input): DiscussionCreationResult
    {
        if (!$this->memberships->isMember($groupId, $userId)) {
            return DiscussionCreationResult::failure([
                '_form' => ['You must be a group member to start a discussion.'],
            ]);
        }

        $subject = trim($input['subject'] ?? '');
        $content = trim($input['content'] ?? '');
        $errors = [];

        if ($subject === '') {
            $errors['subject'][] = 'Enter a discussion subject.';
        } elseif (strlen($subject) > 180) {
            $errors['subject'][] = 'Discussion subject may not be longer than 180 characters.';
        }

        if ($content === '') {
            $errors['content'][] = 'Write the first post.';
        }

        if ($errors !== []) {
            return DiscussionCreationResult::failure($errors);
        }

        return DiscussionCreationResult::success(
            $this->discussions->createWithFirstPost($groupId, $userId, $subject, $content)
        );
    }
}
