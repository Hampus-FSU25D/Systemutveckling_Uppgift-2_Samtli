<?php

declare(strict_types=1);

namespace Samtli\Invitations;

use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\InvitationRepository;
use Samtli\Repositories\MembershipRepository;

final class InvitationCreationService
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly MembershipRepository $memberships,
        private readonly InvitationRepository $invitations
    ) {
    }

    public function create(int $groupId, int $administratorId): InvitationCreationResult
    {
        if (!$this->groups->exists($groupId)) {
            return InvitationCreationResult::failure('Group not found.');
        }

        if (!$this->memberships->isAdministrator($groupId, $administratorId)) {
            return InvitationCreationResult::failure('Only group administrators can create invitations.');
        }

        $token = bin2hex(random_bytes(32));
        $this->invitations->create($groupId, $administratorId, $token);

        return InvitationCreationResult::success($token);
    }

    /**
     * @return list<array{id: int|string, expires_at: string, used_at: string|null, used_by: int|string|null, created_at: string}>
     */
    public function recentForGroup(int $groupId): array
    {
        return $this->invitations->recentForGroup($groupId);
    }
}
