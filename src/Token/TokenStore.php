<?php

declare(strict_types=1);

namespace ContactWarden\Token;

interface TokenStore
{
    /** @param array<string,string> $fieldMap */
    public function storeToken(string $tokenId, array $fieldMap, \DateTimeImmutable $issuedAt, \DateTimeImmutable $expiresAt, string $ip): void;

    /** Looks up, validates expiry, and marks the token consumed in one step (single-use). */
    public function consumeToken(string $tokenId): TokenConsumptionResult;
}
