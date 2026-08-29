<?php

declare(strict_types=1);

namespace Samtli\View;

use RuntimeException;

final class TemplateRenderer
{
    public function __construct(private readonly string $templatePath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = rtrim($this->templatePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
