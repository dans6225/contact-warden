<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\RefererSignal;
use PHPUnit\Framework\TestCase;

final class RefererSignalTest extends TestCase
{
    public function test_fires_missing_weight_when_no_referer(): void
    {
        $signal = new RefererSignal('example.com', 10, 15);
        $context = new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());

        $this->assertSame(10, $signal->evaluate($context)->score);
    }

    public function test_fires_mismatch_weight_when_host_differs(): void
    {
        $signal = new RefererSignal('example.com', 10, 15);
        $context = new RequestContext('1.2.3.4', ['REFERER' => 'https://evil.example/x'], [], new \DateTimeImmutable());

        $this->assertSame(15, $signal->evaluate($context)->score);
    }

    public function test_silent_when_host_matches(): void
    {
        $signal = new RefererSignal('example.com', 10, 15);
        $context = new RequestContext('1.2.3.4', ['REFERER' => 'https://example.com/contact'], [], new \DateTimeImmutable());

        $this->assertSame(0, $signal->evaluate($context)->score);
    }

    public function test_silent_when_no_expected_host_configured(): void
    {
        $signal = new RefererSignal(null, 10, 15);
        $context = new RequestContext('1.2.3.4', ['REFERER' => 'https://anything.example/x'], [], new \DateTimeImmutable());

        $this->assertSame(0, $signal->evaluate($context)->score);
    }
}
