<?php

declare(strict_types=1);

namespace Samtli\Memberships;

use Samtli\Repositories\MembershipRepository;

final class MembershipApprovalService
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    public function approve(int $groupId, int $requestId, int $administratorId): MembershipApprovalResult
    {
        if (!$this->memberships->isAdministrator($groupId, $administratorId)) {
            return MembershipApprovalResult::failure('Only group administrators can approve membership requests.');
        }

        if (!$this->memberships->approvePendingJoinRequest($groupId, $requestId, $administratorId)) {
            return MembershipApprovalResult::failure('Pending request not found.');
        }

        return MembershipApprovalResult::success();
    }
}
