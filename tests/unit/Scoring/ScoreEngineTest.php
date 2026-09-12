<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Scoring;

use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\Scoring\Decision;
use ContactWarden\Scoring\ScoreEngine;
use ContactWarden\Signals\SignalResult;
use PHPUnit\Framework\TestCase;

final class ScoreEngineTest extends TestCase
{
    public function test_sums_signal_scores(): void
    {
        $engine = new ScoreEngine(ContactWardenConfig::default());

        $decision = $engine->decide([
            new SignalResult('a', 5),
            new SignalResult('b', 10),
        ]);

        $this->assertSame(15, $decision->score);
    }

    public function test_below_challenge_threshold_accepts(): void
    {
        $config = new ContactWardenConfig(challengeThreshold: 30, rejectThreshold: 70);
        $engine = new ScoreEngine($config);

        $decision = $engine->decide([new SignalResult('a', 29)]);

        $this->assertSame(Decision::ACCEPT, $decision->result);
    }

    public function test_at_challenge_threshold_challenges(): void
    {
        $config = new ContactWardenConfig(challengeThreshold: 30, rejectThreshold: 70);
        $engine = new ScoreEngine($config);

        $decision = $engine->decide([new SignalResult('a', 30)]);

        $this->assertSame(Decision::CHALLENGE, $decision->result);
    }

    public function test_at_reject_threshold_rejects(): void
    {
        $config = new ContactWardenConfig(challengeThreshold: 30, rejectThreshold: 70);
        $engine = new ScoreEngine($config);

        $decision = $engine->decide([new SignalResult('a', 70)]);

        $this->assertSame(Decision::REJECT, $decision->result);
    }

    public function test_no_signals_accepts_with_zero_score(): void
    {
        $engine = new ScoreEngine(ContactWardenConfig::default());

        $decision = $engine->decide([]);

        $this->assertSame(Decision::ACCEPT, $decision->result);
        $this->assertSame(0, $decision->score);
    }
}
