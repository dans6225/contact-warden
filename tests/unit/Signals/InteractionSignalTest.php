<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\InteractionSignal;
use PHPUnit\Framework\TestCase;

final class InteractionSignalTest extends TestCase
{
    public function test_fires_when_client_reported_zero_interactions(): void
    {
        $signal = new InteractionSignal(8);
        $context = (new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable()))
            ->withAttributes(['reported_interaction_count' => 0]);

        $this->assertSame(8, $signal->evaluate($context)->score);
    }

    public function test_silent_when_interactions_reported(): void
    {
        $signal = new InteractionSignal(8);
        $context = (new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable()))
            ->withAttributes(['reported_interaction_count' => 5]);

        $this->assertSame(0, $signal->evaluate($context)->score);
    }

    /** No-JS submissions never report a count at all — must not be penalized. */
    public function test_silent_when_no_count_reported(): void
    {
        $signal = new InteractionSignal(8);
        $context = new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());

        $this->assertSame(0, $signal->evaluate($context)->score);
    }
}
