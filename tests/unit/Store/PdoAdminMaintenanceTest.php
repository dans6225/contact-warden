<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Store;

use ContactWarden\Store\PdoAdminMaintenance;
use ContactWarden\Store\PdoStorage;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class PdoAdminMaintenanceTest extends TestCase
{
    public function test_purge_expired_tokens_removes_expired_and_consumed_only(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $maintenance = new PdoAdminMaintenance($pdo);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        // consumeToken() checks expiry against the real wall clock, not $now, so the
        // "consumed" token's expiry must be genuinely in the future to be marked
        // consumed rather than bounced back as already-expired.
        $farFuture = (new \DateTimeImmutable())->modify('+1 year');

        $storage->storeToken('active', [], $now, $now->modify('+10 minutes'), '1.1.1.1');
        $storage->storeToken('expired', [], $now, $now->modify('-10 minutes'), '1.1.1.1');
        $storage->storeToken('consumed', [], $now, $farFuture, '1.1.1.1');
        $storage->consumeToken('consumed');

        $removed = $maintenance->purgeExpiredTokens($now);

        $this->assertSame(2, $removed);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM cw_tokens')->fetchColumn());
    }

    public function test_purge_submissions_older_than_cutoff(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $maintenance = new PdoAdminMaintenance($pdo);

        $storage->logSubmission('1.1.1.1', 'ACCEPT', 0, [], new \DateTimeImmutable('2026-01-01 00:00:00'));
        $storage->logSubmission('1.1.1.1', 'ACCEPT', 0, [], new \DateTimeImmutable('2026-01-10 00:00:00'));

        $removed = $maintenance->purgeSubmissionsOlderThan(new \DateTimeImmutable('2026-01-05 00:00:00'));

        $this->assertSame(1, $removed);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM cw_submissions')->fetchColumn());
    }

    public function test_purge_abuse_events_older_than_cutoff(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $maintenance = new PdoAdminMaintenance($pdo);

        $storage->logAbuse('1.1.1.1', 'REJECT', 100, [], new \DateTimeImmutable('2026-01-01 00:00:00'));
        $storage->logAbuse('1.1.1.1', 'REJECT', 100, [], new \DateTimeImmutable('2026-01-10 00:00:00'));

        $removed = $maintenance->purgeAbuseEventsOlderThan(new \DateTimeImmutable('2026-01-05 00:00:00'));

        $this->assertSame(1, $removed);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM cw_abuse_log')->fetchColumn());
    }

    public function test_forget_reputation_removes_single_subject(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $maintenance = new PdoAdminMaintenance($pdo);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->setReputation('1.1.1.1', 50.0, $now);
        $storage->setReputation('2.2.2.2', 30.0, $now);

        $maintenance->forgetReputation('1.1.1.1');

        $this->assertNull($storage->getReputation('1.1.1.1'));
        $this->assertNotNull($storage->getReputation('2.2.2.2'));
    }

    public function test_reset_all_reputation_clears_every_subject(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $maintenance = new PdoAdminMaintenance($pdo);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->setReputation('1.1.1.1', 50.0, $now);
        $storage->setReputation('2.2.2.2', 30.0, $now);

        $maintenance->resetAllReputation();

        $this->assertNull($storage->getReputation('1.1.1.1'));
        $this->assertNull($storage->getReputation('2.2.2.2'));
    }
}
