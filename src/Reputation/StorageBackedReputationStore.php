<?php

declare(strict_types=1);

namespace ContactWarden\Reputation;

use ContactWarden\Store\StorageInterface;

final class StorageBackedReputationStore implements ReputationStore
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly DecayingScore $decay,
    ) {
    }

    public function getDecayedScore(string $subject, \DateTimeImmutable $now): float
    {
        $record = $this->storage->getReputation($subject);

        if ($record === null) {
            return 0.0;
        }

        return $this->decay->decay($record->score, $record->lastUpdated, $now);
    }

    public function penalize(string $subject, float $amount, \DateTimeImmutable $now): void
    {
        $current = $this->getDecayedScore($subject, $now);
        $this->storage->setReputation($subject, $current + $amount, $now);
    }
}
