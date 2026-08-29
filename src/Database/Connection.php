<?php

declare(strict_types=1);

namespace Samtli\Database;

use PDO;

final class Connection
{
    public static function fromEnvironment(): PDO
    {
        $host = self::env('DB_HOST', 'db');
        $port = self::env('DB_PORT', '3306');
        $database = self::env('DB_DATABASE', 'samtli');
        $username = self::env('DB_USERNAME', 'samtli');
        $password = self::env('DB_PASSWORD', '');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return $pdo;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}
