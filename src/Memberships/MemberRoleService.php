<?php

declare(strict_types=1);

namespace Samtli\Memberships;

use Samtli\Repositories\MembershipRepository;

final class MemberRoleService
{
    private const VALID_ROLES = ['member', 'administrator'];

    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    public function changeRole(int $groupId, int $targetUserId, int $administratorId, string $role): MemberRoleResult
    {
        if (!$this->memberships->isAdministrator($groupId, $administratorId)) {
            return MemberRoleResult::failure('Only group administrators can change member roles.');
        }

        if ($targetUserId === $administratorId) {
            return MemberRoleResult::failure('Administrators cannot change their own role.');
        }

        if (!in_array($role, self::VALID_ROLES, true)) {
            return MemberRoleResult::failure('Choose a valid role.');
        }

        if (!$this->memberships->memberExists($groupId, $targetUserId)) {
            return MemberRoleResult::failure('Member not found.');
        }

        $this->memberships->changeMemberRole($groupId, $targetUserId, $role);

        return MemberRoleResult::success();
    }
}
