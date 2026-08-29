<?php

declare(strict_types=1);

use Samtli\Database\Connection;
use Samtli\Database\Migrator;

require __DIR__ . '/../vendor/autoload.php';

$migrationsPath = dirname(__DIR__) . '/database/migrations';

try {
    $migrator = new Migrator(Connection::fromEnvironment(), $migrationsPath);
    $result = $migrator->migrate();

    foreach ($result['applied'] as $migration) {
        echo "Applied {$migration}\n";
    }

    if ($result['applied'] === []) {
        echo "No pending migrations.\n";
    }

    echo sprintf(
        "Migrations complete. Applied: %d. Skipped: %d.\n",
        count($result['applied']),
        count($result['skipped'])
    );
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
