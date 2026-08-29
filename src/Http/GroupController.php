<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Groups\GroupCreationService;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class GroupController
{
    public function __construct(
        private readonly GroupCreationService $groups,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function create(): Response|RedirectResponse
    {
        if (!$this->authenticator->check()) {
            return new RedirectResponse('/login');
        }

        return new Response($this->renderCreateForm());
    }

    /**
     * @param array<string, string> $post
     */
    public function store(array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->csrf->isValid('groups.create', $post['_csrf'] ?? '')) {
            return new Response($this->renderCreateForm(
                ['_form' => ['Your session expired. Please submit the form again.']],
                $this->oldInput($post)
            ), 419);
        }

        $result = $this->groups->create($post, $userId);

        if (!$result->isSuccess() || $result->groupId() === null) {
            return new Response($this->renderCreateForm($result->fieldErrors(), $this->oldInput($post)), 422);
        }

        return new RedirectResponse('/groups/' . $result->groupId());
    }

    public function show(int $groupId): Response|RedirectResponse
    {
        if (!$this->authenticator->check()) {
            return new RedirectResponse('/login');
        }

        return new Response($this->templates->render('groups/show', [
            'title' => 'Group created',
            'groupId' => $groupId,
        ]));
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, string> $old
     */
    private function renderCreateForm(array $errors = [], array $old = []): string
    {
        return $this->templates->render('groups/create', [
            'title' => 'Create group',
            'csrfToken' => $this->csrf->token('groups.create'),
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
            'name' => trim($post['name'] ?? ''),
            'description' => trim($post['description'] ?? ''),
        ];
    }
}
