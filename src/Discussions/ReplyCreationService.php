<?php

declare(strict_types=1);

namespace Samtli\Discussions;

use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\MembershipRepository;

final class ReplyCreationService
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly DiscussionRepository $discussions
    ) {
    }

    /**
     * @param array<string, string> $input
     */
    public function create(int $groupId, int $discussionId, int $userId, array $input): ReplyCreationResult
    {
        if (!$this->memberships->isMember($groupId, $userId)) {
            return ReplyCreationResult::failure([
                '_form' => ['You must be a group member to reply.'],
            ]);
        }

        if (!$this->discussions->existsInGroup($groupId, $discussionId)) {
            return ReplyCreationResult::failure([
                '_form' => ['Discussion not found.'],
            ]);
        }

        $content = trim($input['content'] ?? '');

        if ($content === '') {
            return ReplyCreationResult::failure([
                'content' => ['Write a reply before posting.'],
            ]);
        }

        return ReplyCreationResult::success(
            $this->discussions->addReply($groupId, $discussionId, $userId, $content)
        );
    }
}
