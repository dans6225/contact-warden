<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\TokenValiditySignal;
use PHPUnit\Framework\TestCase;

final class TokenValiditySignalTest extends TestCase
{
    private function context(string $status): RequestContext
    {
        return (new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable()))
            ->withAttributes(['token_status' => $status]);
    }

    public function test_valid_token_scores_zero(): void
    {
        $signal = new TokenValiditySignal(35, 45, 25);

        $this->assertSame(0, $signal->evaluate($this->context('valid'))->score);
    }

    public function test_missing_token_uses_missing_weight(): void
    {
        $signal = new TokenValiditySignal(35, 45, 25);

        $this->assertSame(35, $signal->evaluate($this->context('missing'))->score);
    }

    public function test_invalid_token_uses_invalid_weight(): void
    {
        $signal = new TokenValiditySignal(35, 45, 25);

        $this->assertSame(45, $signal->evaluate($this->context('invalid'))->score);
    }

    public function test_expired_token_uses_expired_weight(): void
    {
        $signal = new TokenValiditySignal(35, 45, 25);

        $this->assertSame(25, $signal->evaluate($this->context('expired'))->score);
    }
}
