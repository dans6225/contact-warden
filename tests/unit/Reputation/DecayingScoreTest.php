<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Reputation;

use ContactWarden\Reputation\DecayingScore;
use PHPUnit\Framework\TestCase;

final class DecayingScoreTest extends TestCase
{
    public function test_score_is_unchanged_with_no_elapsed_time(): void
    {
        $decay = new DecayingScore(3600);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');

        $this->assertSame(50.0, $decay->decay(50.0, $now, $now));
    }

    public function test_score_halves_after_one_half_life(): void
    {
        $decay = new DecayingScore(3600);
        $lastUpdated = new \DateTimeImmutable('2026-01-01 00:00:00');
        $now = $lastUpdated->modify('+3600 seconds');

        $this->assertEqualsWithDelta(25.0, $decay->decay(50.0, $lastUpdated, $now), 0.001);
    }

    public function test_score_quarters_after_two_half_lives(): void
    {
        $decay = new DecayingScore(3600);
        $lastUpdated = new \DateTimeImmutable('2026-01-01 00:00:00');
        $now = $lastUpdated->modify('+7200 seconds');

        $this->assertEqualsWithDelta(12.5, $decay->decay(50.0, $lastUpdated, $now), 0.001);
    }

    public function test_zero_score_stays_zero(): void
    {
        $decay = new DecayingScore(3600);
        $lastUpdated = new \DateTimeImmutable('2026-01-01 00:00:00');
        $now = $lastUpdated->modify('+1000000 seconds');

        $this->assertSame(0.0, $decay->decay(0.0, $lastUpdated, $now));
    }
}
