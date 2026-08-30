<?php

declare(strict_types=1);

namespace Samtli\Invitations;

use Samtli\Repositories\InvitationRepository;
use Samtli\Repositories\MembershipRepository;

final class InvitationAcceptanceService
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly InvitationRepository $invitations
    ) {
    }

    public function accept(string $token, int $userId): InvitationAcceptanceResult
    {
        $invitation = $this->invitations->findAvailableByToken($token);

        if ($invitation === null) {
            return InvitationAcceptanceResult::failure('Invitation is no longer available.');
        }

        $groupId = (int) $invitation['group_id'];

        if ($this->memberships->isMember($groupId, $userId)) {
            return InvitationAcceptanceResult::failure('You are already a member of this group.', $groupId);
        }

        if (!$this->invitations->accept($token, $groupId, $userId)) {
            return InvitationAcceptanceResult::failure('Invitation is no longer available.');
        }

        return InvitationAcceptanceResult::success($groupId);
    }
}
