<?php

declare(strict_types=1);

namespace Samtli\Http;

final class RedirectResponse
{
    public function __construct(private readonly string $location)
    {
    }

    public function send(): never
    {
        header('Location: ' . $this->location, true, 303);
        exit;
    }

    public function location(): string
    {
        return $this->location;
    }
}
