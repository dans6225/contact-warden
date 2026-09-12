<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

final class SignalResult
{
    /** @param array<string,mixed> $evidence */
    public function __construct(
        public readonly string $name,
        public readonly int $score,
        public readonly array $evidence = [],
    ) {
    }
}
