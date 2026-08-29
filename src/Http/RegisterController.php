<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Auth\RegistrationService;
use Samtli\Security\CsrfTokenManager;
use Samtli\View\TemplateRenderer;

final class RegisterController
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function show(): Response
    {
        return new Response($this->renderForm());
    }

    /**
     * @param array<string, string> $post
     */
    public function store(array $post): Response|RedirectResponse
    {
        if (!$this->csrf->isValid('register', $post['_csrf'] ?? '')) {
            return new Response($this->renderForm(
                ['_form' => ['Your session expired. Please submit the form again.']],
                $this->oldInput($post)
            ), 419);
        }

        $result = $this->registration->register($post);

        if (!$result->isSuccess()) {
            return new Response($this->renderForm($result->fieldErrors(), $this->oldInput($post)), 422);
        }

        $_SESSION['_flash']['success'] = 'Account created. You can now log in.';

        return new RedirectResponse('/login');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, string> $old
     */
    private function renderForm(array $errors = [], array $old = []): string
    {
        return $this->templates->render('auth/register', [
            'title' => 'Join Samtli',
            'csrfToken' => $this->csrf->token('register'),
            'errors' => $errors,
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
            'first_name' => trim($post['first_name'] ?? ''),
            'last_name' => trim($post['last_name'] ?? ''),
            'email' => trim($post['email'] ?? ''),
        ];
    }
}
