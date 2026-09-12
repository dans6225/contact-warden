<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\HoneypotSignal;
use PHPUnit\Framework\TestCase;

final class HoneypotSignalTest extends TestCase
{
    public function test_fires_when_honeypot_filled(): void
    {
        $signal = new HoneypotSignal('website', 100);
        $context = new RequestContext('1.2.3.4', [], ['website' => 'http://spam.example'], new \DateTimeImmutable());

        $result = $signal->evaluate($context);

        $this->assertSame(100, $result->score);
    }

    public function test_silent_when_honeypot_empty(): void
    {
        $signal = new HoneypotSignal('website', 100);
        $context = new RequestContext('1.2.3.4', [], ['website' => ''], new \DateTimeImmutable());

        $result = $signal->evaluate($context);

        $this->assertSame(0, $result->score);
    }

    public function test_silent_when_honeypot_field_absent(): void
    {
        $signal = new HoneypotSignal('website', 100);
        $context = new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());

        $result = $signal->evaluate($context);

        $this->assertSame(0, $result->score);
    }
}
