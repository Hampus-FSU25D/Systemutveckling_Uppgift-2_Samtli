<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Invitations\InvitationAcceptanceService;
use Samtli\Repositories\InvitationRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class InvitationController
{
    public function __construct(
        private readonly InvitationAcceptanceService $acceptance,
        private readonly InvitationRepository $invitations,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function show(string $token, ?string $errorMessage = null): Response
    {
        $invitation = $this->invitations->findAvailableByToken($token);
        $userId = $this->authenticator->id();

        if ($invitation === null) {
            return new Response($this->templates->render('invitations/unavailable', [
                'title' => 'Invitation unavailable',
                'authenticatedUserId' => $userId,
            ]), 410);
        }

        return new Response($this->templates->render('invitations/show', [
            'title' => 'Invitation',
            'token' => $token,
            'group' => $invitation,
            'csrfToken' => $this->csrf->token('invitations.accept'),
            'authenticatedUserId' => $userId,
            'errorMessage' => $errorMessage,
        ]));
    }

    /**
     * @param array<string, string> $post
     */
    public function accept(string $token, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->csrf->isValid('invitations.accept', $post['_csrf'] ?? '')) {
            $response = $this->show($token, 'Your session expired. Please accept the invitation again.');

            return new Response($response->body(), 419);
        }

        $result = $this->acceptance->accept($token, $userId);

        if (!$result->isSuccess()) {
            $status = $result->message() === 'You are already a member of this group.' ? 200 : 410;

            return new Response($this->templates->render('invitations/unavailable', [
                'title' => 'Invitation unavailable',
                'authenticatedUserId' => $userId,
                'message' => $result->message(),
            ]), $status);
        }

        $_SESSION['_flash']['success'] = $result->message();

        return new RedirectResponse('/groups/' . $result->groupId());
    }
}
