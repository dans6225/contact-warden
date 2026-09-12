<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/** Soft signal — a real user can suppress referer (privacy settings, direct POST), so it never carries much weight alone. */
final class RefererSignal implements SignalInterface
{
    public function __construct(
        private readonly ?string $expectedHost,
        private readonly int $missingWeight,
        private readonly int $mismatchWeight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $referer = $context->headers['REFERER'] ?? '';

        if ($referer === '') {
            return new SignalResult('referer', $this->missingWeight, ['reason' => 'missing']);
        }

        if ($this->expectedHost === null) {
            return new SignalResult('referer', 0);
        }

        $host = (string) (parse_url($referer, PHP_URL_HOST) ?: '');
        if (strcasecmp($host, $this->expectedHost) !== 0) {
            return new SignalResult('referer', $this->mismatchWeight, ['host' => $host]);
        }

        return new SignalResult('referer', 0);
    }
}
