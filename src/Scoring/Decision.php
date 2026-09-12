<?php

declare(strict_types=1);

namespace ContactWarden\Scoring;

use ContactWarden\Signals\SignalResult;

final class Decision
{
    public const ACCEPT = 'ACCEPT';
    public const CHALLENGE = 'CHALLENGE';
    public const REJECT = 'REJECT';

    /** @param SignalResult[] $evidence */
    public function __construct(
        public readonly string $result,
        public readonly int $score,
        public readonly array $evidence,
    ) {
    }

    public function isAccept(): bool
    {
        return $this->result === self::ACCEPT;
    }

    public function isChallenge(): bool
    {
        return $this->result === self::CHALLENGE;
    }

    public function isReject(): bool
    {
        return $this->result === self::REJECT;
    }
}
