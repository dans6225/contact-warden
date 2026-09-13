<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

/**
 * Read-only, paginated query access to what the engine already logs
 * (submissions, abuse evidence, reputation) — the data side of an admin UI.
 * Deliberately separate from StorageInterface: that interface is what Engine
 * itself depends on to write, and every StorageInterface implementer
 * (including the test suite's in-memory SQLite backend) has to satisfy it,
 * so listing/pagination for an optional admin UI has no business living
 * there. A host app that doesn't want an admin UI never implements this.
 */
interface AdminDataSource
{
    /** @return SubmissionRecord[] */
    public function listSubmissions(SubmissionQuery $query): array;

    public function countSubmissions(SubmissionQuery $query): int;

    public function getSubmission(int $id): ?SubmissionRecord;

    /** @return AbuseRecord[] */
    public function listAbuseEvents(AbuseQuery $query): array;

    public function countAbuseEvents(AbuseQuery $query): int;

    /** @return ReputationEntry[] */
    public function listReputations(ReputationQuery $query): array;
}
