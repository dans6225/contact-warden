<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/**
 * Reads the token status the Engine already determined (signature, expiry,
 * and single-use are all checked once, during token consumption). Missing
 * stays below REJECT alone by default so a no-JS user — who never got a
 * token — is challenged, not blocked.
 */
final class TokenValiditySignal implements SignalInterface
{
    public function __construct(
        private readonly int $missingWeight,
        private readonly int $invalidWeight,
        private readonly int $expiredWeight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $status = $context->attributes['token_status'] ?? 'missing';

        return match ($status) {
            'valid' => new SignalResult('token_validity', 0),
            'expired' => new SignalResult('token_validity', $this->expiredWeight, ['status' => 'expired']),
            'invalid' => new SignalResult('token_validity', $this->invalidWeight, ['status' => 'invalid']),
            default => new SignalResult('token_validity', $this->missingWeight, ['status' => 'missing']),
        };
    }
}
