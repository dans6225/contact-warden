<?php

declare(strict_types=1);

namespace ContactWarden\Reputation;

interface ReputationStore
{
    public function getDecayedScore(string $subject, \DateTimeImmutable $now): float;

    public function penalize(string $subject, float $amount, \DateTimeImmutable $now): void;
}
