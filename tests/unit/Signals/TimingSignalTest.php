<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\TimingSignal;
use PHPUnit\Framework\TestCase;

final class TimingSignalTest extends TestCase
{
    public function test_fires_when_submitted_too_fast(): void
    {
        $signal = new TimingSignal(2, 40);
        $receivedAt = new \DateTimeImmutable('2026-01-01 12:00:01');
        $context = (new RequestContext('1.2.3.4', [], [], $receivedAt))
            ->withAttributes(['form_started_at' => new \DateTimeImmutable('2026-01-01 12:00:00')]);

        $result = $signal->evaluate($context);

        $this->assertSame(40, $result->score);
    }

    public function test_silent_when_elapsed_time_is_sufficient(): void
    {
        $signal = new TimingSignal(2, 40);
        $receivedAt = new \DateTimeImmutable('2026-01-01 12:00:05');
        $context = (new RequestContext('1.2.3.4', [], [], $receivedAt))
            ->withAttributes(['form_started_at' => new \DateTimeImmutable('2026-01-01 12:00:00')]);

        $result = $signal->evaluate($context);

        $this->assertSame(0, $result->score);
    }

    public function test_silent_when_form_started_at_unknown(): void
    {
        $signal = new TimingSignal(2, 40);
        $context = new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());

        $result = $signal->evaluate($context);

        $this->assertSame(0, $result->score);
    }
}
