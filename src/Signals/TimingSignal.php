<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/**
 * Server-authoritative elapsed-time check: `form_started_at` comes from the
 * token record the server itself wrote at issuance, never from anything the
 * client echoes back, so it can't be spoofed by a client claiming to have
 * waited longer than it did.
 */
final class TimingSignal implements SignalInterface
{
    public function __construct(
        private readonly int $minFormFillSeconds,
        private readonly int $weight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $formStartedAt = $context->attributes['form_started_at'] ?? null;
        if (!$formStartedAt instanceof \DateTimeImmutable) {
            return new SignalResult('timing', 0);
        }

        $elapsedSeconds = $context->receivedAt->getTimestamp() - $formStartedAt->getTimestamp();

        if ($elapsedSeconds < $this->minFormFillSeconds) {
            return new SignalResult('timing', $this->weight, ['elapsed_seconds' => $elapsedSeconds]);
        }

        return new SignalResult('timing', 0);
    }
}
