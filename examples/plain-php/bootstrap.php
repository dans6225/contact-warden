<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\Engine;
use ContactWarden\Mail\CallbackMailer;
use ContactWarden\Reputation\DecayingScore;
use ContactWarden\Reputation\StorageBackedReputationStore;
use ContactWarden\Scoring\ScoreEngine;
use ContactWarden\Signals\ContentHeuristicSignal;
use ContactWarden\Signals\HoneypotSignal;
use ContactWarden\Signals\InteractionSignal;
use ContactWarden\Signals\RateLimitSignal;
use ContactWarden\Signals\RefererSignal;
use ContactWarden\Signals\ReputationSignal;
use ContactWarden\Signals\TimingSignal;
use ContactWarden\Signals\TokenValiditySignal;
use ContactWarden\Signals\UserAgentSignal;
use ContactWarden\Store\DatabaseConfig;
use ContactWarden\Store\PdoStorage;
use ContactWarden\Token\TokenIssuer;

$envFile = __DIR__ . '/../../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Real field names, shared by the form, the init endpoint, and the token's field map.
// '_interaction_count' rides along so its name gets randomized too, same as any other field.
$fieldNames = ['name', 'email', 'message', 'website', '_interaction_count'];

$config = ContactWardenConfig::default();
$storage = PdoStorage::fromConfig(DatabaseConfig::fromEnv());
$reputation = new StorageBackedReputationStore($storage, new DecayingScore($config->reputationHalfLifeSeconds));

$tokenSecret = $_ENV['CW_TOKEN_SECRET'] ?? '';
if ($tokenSecret === '') {
    throw new \RuntimeException('CW_TOKEN_SECRET must be set in .env for the demo to issue tokens.');
}
$tokenIssuer = new TokenIssuer($tokenSecret, $storage, $config->tokenExpirySeconds);

$signals = [
    new HoneypotSignal('website', $config->weights['honeypot']),
    new TimingSignal($config->minFormFillSeconds, $config->weights['timing_too_fast']),
    new InteractionSignal($config->weights['interaction_zero']),
    new TokenValiditySignal($config->weights['token_missing'], $config->weights['token_invalid'], $config->weights['token_expired']),
    new RefererSignal($_SERVER['HTTP_HOST'] ?? null, $config->weights['referer_missing'], $config->weights['referer_mismatch']),
    new UserAgentSignal($config->weights['user_agent_suspicious']),
    new ReputationSignal($reputation, $config->reputationMaxContribution),
    new ContentHeuristicSignal($config->spamKeywords, $config->maxLinksBeforePenalty, $config->weights['content_heuristic_max']),
    new RateLimitSignal($storage, $config->rateLimitMaxSubmissions, $config->rateLimitWindowSeconds, $config->weights['rate_limit_exceeded']),
];

// Demo mailer: appends to a local log instead of actually sending mail, so
// testing on the VM never risks a real send. Swap in a real MailerInterface
// implementation (SMTP, PHPMailer, etc.) for production use.
$mailer = new CallbackMailer(function (array $data) {
    $line = sprintf("[%s] %s\n", date('c'), json_encode($data));
    file_put_contents(__DIR__ . '/storage/mail.log', $line, FILE_APPEND | LOCK_EX);
});

$engine = new Engine($config, $signals, new ScoreEngine($config), $storage, $reputation, $tokenIssuer, $mailer);
