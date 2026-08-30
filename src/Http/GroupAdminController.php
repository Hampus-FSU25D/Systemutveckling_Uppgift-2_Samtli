<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Invitations\InvitationCreationService;
use Samtli\Memberships\MembershipApprovalService;
use Samtli\Memberships\MemberRoleService;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class GroupAdminController
{
    public function __construct(
        private readonly MembershipApprovalService $approvals,
        private readonly MemberRoleService $roles,
        private readonly InvitationCreationService $invitations,
        private readonly MembershipRepository $memberships,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function members(int $groupId, ?string $successMessage = null, ?string $errorMessage = null): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        return new Response($this->templates->render('groups/members', [
            'title' => 'Members',
            'groupId' => $groupId,
            'currentUserId' => $userId,
            'csrfToken' => $this->csrf->token('admin.member_roles'),
            'members' => $this->memberships->membersForGroup($groupId),
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
        ]));
    }

    public function invitations(int $groupId, ?string $inviteUrl = null, ?string $successMessage = null, ?string $errorMessage = null): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        return new Response($this->templates->render('groups/invitations', [
            'title' => 'Invitations',
            'groupId' => $groupId,
            'csrfToken' => $this->csrf->token('admin.invitations'),
            'inviteUrl' => $inviteUrl,
            'invitations' => $this->invitations->recentForGroup($groupId),
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
        ]));
    }

    public function joinRequests(int $groupId, ?string $successMessage = null, ?string $errorMessage = null): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        return new Response($this->templates->render('groups/join_requests', [
            'title' => 'Pending requests',
            'groupId' => $groupId,
            'csrfToken' => $this->csrf->token('admin.join_requests'),
            'requests' => $this->memberships->pendingJoinRequestsForGroup($groupId),
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
        ]));
    }

    /**
     * @param array<string, string> $post
     */
    public function approveJoinRequest(int $groupId, int $requestId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        if (!$this->csrf->isValid('admin.join_requests', $post['_csrf'] ?? '')) {
            $response = $this->joinRequests($groupId, null, 'Your session expired. Please approve the request again.');

            return $response instanceof Response
                ? new Response($response->body(), 419)
                : new Response('', 419);
        }

        $result = $this->approvals->approve($groupId, $requestId, $userId);

        if (!$result->isSuccess()) {
            return $this->joinRequests($groupId, null, $result->message());
        }

        $_SESSION['_flash']['success'] = $result->message();

        return new RedirectResponse("/groups/{$groupId}/admin/join-requests");
    }

    /**
     * @param array<string, string> $post
     */
    public function createInvitation(int $groupId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        if (!$this->csrf->isValid('admin.invitations', $post['_csrf'] ?? '')) {
            $response = $this->invitations($groupId, null, null, 'Your session expired. Please create the invitation again.');

            return $response instanceof Response
                ? new Response($response->body(), 419)
                : new Response('', 419);
        }

        $result = $this->invitations->create($groupId, $userId);

        if (!$result->isSuccess() || $result->token() === null) {
            return $this->invitations($groupId, null, null, $result->message());
        }

        $_SESSION['_flash']['success'] = $result->message();
        $_SESSION['_flash']['invite_url'] = $this->absoluteUrl('/invitations/' . $result->token());

        return new RedirectResponse("/groups/{$groupId}/admin/invitations");
    }

    /**
     * @param array<string, string> $post
     */
    public function updateMemberRole(int $groupId, int $memberUserId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isAdministrator($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        if (!$this->csrf->isValid('admin.member_roles', $post['_csrf'] ?? '')) {
            $response = $this->members($groupId, null, 'Your session expired. Please update the role again.');

            return $response instanceof Response
                ? new Response($response->body(), 419)
                : new Response('', 419);
        }

        $result = $this->roles->changeRole($groupId, $memberUserId, $userId, $post['role'] ?? '');

        if (!$result->isSuccess()) {
            return $this->members($groupId, null, $result->message());
        }

        $_SESSION['_flash']['success'] = $result->message();

        return new RedirectResponse("/groups/{$groupId}/admin/members");
    }

    private function absoluteUrl(string $path): string
    {
        $appUrl = rtrim((string) getenv('APP_URL'), '/');

        if ($appUrl !== '') {
            return $appUrl . $path;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return "{$scheme}://{$host}{$path}";
    }
}
