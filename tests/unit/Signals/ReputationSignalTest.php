<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Reputation\DecayingScore;
use ContactWarden\Reputation\StorageBackedReputationStore;
use ContactWarden\Signals\ReputationSignal;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class ReputationSignalTest extends TestCase
{
    public function test_contribution_capped_below_raw_score(): void
    {
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        $storage->setReputation('1.2.3.4', 90.0, $now);

        $reputationStore = new StorageBackedReputationStore($storage, new DecayingScore(604800));
        $signal = new ReputationSignal($reputationStore, 50);
        $context = new RequestContext('1.2.3.4', [], [], $now);

        $this->assertSame(50, $signal->evaluate($context)->score);
    }

    public function test_zero_for_unknown_subject(): void
    {
        $storage = SqliteStorageFactory::create();
        $reputationStore = new StorageBackedReputationStore($storage, new DecayingScore(604800));
        $signal = new ReputationSignal($reputationStore, 50);
        $context = new RequestContext('9.9.9.9', [], [], new \DateTimeImmutable());

        $this->assertSame(0, $signal->evaluate($context)->score);
    }
}
