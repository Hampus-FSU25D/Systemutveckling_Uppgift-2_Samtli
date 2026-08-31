<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Auth\AuthenticationService;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class LoginController
{
    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function show(?string $flash = null): Response
    {
        return new Response($this->renderForm(flash: $flash));
    }

    /**
     * @param array<string, string> $post
     */
    public function store(array $post): Response|RedirectResponse
    {
        if (!$this->csrf->isValid('login', $post['_csrf'] ?? '')) {
            return new Response($this->renderForm(
                'Your session expired. Please submit the form again.',
                $this->oldInput($post)
            ), 419);
        }

        $result = $this->authentication->authenticate($post);

        if (!$result->isSuccess() || $result->userId() === null) {
            return new Response($this->renderForm(
                $result->formError(),
                $this->oldInput($post)
            ), 422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->authenticator->login($result->userId(), $result->user());

        return new RedirectResponse('/');
    }

    /**
     * @param array<string, string> $old
     */
    private function renderForm(?string $error = null, array $old = [], ?string $flash = null): string
    {
        return $this->templates->render('auth/login', [
            'title' => 'Log in',
            'csrfToken' => $this->csrf->token('login'),
            'error' => $error,
            'flash' => $flash,
            'old' => $old,
        ]);
    }

    /**
     * @param array<string, string> $post
     * @return array<string, string>
     */
    private function oldInput(array $post): array
    {
        return [
            'email' => trim($post['email'] ?? ''),
        ];
    }
}
