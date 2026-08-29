<?php

declare(strict_types=1);

namespace Samtli\Database;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    private const LOCK_NAME = 'samtli_schema_migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsPath
    ) {
    }

    /**
     * @return array{applied: list<string>, skipped: list<string>}
     */
    public function migrate(): array
    {
        $this->acquireLock();

        try {
            $this->createMigrationTable();

            $applied = [];
            $skipped = [];
            $alreadyApplied = $this->appliedMigrations();

            foreach ($this->migrationFiles() as $file) {
                $migration = basename($file);

                if (isset($alreadyApplied[$migration])) {
                    $skipped[] = $migration;
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException("Could not read migration {$migration}.");
                }

                try {
                    $this->pdo->exec($sql);
                    $this->recordMigration($migration);
                    $applied[] = $migration;
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Migration {$migration} failed: {$exception->getMessage()}",
                        (int) $exception->getCode(),
                        $exception
                    );
                }
            }

            return [
                'applied' => $applied,
                'skipped' => $skipped,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    private function createMigrationTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(190) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<string, true>
     */
    private function appliedMigrations(): array
    {
        $statement = $this->pdo->query('SELECT migration FROM schema_migrations');
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        $applied = [];
        foreach ($rows as $migration) {
            $applied[(string) $migration] = true;
        }

        return $applied;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            throw new RuntimeException("Migrations directory does not exist: {$this->migrationsPath}");
        }

        $files = glob(rtrim($this->migrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            throw new RuntimeException("Could not read migrations directory: {$this->migrationsPath}");
        }

        sort($files, SORT_STRING);

        return array_values($files);
    }

    private function recordMigration(string $migration): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO schema_migrations (migration, applied_at) VALUES (?, CURRENT_TIMESTAMP)'
        );
        $statement->execute([$migration]);
    }

    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $statement->execute([self::LOCK_NAME]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire schema migration lock.');
        }
    }

    private function releaseLock(): void
    {
        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([self::LOCK_NAME]);
    }
}
