<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Memberships\MembershipApprovalService;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class GroupAdminController
{
    public function __construct(
        private readonly MembershipApprovalService $approvals,
        private readonly MembershipRepository $memberships,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
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
}
