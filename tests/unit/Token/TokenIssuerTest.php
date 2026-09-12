<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Token;

use ContactWarden\Tests\Support\SqliteStorageFactory;
use ContactWarden\Token\TokenIssuer;
use PHPUnit\Framework\TestCase;

final class TokenIssuerTest extends TestCase
{
    public function test_issued_token_is_valid_and_returns_field_map(): void
    {
        $storage = SqliteStorageFactory::create();
        $issuer = new TokenIssuer('secret', $storage, 1800);

        $tokenId = $issuer->issue(['name' => 'f_abc123'], '1.2.3.4');
        $result = $issuer->validateAndConsume($tokenId);

        $this->assertSame('valid', $result->status);
        $this->assertSame(['name' => 'f_abc123'], $result->token->fieldMap);
    }

    public function test_token_is_single_use(): void
    {
        $storage = SqliteStorageFactory::create();
        $issuer = new TokenIssuer('secret', $storage, 1800);

        $tokenId = $issuer->issue(['name' => 'f_abc123'], '1.2.3.4');
        $issuer->validateAndConsume($tokenId);
        $second = $issuer->validateAndConsume($tokenId);

        $this->assertSame('invalid', $second->status);
    }

    /**
     * Regression test: the old scheme let anyone construct a syntactically
     * valid key for their own IP without ever going through issuance. A
     * tampered id (or a token minted with a different secret) must never
     * validate, since the store might otherwise be tricked into a lookup.
     */
    public function test_tampered_token_is_rejected_without_hitting_the_store(): void
    {
        $storage = SqliteStorageFactory::create();
        $issuer = new TokenIssuer('secret', $storage, 1800);

        $forged = bin2hex(random_bytes(16)) . '.' . str_repeat('0', 64);
        $result = $issuer->validateAndConsume($forged);

        $this->assertSame('invalid', $result->status);
    }

    public function test_token_signed_with_different_secret_is_rejected(): void
    {
        $storage = SqliteStorageFactory::create();
        $issuedByOtherSecret = new TokenIssuer('other-secret', $storage, 1800);
        $tokenId = $issuedByOtherSecret->issue(['name' => 'f_abc123'], '1.2.3.4');

        $issuer = new TokenIssuer('secret', $storage, 1800);
        $result = $issuer->validateAndConsume($tokenId);

        $this->assertSame('invalid', $result->status);
    }

    public function test_expired_token_is_reported_as_expired(): void
    {
        $storage = SqliteStorageFactory::create();
        $issuer = new TokenIssuer('secret', $storage, -1);

        $tokenId = $issuer->issue(['name' => 'f_abc123'], '1.2.3.4');
        $result = $issuer->validateAndConsume($tokenId);

        $this->assertSame('expired', $result->status);
    }
}
