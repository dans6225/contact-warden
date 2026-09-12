<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/**
 * Advisory only: the interaction count is client-reported telemetry, not
 * server-verified, so it stays a low weight and only fires when the client
 * actively reported zero interactions (JS ran but nothing was typed) — a
 * no-JS submission (nothing reported at all) is not penalized here.
 */
final class InteractionSignal implements SignalInterface
{
    public function __construct(private readonly int $weight)
    {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $count = $context->attributes['reported_interaction_count'] ?? null;

        if ($count === 0) {
            return new SignalResult('interaction', $this->weight, ['reported_count' => 0]);
        }

        return new SignalResult('interaction', 0);
    }
}
