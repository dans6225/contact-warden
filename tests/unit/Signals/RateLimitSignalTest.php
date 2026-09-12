<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\RateLimitSignal;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use PHPUnit\Framework\TestCase;

final class RateLimitSignalTest extends TestCase
{
    public function test_silent_below_the_limit(): void
    {
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:10:00');
        $storage->logSubmission('1.2.3.4', 'ACCEPT', 0, [], $now->modify('-1 minute'));

        $signal = new RateLimitSignal($storage, 5, 600, 50);
        $context = new RequestContext('1.2.3.4', [], [], $now);

        $this->assertSame(0, $signal->evaluate($context)->score);
    }

    public function test_fires_at_the_limit(): void
    {
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:10:00');
        for ($i = 0; $i < 5; $i++) {
            $storage->logSubmission('1.2.3.4', 'ACCEPT', 0, [], $now->modify('-1 minute'));
        }

        $signal = new RateLimitSignal($storage, 5, 600, 50);
        $context = new RequestContext('1.2.3.4', [], [], $now);

        $this->assertSame(50, $signal->evaluate($context)->score);
    }

    public function test_ignores_submissions_outside_the_window(): void
    {
        $storage = SqliteStorageFactory::create();
        $now = new \DateTimeImmutable('2026-01-01 00:10:00');
        for ($i = 0; $i < 5; $i++) {
            $storage->logSubmission('1.2.3.4', 'ACCEPT', 0, [], $now->modify('-1 hour'));
        }

        $signal = new RateLimitSignal($storage, 5, 600, 50);
        $context = new RequestContext('1.2.3.4', [], [], $now);

        $this->assertSame(0, $signal->evaluate($context)->score);
    }
}
