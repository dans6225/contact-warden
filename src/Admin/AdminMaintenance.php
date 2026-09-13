<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

/**
 * Write-side admin housekeeping (purge/forget/reset) against the same
 * tables AdminDataSource reads. Kept as its own interface rather than
 * folded into AdminDataSource so that one can stay purely read-only, and
 * rather than added to StorageInterface since Engine itself never deletes
 * rows — these operations only ever run from an admin UI's maintenance
 * controls.
 */
interface AdminMaintenance
{
    /** @return int rows removed */
    public function purgeExpiredTokens(\DateTimeImmutable $now): int;

    /** @return int rows removed */
    public function purgeSubmissionsOlderThan(\DateTimeImmutable $cutoff): int;

    /** @return int rows removed */
    public function purgeAbuseEventsOlderThan(\DateTimeImmutable $cutoff): int;

    public function forgetReputation(string $subject): void;

    public function resetAllReputation(): void;
}
