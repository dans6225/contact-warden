<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Reputation\ReputationRecord;
use ContactWarden\Token\TokenStore;

interface StorageInterface extends TokenStore
{
    /**
     * @param array<string,mixed> $meta
     */
    public function logSubmission(string $ip, string $decision, int $score, array $meta, \DateTimeImmutable $at): void;

    /**
     * @param array<string,mixed> $evidence
     */
    public function logAbuse(string $ip, string $decision, int $score, array $evidence, \DateTimeImmutable $at): void;

    public function getReputation(string $subject): ?ReputationRecord;

    public function setReputation(string $subject, float $score, \DateTimeImmutable $lastUpdated): void;

    /** Count of submissions from $ip at or after $since — backs the sliding-window rate-limit signal. */
    public function countRecentSubmissions(string $ip, \DateTimeImmutable $since): int;
}
