<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Admin\AdminMaintenance;

final class PdoAdminMaintenance implements AdminMaintenance
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function purgeExpiredTokens(\DateTimeImmutable $now): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM cw_tokens WHERE expires_at < :now OR consumed_at IS NOT NULL');
        $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    public function purgeSubmissionsOlderThan(\DateTimeImmutable $cutoff): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM cw_submissions WHERE created_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    public function purgeAbuseEventsOlderThan(\DateTimeImmutable $cutoff): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM cw_abuse_log WHERE created_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    public function forgetReputation(string $subject): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM cw_reputation WHERE subject = :subject');
        $stmt->execute(['subject' => $subject]);
    }

    public function resetAllReputation(): void
    {
        // DELETE, not TRUNCATE: SQLite has no TRUNCATE, and the test suite is SQLite-only.
        $this->pdo->exec('DELETE FROM cw_reputation');
    }
}
