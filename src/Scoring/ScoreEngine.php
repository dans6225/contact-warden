<?php

declare(strict_types=1);

namespace ContactWarden\Scoring;

use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\Signals\SignalResult;

final class ScoreEngine
{
    public function __construct(private readonly ContactWardenConfig $config)
    {
    }

    /** @param SignalResult[] $results */
    public function decide(array $results): Decision
    {
        $total = array_sum(array_map(static fn (SignalResult $r) => $r->score, $results));

        $result = match (true) {
            $total >= $this->config->rejectThreshold => Decision::REJECT,
            $total >= $this->config->challengeThreshold => Decision::CHALLENGE,
            default => Decision::ACCEPT,
        };

        return new Decision($result, $total, $results);
    }
}
