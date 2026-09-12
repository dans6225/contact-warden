<?php

declare(strict_types=1);

namespace ContactWarden\Tests;

use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\Engine;
use ContactWarden\FieldObfuscation\FieldMap;
use ContactWarden\Http\RequestContext;
use ContactWarden\Mail\CallbackMailer;
use ContactWarden\Reputation\DecayingScore;
use ContactWarden\Reputation\StorageBackedReputationStore;
use ContactWarden\Scoring\Decision;
use ContactWarden\Scoring\ScoreEngine;
use ContactWarden\Signals\HoneypotSignal;
use ContactWarden\Signals\InteractionSignal;
use ContactWarden\Signals\RateLimitSignal;
use ContactWarden\Signals\RefererSignal;
use ContactWarden\Signals\ReputationSignal;
use ContactWarden\Signals\TimingSignal;
use ContactWarden\Signals\TokenValiditySignal;
use ContactWarden\Signals\UserAgentSignal;
use ContactWarden\Store\PdoStorage;
use ContactWarden\Tests\Support\SqliteStorageFactory;
use ContactWarden\Token\TokenIssuer;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    private function buildEngine(PdoStorage $storage, ContactWardenConfig $config, ?\Closure $onMail = null): array
    {
        $reputation = new StorageBackedReputationStore($storage, new DecayingScore($config->reputationHalfLifeSeconds));
        $tokenIssuer = new TokenIssuer('test-secret', $storage, $config->tokenExpirySeconds);

        $signals = [
            new HoneypotSignal('website', $config->weights['honeypot']),
            new TimingSignal($config->minFormFillSeconds, $config->weights['timing_too_fast']),
            new InteractionSignal($config->weights['interaction_zero']),
            new TokenValiditySignal($config->weights['token_missing'], $config->weights['token_invalid'], $config->weights['token_expired']),
            new RefererSignal('example.com', $config->weights['referer_missing'], $config->weights['referer_mismatch']),
            new UserAgentSignal($config->weights['user_agent_suspicious']),
            new ReputationSignal($reputation, $config->reputationMaxContribution),
            new RateLimitSignal($storage, $config->rateLimitMaxSubmissions, $config->rateLimitWindowSeconds, $config->weights['rate_limit_exceeded']),
        ];

        $engine = new Engine(
            $config,
            $signals,
            new ScoreEngine($config),
            $storage,
            $reputation,
            $tokenIssuer,
            new CallbackMailer($onMail ?? static function (): void {
            }),
        );

        return [$engine, $tokenIssuer];
    }

    private function legitimateHeaders(): array
    {
        return [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'HTTP_REFERER' => 'https://example.com/contact',
        ];
    }

    public function test_honeypot_filled_rejects(): void
    {
        $storage = SqliteStorageFactory::create();
        [$engine] = $this->buildEngine($storage, ContactWardenConfig::default());

        $context = RequestContext::fromGlobals(
            $this->legitimateHeaders(),
            ['name' => 'Bot', 'message' => 'hi', 'website' => 'http://spam.example'],
        );

        $decision = $engine->handle($context);

        $this->assertSame(Decision::REJECT, $decision->result);
    }

    public function test_missing_token_challenges_not_rejects(): void
    {
        // Resolves the architecture doc's open decision: no-JS/no-token
        // submissions must be challenged, never hard-rejected, on their own.
        $storage = SqliteStorageFactory::create();
        [$engine] = $this->buildEngine($storage, ContactWardenConfig::default());

        $context = RequestContext::fromGlobals(
            $this->legitimateHeaders(),
            ['name' => 'Jane', 'message' => 'Hello, just checking in.'],
        );

        $decision = $engine->handle($context);

        $this->assertSame(Decision::CHALLENGE, $decision->result);
    }

    public function test_valid_token_with_realistic_timing_accepts_and_sends_mail(): void
    {
        $storage = SqliteStorageFactory::create();
        $mailed = null;
        [$engine, $tokenIssuer] = $this->buildEngine($storage, ContactWardenConfig::default(), function (array $data) use (&$mailed): void {
            $mailed = $data;
        });

        $fieldMap = FieldMap::generate(['name', 'email', 'message', 'website']);
        $tokenId = $tokenIssuer->issue($fieldMap, '203.0.113.10');

        // Simulate the token having been issued 5 seconds ago (past minFormFillSeconds)
        // without a real sleep(), by re-storing it with a backdated issued_at.
        $this->backdateToken($storage, $tokenId, 5);

        $submitted = [
            '_cw_token' => $tokenId,
            $fieldMap['name'] => 'Real User',
            $fieldMap['email'] => 'real@example.com',
            $fieldMap['message'] => 'Hi, interested in your services.',
            $fieldMap['website'] => '',
        ];

        $decision = $engine->handle(RequestContext::fromGlobals($this->legitimateHeaders(), $submitted));

        $this->assertSame(Decision::ACCEPT, $decision->result);
        $this->assertNotNull($mailed);
        $this->assertSame('Real User', $mailed['name']);
    }

    public function test_replayed_token_is_not_valid_the_second_time(): void
    {
        $storage = SqliteStorageFactory::create();
        [$engine, $tokenIssuer] = $this->buildEngine($storage, ContactWardenConfig::default());

        $fieldMap = FieldMap::generate(['name', 'website']);
        $tokenId = $tokenIssuer->issue($fieldMap, '203.0.113.10');
        $this->backdateToken($storage, $tokenId, 5);

        $body = ['_cw_token' => $tokenId, $fieldMap['name'] => 'Real User', $fieldMap['website'] => ''];
        $first = $engine->handle(RequestContext::fromGlobals($this->legitimateHeaders(), $body));
        $this->assertSame(Decision::ACCEPT, $first->result);

        $second = $engine->handle(RequestContext::fromGlobals($this->legitimateHeaders(), $body));
        $this->assertNotSame(Decision::ACCEPT, $second->result);
    }

    private function backdateToken(PdoStorage $storage, string $tokenId, int $secondsAgo): void
    {
        $ref = new \ReflectionProperty(PdoStorage::class, 'pdo');
        $ref->setAccessible(true);
        /** @var \PDO $pdo */
        $pdo = $ref->getValue($storage);

        $issuedAt = (new \DateTimeImmutable())->modify(sprintf('-%d seconds', $secondsAgo));
        $stmt = $pdo->prepare('UPDATE cw_tokens SET issued_at = :issued_at WHERE id = :id');
        $stmt->execute(['issued_at' => $issuedAt->format('Y-m-d H:i:s'), 'id' => $tokenId]);
    }
}
