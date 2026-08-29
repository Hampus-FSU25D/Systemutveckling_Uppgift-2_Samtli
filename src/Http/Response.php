<?php

declare(strict_types=1);

namespace Samtli\Http;

final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $statusCode = 200
    ) {
    }

    public function send(): never
    {
        http_response_code($this->statusCode);
        echo $this->body;
        exit;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
