<?php

declare(strict_types=1);

namespace ContactWarden\Store;

/**
 * Connection parameters for the default PDO/MySQL storage backend.
 *
 * The library itself never reads environment variables directly (it stays
 * framework-agnostic per design goal #1) — `fromEnv()` is a convenience for
 * apps that do keep credentials in the environment; anything else should
 * just construct this directly or hand `PdoStorage` a PDO instance.
 */
final class DatabaseConfig
{
    public function __construct(
        public readonly string $host,
        public readonly string $database,
        public readonly string $username,
        public readonly string $password,
        public readonly int $port = 3306,
        public readonly string $charset = 'utf8mb4',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST')),
            database: (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE')),
            username: (string) ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME')),
            password: (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD')),
            port: (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
            charset: (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4'),
        );
    }

    public function toDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}
