<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$dockerfile = file_get_contents($root . '/Dockerfile');
$entrypointPath = $root . '/docker/entrypoint.sh';
$attributes = file_get_contents($root . '/.gitattributes');

assertTrue($dockerfile !== false, 'Dockerfile can be read');
assertTrue(str_contains($dockerfile, 'samtli-entrypoint'), 'Dockerfile installs Samtli startup entrypoint');
assertTrue(str_contains($dockerfile, 'CMD ["apache2-foreground"]'), 'Dockerfile starts Apache through explicit default command');
assertTrue(is_file($entrypointPath), 'Docker startup entrypoint exists');

$entrypoint = file_get_contents($entrypointPath);
assertTrue($entrypoint !== false, 'Docker startup entrypoint can be read');
assertTrue(!str_contains($entrypoint, "\r\n"), 'Docker startup entrypoint uses Unix line endings');
assertTrue(str_contains($entrypoint, 'php bin/migrate.php'), 'Docker startup entrypoint runs database migrations');
assertTrue(str_contains($entrypoint, 'MIGRATE_ON_START'), 'Docker startup entrypoint allows migration opt-out');
assertTrue(str_contains($entrypoint, 'exec docker-php-entrypoint "$@"'), 'Docker startup entrypoint delegates to the base image entrypoint');
assertTrue($attributes !== false && str_contains($attributes, '*.sh text eol=lf'), 'Git preserves shell scripts with Unix line endings');

echo "Docker startup verification passed.\n";

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}
