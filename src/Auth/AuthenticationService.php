<?php

declare(strict_types=1);

namespace Samtli\Auth;

use Samtli\Repositories\UserRepository;

final class AuthenticationService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param array<string, string> $input
     */
    public function authenticate(array $input): AuthenticationResult
    {
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return AuthenticationResult::failed();
        }

        $user = $this->users->findForAuthentication($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return AuthenticationResult::failed();
        }

        return AuthenticationResult::success((int) $user['id']);
    }
}
