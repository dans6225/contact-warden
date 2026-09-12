<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Store;

use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class PdoStorageTest extends TestCase
{
    public function test_reputation_round_trips_and_updates(): void
    {
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $this->assertNull($storage->getReputation('1.2.3.4'));

        $storage->setReputation('1.2.3.4', 30.0, $now);
        $record = $storage->getReputation('1.2.3.4');
        $this->assertSame(30.0, $record->score);

        $storage->setReputation('1.2.3.4', 45.0, $now);
        $updated = $storage->getReputation('1.2.3.4');
        $this->assertSame(45.0, $updated->score);
    }

    public function test_reputation_update_to_same_score_still_persists(): void
    {
        // Regression: a naive "UPDATE, then INSERT if 0 rows affected" upsert
        // can misfire when the new value equals the old one (MySQL reports 0
        // rows changed), wrongly attempting a duplicate INSERT.
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->setReputation('1.2.3.4', 20.0, $now);
        $storage->setReputation('1.2.3.4', 20.0, $now->modify('+1 hour'));

        $record = $storage->getReputation('1.2.3.4');
        $this->assertSame(20.0, $record->score);
        $this->assertSame('2026-01-01 01:00:00', $record->lastUpdated->format('Y-m-d H:i:s'));
    }

    public function test_count_recent_submissions(): void
    {
        $storage = SqliteStorageFactory::create();
        $old = new \DateTimeImmutable('2026-01-01 00:00:00');
        $recent = new \DateTimeImmutable('2026-01-01 00:09:00');
        $since = new \DateTimeImmutable('2026-01-01 00:05:00');

        $storage->logSubmission('9.9.9.9', 'ACCEPT', 0, [], $old);
        $storage->logSubmission('9.9.9.9', 'ACCEPT', 0, [], $recent);
        $storage->logSubmission('1.1.1.1', 'ACCEPT', 0, [], $recent);

        $this->assertSame(1, $storage->countRecentSubmissions('9.9.9.9', $since));
    }

    public function test_log_abuse_persists_evidence(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new \ContactWarden\Store\PdoStorage($pdo);

        $storage->logAbuse('9.9.9.9', 'REJECT', 100, ['honeypot' => true], new \DateTimeImmutable('2026-01-01 00:00:00'));

        $row = $pdo->query('SELECT ip, score, decision, evidence FROM cw_abuse_log')->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('9.9.9.9', $row['ip']);
        $this->assertSame(100, (int) $row['score']);
        $this->assertSame('REJECT', $row['decision']);
        $this->assertSame(['honeypot' => true], json_decode($row['evidence'], true));
    }
}
