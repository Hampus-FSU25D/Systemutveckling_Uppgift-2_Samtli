<?php

declare(strict_types=1);

namespace Samtli\Security;

final class CsrfTokenManager
{
    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(array &$session)
    {
        $this->session = &$session;
    }

    public function token(string $key): string
    {
        if (!isset($this->session['_csrf']) || !is_array($this->session['_csrf'])) {
            $this->session['_csrf'] = [];
        }

        if (!isset($this->session['_csrf'][$key]) || !is_string($this->session['_csrf'][$key])) {
            $this->session['_csrf'][$key] = bin2hex(random_bytes(32));
        }

        return $this->session['_csrf'][$key];
    }

    public function isValid(string $key, string $token): bool
    {
        $expected = $this->session['_csrf'][$key] ?? null;

        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }
}
