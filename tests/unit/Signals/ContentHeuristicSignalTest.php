<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\ContentHeuristicSignal;
use PHPUnit\Framework\TestCase;

final class ContentHeuristicSignalTest extends TestCase
{
    public function test_silent_on_clean_message(): void
    {
        $signal = new ContentHeuristicSignal(['viagra', 'casino'], 3, 30);
        $context = new RequestContext('1.2.3.4', [], ['message' => 'Hi, I would like a quote please.'], new \DateTimeImmutable());

        $this->assertSame(0, $signal->evaluate($context)->score);
    }

    public function test_scores_keyword_hits(): void
    {
        $signal = new ContentHeuristicSignal(['viagra', 'casino'], 3, 30);
        $context = new RequestContext('1.2.3.4', [], ['message' => 'buy viagra and try our casino'], new \DateTimeImmutable());

        $this->assertSame(16, $signal->evaluate($context)->score);
    }

    public function test_scores_excess_links(): void
    {
        $signal = new ContentHeuristicSignal([], 3, 30);
        $body = ['message' => 'http://a.example http://b.example http://c.example http://d.example'];
        $context = new RequestContext('1.2.3.4', [], $body, new \DateTimeImmutable());

        $this->assertSame(10, $signal->evaluate($context)->score);
    }

    public function test_caps_at_max_weight(): void
    {
        $signal = new ContentHeuristicSignal(['viagra', 'casino', 'forex', 'crypto airdrop'], 3, 30);
        $body = ['message' => 'viagra casino forex crypto airdrop http://a.example http://b.example http://c.example http://d.example http://e.example'];
        $context = new RequestContext('1.2.3.4', [], $body, new \DateTimeImmutable());

        $this->assertSame(30, $signal->evaluate($context)->score);
    }
}
