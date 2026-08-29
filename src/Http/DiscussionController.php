<?php

declare(strict_types=1);

namespace Samtli\Http;

use Samtli\Discussions\DiscussionCreationService;
use Samtli\Discussions\ReplyCreationService;
use Samtli\Repositories\DiscussionRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

final class DiscussionController
{
    public function __construct(
        private readonly DiscussionCreationService $discussionCreation,
        private readonly ReplyCreationService $replyCreation,
        private readonly MembershipRepository $memberships,
        private readonly DiscussionRepository $discussions,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function show(int $groupId, int $discussionId): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isMember($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        $discussion = $this->discussions->findInGroupWithPosts($groupId, $discussionId);

        if ($discussion === null) {
            return new Response($this->templates->render('home', [
                'title' => 'Page not found',
                'authenticatedUserId' => $userId,
            ]), 404);
        }

        return new Response($this->templates->render('discussions/show', [
            'title' => (string) $discussion['subject'],
            'groupId' => $groupId,
            'discussion' => $discussion,
            'replyCsrfToken' => $this->csrf->token('discussions.reply'),
            'replyErrors' => [],
            'replyOld' => [],
        ]));
    }

    public function create(int $groupId): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isMember($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        return new Response($this->renderForm($groupId));
    }

    /**
     * @param array<string, string> $post
     */
    public function store(int $groupId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isMember($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        if (!$this->csrf->isValid('discussions.create', $post['_csrf'] ?? '')) {
            return new Response($this->renderForm(
                $groupId,
                ['_form' => ['Your session expired. Please submit the form again.']],
                $this->oldInput($post)
            ), 419);
        }

        $result = $this->discussionCreation->create($groupId, $userId, $post);

        if (!$result->isSuccess() || $result->discussionId() === null) {
            return new Response($this->renderForm($groupId, $result->fieldErrors(), $this->oldInput($post)), 422);
        }

        return new RedirectResponse("/groups/{$groupId}/discussions/{$result->discussionId()}");
    }

    /**
     * @param array<string, string> $post
     */
    public function storeReply(int $groupId, int $discussionId, array $post): Response|RedirectResponse
    {
        $userId = $this->authenticator->id();

        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->memberships->isMember($groupId, $userId)) {
            return new Response($this->templates->render('home', [
                'title' => 'Forbidden',
                'authenticatedUserId' => $userId,
            ]), 403);
        }

        if (!$this->csrf->isValid('discussions.reply', $post['_csrf'] ?? '')) {
            return new Response($this->renderDiscussion(
                $groupId,
                $discussionId,
                ['_form' => ['Your session expired. Please submit the reply again.']],
                $this->replyOldInput($post)
            ), 419);
        }

        $result = $this->replyCreation->create($groupId, $discussionId, $userId, $post);

        if (!$result->isSuccess()) {
            return new Response($this->renderDiscussion($groupId, $discussionId, $result->fieldErrors(), $this->replyOldInput($post)), 422);
        }

        return new RedirectResponse("/groups/{$groupId}/discussions/{$discussionId}");
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, string> $old
     */
    private function renderForm(int $groupId, array $errors = [], array $old = []): string
    {
        return $this->templates->render('discussions/create', [
            'title' => 'Start discussion',
            'groupId' => $groupId,
            'csrfToken' => $this->csrf->token('discussions.create'),
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
            'subject' => trim($post['subject'] ?? ''),
            'content' => trim($post['content'] ?? ''),
        ];
    }

    /**
     * @param array<string, list<string>> $replyErrors
     * @param array<string, string> $replyOld
     */
    private function renderDiscussion(int $groupId, int $discussionId, array $replyErrors = [], array $replyOld = []): string
    {
        $discussion = $this->discussions->findInGroupWithPosts($groupId, $discussionId);

        if ($discussion === null) {
            return $this->templates->render('home', [
                'title' => 'Page not found',
                'authenticatedUserId' => $this->authenticator->id(),
            ]);
        }

        return $this->templates->render('discussions/show', [
            'title' => (string) $discussion['subject'],
            'groupId' => $groupId,
            'discussion' => $discussion,
            'replyCsrfToken' => $this->csrf->token('discussions.reply'),
            'replyErrors' => $replyErrors,
            'replyOld' => $replyOld,
        ]);
    }

    /**
     * @param array<string, string> $post
     * @return array<string, string>
     */
    private function replyOldInput(array $post): array
    {
        return [
            'content' => trim($post['content'] ?? ''),
        ];
    }
}
