<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Groups\GroupCreationService;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class GroupController
{
    public function __construct(
        private readonly GroupCreationService $groups,
        private readonly MembershipRequestService $membershipRequests,
        private readonly GroupRepository $groupRepository,
        private readonly MembershipRepository $memberships,
        private readonly DiscussionRepository $discussions,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function index(?string $successMessage = null, ?string $errorMessage = null): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        return new Response($this->templates->render('groups/index', [
            'title' => 'Discover groups',
            'csrfToken' => $this->csrf->token('groups.join_request'),
            'groups' => $this->groupRepository->discoverableForUser($userId),
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
        ]));
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
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        $group = $this->groupRepository->find($groupId);

        if ($group === null) {
            return new Response($this->templates->render('home', [
                'title' => 'Page not found',
                'authenticatedUserId' => $userId,
            ]), 404);
        }

        if (!$this->memberships->isMember($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        return new Response($this->templates->render('groups/show', [
            'title' => (string) $group['name'],
            'groupId' => $groupId,
            'group' => $group,
            'discussions' => $this->discussions->forGroup($groupId),
            'isAdministrator' => $this->memberships->isAdministrator($groupId, $userId),
        ]));
    }

    /**
     * @param array<string, string> $post
     */
    public function requestMembership(int $groupId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->csrf->isValid('groups.join_request', $post['_csrf'] ?? '')) {
            $response = $this->index(null, 'Your session expired. Please submit the request again.');

            return $response instanceof Response
                ? new Response($response->body(), 419)
                : new Response('', 419);
        }

        $result = $this->membershipRequests->request($groupId, $userId);

        if (!$result->isSuccess()) {
            return $this->index(null, $result->message());
        }

        $_SESSION['_flash']['success'] = $result->message();

        return new RedirectResponse('/groups');
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
