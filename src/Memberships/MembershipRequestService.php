<?php

declare(strict_types=1);

namespace Samtli\Memberships;

use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;

final class MembershipRequestService
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly MembershipRepository $memberships
    ) {
    }

    public function request(int $groupId, int $userId): MembershipRequestResult
    {
        if (!$this->groups->exists($groupId)) {
            return MembershipRequestResult::failure('Group not found.');
        }

        if ($this->memberships->isMember($groupId, $userId)) {
            return MembershipRequestResult::failure('You are already a member of this group.');
        }

        if ($this->memberships->pendingRequestExists($groupId, $userId)) {
            return MembershipRequestResult::failure('You already have a pending request for this group.');
        }

        $this->memberships->createJoinRequest($groupId, $userId);

        return MembershipRequestResult::success();
    }
}
