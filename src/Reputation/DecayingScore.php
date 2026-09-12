<?php

declare(strict_types=1);

namespace ContactWarden\Reputation;

/**
 * Exponential half-life decay so a stale bad score recovers over time and a
 * shared IP (CGNAT, corporate egress) isn't permanently poisoned by one
 * abuser.
 */
final class DecayingScore
{
    public function __construct(private readonly int $halfLifeSeconds)
    {
    }

    public function decay(float $score, \DateTimeImmutable $lastUpdated, \DateTimeImmutable $now): float
    {
        if ($score <= 0.0) {
            return 0.0;
        }

        $elapsedSeconds = max(0, $now->getTimestamp() - $lastUpdated->getTimestamp());

        return $score * (0.5 ** ($elapsedSeconds / $this->halfLifeSeconds));
    }
}
