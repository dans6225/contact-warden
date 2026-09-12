<?php

declare(strict_types=1);

namespace ContactWarden\Token;

final class ConsumedToken
{
    /** @param array<string,string> $fieldMap */
    public function __construct(
        public readonly array $fieldMap,
        public readonly \DateTimeImmutable $issuedAt,
    ) {
    }
}
