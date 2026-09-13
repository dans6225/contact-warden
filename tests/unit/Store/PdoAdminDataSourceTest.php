<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Store;

use ContactWarden\Admin\AbuseQuery;
use ContactWarden\Admin\ReputationQuery;
use ContactWarden\Admin\SubmissionQuery;
use ContactWarden\Store\PdoAdminDataSource;
use ContactWarden\Store\PdoStorage;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class PdoAdminDataSourceTest extends TestCase
{
    public function test_list_and_count_submissions_filters_by_decision_and_ip(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);
        $at = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->logSubmission('1.1.1.1', 'ACCEPT', 0, [], $at);
        $storage->logSubmission('1.1.1.1', 'REJECT', 100, ['honeypot' => true], $at->modify('+1 minute'));
        $storage->logSubmission('2.2.2.2', 'REJECT', 90, [], $at->modify('+2 minutes'));

        $rejected = $admin->listSubmissions(new SubmissionQuery(decision: 'REJECT'));
        $this->assertCount(2, $rejected);
        $this->assertSame(2, $admin->countSubmissions(new SubmissionQuery(decision: 'REJECT')));

        $fromFirstIp = $admin->listSubmissions(new SubmissionQuery(ip: '1.1.1.1'));
        $this->assertCount(2, $fromFirstIp);

        // Most recent first.
        $this->assertSame('2.2.2.2', $rejected[0]->ip);
        $this->assertSame(['honeypot' => true], $rejected[1]->meta);
    }

    public function test_get_submission_returns_record_or_null(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);

        $storage->logSubmission('1.1.1.1', 'REJECT', 100, ['honeypot' => true], new \DateTimeImmutable('2026-01-01 00:00:00'));

        $found = $admin->listSubmissions(new SubmissionQuery())[0];
        $record = $admin->getSubmission($found->id);

        $this->assertNotNull($record);
        $this->assertSame('1.1.1.1', $record->ip);
        $this->assertSame(['honeypot' => true], $record->meta);

        $this->assertNull($admin->getSubmission(999999));
    }

    public function test_submission_date_range_filters(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);

        $storage->logSubmission('9.9.9.9', 'ACCEPT', 0, [], new \DateTimeImmutable('2026-01-01 00:00:00'));
        $storage->logSubmission('9.9.9.9', 'ACCEPT', 0, [], new \DateTimeImmutable('2026-01-05 00:00:00'));
        $storage->logSubmission('9.9.9.9', 'ACCEPT', 0, [], new \DateTimeImmutable('2026-01-10 00:00:00'));

        $inRange = $admin->listSubmissions(new SubmissionQuery(
            since: new \DateTimeImmutable('2026-01-02 00:00:00'),
            until: new \DateTimeImmutable('2026-01-09 00:00:00'),
        ));

        $this->assertCount(1, $inRange);
        $this->assertSame('2026-01-05 00:00:00', $inRange[0]->createdAt->format('Y-m-d H:i:s'));
    }

    public function test_submissions_pagination(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);
        $at = new \DateTimeImmutable('2026-01-01 00:00:00');

        for ($i = 0; $i < 5; $i++) {
            $storage->logSubmission('9.9.9.9', 'ACCEPT', $i, [], $at->modify("+{$i} minutes"));
        }

        $page = $admin->listSubmissions(new SubmissionQuery(limit: 2, offset: 2));

        $this->assertCount(2, $page);
        $this->assertSame(5, $admin->countSubmissions(new SubmissionQuery()));
        // Newest-first ordering: offset 2 skips scores 4 and 3.
        $this->assertSame(2, $page[0]->score);
        $this->assertSame(1, $page[1]->score);
    }

    public function test_list_and_count_abuse_events_with_evidence(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);
        $at = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->logAbuse('9.9.9.9', 'REJECT', 100, ['honeypot' => true], $at);
        $storage->logAbuse('8.8.8.8', 'CHALLENGE', 40, ['timing' => 'too_fast'], $at->modify('+1 minute'));

        $rejects = $admin->listAbuseEvents(new AbuseQuery(decision: 'REJECT'));
        $this->assertCount(1, $rejects);
        $this->assertSame(['honeypot' => true], $rejects[0]->evidence);
        $this->assertSame(1, $admin->countAbuseEvents(new AbuseQuery(decision: 'REJECT')));
        $this->assertSame(2, $admin->countAbuseEvents(new AbuseQuery()));
    }

    public function test_list_reputations_ordered_by_score_descending_with_min_score_filter(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $storage = new PdoStorage($pdo);
        $admin = new PdoAdminDataSource($pdo);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $storage->setReputation('1.1.1.1', 10.0, $now);
        $storage->setReputation('2.2.2.2', 90.0, $now);
        $storage->setReputation('3.3.3.3', 50.0, $now);

        $all = $admin->listReputations(new ReputationQuery());
        $this->assertSame(['2.2.2.2', '3.3.3.3', '1.1.1.1'], array_map(static fn ($e) => $e->subject, $all));

        $worstOffenders = $admin->listReputations(new ReputationQuery(minScore: 50.0));
        $this->assertCount(2, $worstOffenders);
        $this->assertSame(90.0, $worstOffenders[0]->record->score);
    }
}
