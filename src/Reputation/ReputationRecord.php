<?php

declare(strict_types=1);

namespace ContactWarden\Reputation;

final class ReputationRecord
{
    public function __construct(
        public readonly float $score,
        public readonly \DateTimeImmutable $lastUpdated,
    ) {
    }
}
