<?php

declare(strict_types=1);

namespace Samtli\Auth;

use Samtli\Repositories\DuplicateEmailException;
use Samtli\Repositories\UserRepository;
use Throwable;

final class RegistrationService
{
    private const NAME_MAX_LENGTH = 100;
    private const EMAIL_MAX_LENGTH = 254;
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param array<string, string> $input
     */
    public function register(array $input): RegistrationResult
    {
        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = $this->normalizeEmail($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $passwordConfirmation = $input['password_confirmation'] ?? '';

        $errors = $this->validate($firstName, $lastName, $email, $password, $passwordConfirmation);

        if ($errors !== []) {
            return RegistrationResult::validationFailed($errors);
        }

        if ($this->users->emailExists($email)) {
            return RegistrationResult::validationFailed([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        try {
            $userId = $this->users->create($firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (DuplicateEmailException) {
            return RegistrationResult::validationFailed([
                'email' => ['An account with this email already exists.'],
            ]);
        } catch (Throwable $exception) {
            error_log('Registration failed: ' . $exception->getMessage());

            return RegistrationResult::validationFailed([
                '_form' => ['We could not create your account right now. Please try again.'],
            ]);
        }

        return RegistrationResult::success($userId);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @return array<string, list<string>>
     */
    private function validate(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $passwordConfirmation
    ): array {
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

        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        } elseif (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors['password'][] = 'Password must be at least 8 characters long.';
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'][] = 'Password confirmation is required.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
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
