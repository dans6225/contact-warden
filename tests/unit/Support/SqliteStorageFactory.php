<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Support;

use ContactWarden\Store\PdoStorage;

/**
 * Builds an in-memory SQLite-backed PdoStorage for tests — this is the
 * dependency-free backend the architecture calls for so the suite never
 * touches the real MySQL database. Schema is a portable subset of
 * src/Store/migrations/0001_create_tables.sql (no ENUM/AUTO_INCREMENT/
 * ENGINE clauses, which are MySQL-only).
 */
final class SqliteStorageFactory
{
    public static function create(): PdoStorage
    {
        return new PdoStorage(self::createPdo());
    }

    public static function createPdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE cw_tokens (
            id TEXT PRIMARY KEY,
            field_map TEXT NOT NULL,
            issued_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            consumed_at TEXT,
            ip TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE cw_submissions (
            id INTEGER PRIMARY KEY,
            created_at TEXT NOT NULL,
            ip TEXT NOT NULL,
            decision TEXT NOT NULL,
            score INTEGER NOT NULL,
            meta TEXT
        )');

        $pdo->exec('CREATE TABLE cw_abuse_log (
            id INTEGER PRIMARY KEY,
            created_at TEXT NOT NULL,
            ip TEXT NOT NULL,
            score INTEGER NOT NULL,
            decision TEXT NOT NULL,
            evidence TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE cw_reputation (
            subject TEXT PRIMARY KEY,
            score REAL NOT NULL DEFAULT 0,
            last_updated TEXT NOT NULL
        )');

        return $pdo;
    }
}
