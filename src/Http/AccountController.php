<?php

declare(strict_types=1);

namespace Samtli\Http;

use DateTimeImmutable;
use Samtli\Repositories\DuplicateEmailException;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;
use Throwable;

final class AccountController
{
    private const NAME_MAX_LENGTH = 100;
    private const EMAIL_MAX_LENGTH = 254;

    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionAuthenticator $authenticator,
        private readonly CsrfTokenManager $csrf,
        private readonly TemplateRenderer $templates
    ) {
    }

    public function show(?string $success = null, ?string $error = null): Response|RedirectResponse
    {
        $user = $this->currentUser();

        if ($user === null) {
            return new RedirectResponse('/login');
        }

        return new Response($this->renderForm($user, [], $success, $error));
    }

    /**
     * @param array<string, string> $post
     */
    public function update(array $post): Response|RedirectResponse
    {
        $user = $this->currentUser();

        if ($user === null) {
            return new RedirectResponse('/login');
        }

        if (!$this->csrf->isValid('account.update', $post['_csrf'] ?? '')) {
            return new Response($this->renderForm($this->mergeOld($user, $post), ['_form' => ['Your session expired. Please submit the form again.']]), 419);
        }

        $firstName = trim($post['first_name'] ?? '');
        $lastName = trim($post['last_name'] ?? '');
        $email = strtolower(trim($post['email'] ?? ''));
        $errors = $this->validate($firstName, $lastName, $email);

        if ($errors !== []) {
            return new Response($this->renderForm($this->mergeOld($user, $post), $errors), 422);
        }

        if ($this->users->emailExistsForAnotherUser($email, (int) $user['id'])) {
            return new Response($this->renderForm($this->mergeOld($user, $post), [
                'email' => ['An account with this email already exists.'],
            ]), 422);
        }

        try {
            $this->users->updateProfile((int) $user['id'], $firstName, $lastName, $email);
        } catch (DuplicateEmailException) {
            return new Response($this->renderForm($this->mergeOld($user, $post), [
                'email' => ['An account with this email already exists.'],
            ]), 422);
        } catch (Throwable $exception) {
            error_log('Account update failed: ' . $exception->getMessage());

            return new Response($this->renderForm($this->mergeOld($user, $post), [
                '_form' => ['We could not update your account right now. Please try again.'],
            ]), 422);
        }

        $this->authenticator->updateUserSnapshot([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        $_SESSION['_flash']['success'] = 'Account updated.';

        return new RedirectResponse('/account');
    }

    /**
     * @param array<string, string> $post
     */
    public function logout(array $post): Response|RedirectResponse
    {
        if (!$this->authenticator->check()) {
            return new RedirectResponse('/login');
        }

        if (!$this->csrf->isValid('logout', $post['_csrf'] ?? '')) {
            $user = $this->currentUser();

            return new Response($this->renderForm($user, ['_form' => ['Your session expired. Please submit the form again.']]), 419);
        }

        $this->authenticator->logout();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return new RedirectResponse('/');
    }

    /**
     * @return array{id: int|string, first_name: string, last_name: string, email: string, created_at: string}|null
     */
    private function currentUser(): ?array
    {
        $userId = $this->authenticator->id();

        return $userId === null ? null : $this->users->findPublicById($userId);
    }

    /**
     * @param array{id: int|string, first_name: string, last_name: string, email: string, created_at: string}|null $user
     * @param array<string, list<string>> $errors
     */
    private function renderForm(?array $user, array $errors = [], ?string $success = null, ?string $error = null): string
    {
        return $this->templates->render('account/show', [
            'title' => 'Account settings',
            'user' => $user,
            'memberSince' => $this->memberSince((string) ($user['created_at'] ?? '')),
            'csrfToken' => $this->csrf->token('account.update'),
            'logoutToken' => $this->csrf->token('logout'),
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    private function memberSince(string $createdAt): string
    {
        if ($createdAt === '') {
            return 'Unknown';
        }

        return (new DateTimeImmutable($createdAt))->format('j F Y');
    }

    /**
     * @param array{id: int|string, first_name: string, last_name: string, email: string, created_at: string} $user
     * @param array<string, string> $post
     * @return array{id: int|string, first_name: string, last_name: string, email: string, created_at: string}
     */
    private function mergeOld(array $user, array $post): array
    {
        return [
            ...$user,
            'first_name' => trim($post['first_name'] ?? (string) $user['first_name']),
            'last_name' => trim($post['last_name'] ?? (string) $user['last_name']),
            'email' => strtolower(trim($post['email'] ?? (string) $user['email'])),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function validate(string $firstName, string $lastName, string $email): array
    {
        $errors = [];

        if ($firstName === '') {
            $errors['first_name'][] = 'First name is required.';
        } elseif ($this->length($firstName) > self::NAME_MAX_LENGTH) {
            $errors['first_name'][] = 'First name must be 100 characters or fewer.';
        }

        if ($lastName === '') {
            $errors['last_name'][] = 'Last name is required.';
        } elseif ($this->length($lastName) > self::NAME_MAX_LENGTH) {
            $errors['last_name'][] = 'Last name must be 100 characters or fewer.';
        }

        if ($email === '') {
            $errors['email'][] = 'Email address is required.';
        } elseif (strlen($email) > self::EMAIL_MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Enter a valid email address.';
        }

        return $errors;
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
