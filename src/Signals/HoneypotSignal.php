<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/** Fires at full weight if the honeypot field — invisible to humans, catnip to bots that fill every input — has any value. */
final class HoneypotSignal implements SignalInterface
{
    public function __construct(
        private readonly string $fieldName,
        private readonly int $weight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $value = (string) ($context->body[$this->fieldName] ?? '');

        if (trim($value) !== '') {
            return new SignalResult('honeypot', $this->weight, ['field' => $this->fieldName]);
        }

        return new SignalResult('honeypot', 0);
    }
}
