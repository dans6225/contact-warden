<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Store;

use ContactWarden\Http\RequestContext;
use ContactWarden\Store\PdoContactRecordStore;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class PdoContactRecordStoreTest extends TestCase
{
    public function test_records_submission_content(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $store = new PdoContactRecordStore($pdo);

        $context = new RequestContext('9.9.9.9', [], [], new \DateTimeImmutable('2026-01-01 12:00:00'));
        $store->record(['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi'], $context);

        $row = $pdo->query('SELECT created_at, ip, fields FROM cw_contacts')->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('2026-01-01 12:00:00', $row['created_at']);
        $this->assertSame('9.9.9.9', $row['ip']);
        $this->assertSame(
            ['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi'],
            json_decode($row['fields'], true),
        );
    }

    public function test_records_multiple_submissions_independently(): void
    {
        $pdo = SqliteStorageFactory::createPdo();
        $store = new PdoContactRecordStore($pdo);
        $context = new RequestContext('1.1.1.1', [], [], new \DateTimeImmutable());

        $store->record(['name' => 'One'], $context);
        $store->record(['name' => 'Two'], $context);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM cw_contacts')->fetchColumn();
        $this->assertSame(2, $count);
    }
}
