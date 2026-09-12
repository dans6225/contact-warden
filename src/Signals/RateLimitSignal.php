<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Store\StorageInterface;

/** Sliding-window count of recent submissions from the same IP, backed by cw_submissions. */
final class RateLimitSignal implements SignalInterface
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly int $maxSubmissions,
        private readonly int $windowSeconds,
        private readonly int $weight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $since = $context->receivedAt->modify(sprintf('-%d seconds', $this->windowSeconds));
        $count = $this->storage->countRecentSubmissions($context->ip, $since);

        if ($count >= $this->maxSubmissions) {
            return new SignalResult('rate_limit', $this->weight, ['recent_count' => $count]);
        }

        return new SignalResult('rate_limit', 0);
    }
}
