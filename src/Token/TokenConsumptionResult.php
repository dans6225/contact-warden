<?php

declare(strict_types=1);

namespace ContactWarden\Token;

final class TokenConsumptionResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?ConsumedToken $token,
    ) {
    }

    public static function valid(ConsumedToken $token): self
    {
        return new self('valid', $token);
    }

    public static function invalid(): self
    {
        return new self('invalid', null);
    }

    public static function expired(): self
    {
        return new self('expired', null);
    }
}
