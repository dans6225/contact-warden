<?php

declare(strict_types=1);

namespace ContactWarden\Token;

/**
 * HMAC-signs the random token id so a token can only have been minted by a
 * server that knows the secret — replaces the old scheme where a
 * syntactically valid key could be constructed offline for any IP. Expiry
 * and single-use are enforced by the TokenStore (server-authoritative), not
 * by anything embedded in the token itself.
 */
final class TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly TokenStore $store,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    /** @param array<string,string> $fieldMap */
    public function issue(array $fieldMap, string $ip): string
    {
        $issuedAt = new \DateTimeImmutable();
        $expiresAt = $issuedAt->modify(sprintf('%+d seconds', $this->ttlSeconds));
        $id = bin2hex(random_bytes(16));
        $tokenId = $id . '.' . $this->sign($id);

        $this->store->storeToken($tokenId, $fieldMap, $issuedAt, $expiresAt, $ip);

        return $tokenId;
    }

    public function validateAndConsume(string $tokenId): TokenConsumptionResult
    {
        $parts = explode('.', $tokenId, 2);
        if (count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return TokenConsumptionResult::invalid();
        }

        return $this->store->consumeToken($tokenId);
    }

    private function sign(string $id): string
    {
        return hash_hmac('sha256', $id, $this->secret);
    }
}
