<?php

declare(strict_types=1);

namespace ContactWarden\Config;

/**
 * Server-side-authoritative scoring weights, decision thresholds, and the
 * package's resolved "open decisions" from the architecture brief:
 *  - `rejectResponseMode` defaults to 'silent' — a silent-drop REJECT denies
 *    bots feedback to iterate against; hosts that want an explicit rejection
 *    page can switch this per their own tolerance for user confusion.
 *  - JS-absent submissions are resolved by weights, not a separate flag:
 *    `token_missing` (35) lands in the CHALLENGE band by default, never
 *    REJECT alone, so a no-JS user is challenged, not blocked.
 *  - CHALLENGE ships v1 as a pluggable hook (`Challenge\ChallengeHandlerInterface`)
 *    with a no-op default, not a bundled CAPTCHA integration.
 *
 * Weights are additive contributions a fired signal adds to a submission's
 * score; ScoreEngine sums them and compares against the thresholds below to
 * produce ACCEPT / CHALLENGE / REJECT. Defaults are picked so that:
 *  - a single strong-but-plausible signal (missing token, fast timing) lands
 *    in the CHALLENGE band, never REJECT, on its own — legitimate no-JS or
 *    autofill users must not be hard-blocked by one signal;
 *  - only the honeypot (a field no human ever sees or fills) is strong
 *    enough to REJECT alone;
 *  - soft signals (referer, user-agent) stay low enough that two of them
 *    together still don't reach CHALLENGE.
 */
final class ContactWardenConfig
{
    public const DEFAULT_WEIGHTS = [
        'honeypot' => 100,
        'timing_too_fast' => 40,
        'interaction_zero' => 8,
        'token_missing' => 35,
        'token_invalid' => 45,
        'token_expired' => 25,
        'referer_missing' => 10,
        'referer_mismatch' => 15,
        'user_agent_suspicious' => 10,
        'content_heuristic_max' => 30,
        'rate_limit_exceeded' => 50,
    ];

    public const DEFAULT_SPAM_KEYWORDS = [
        'viagra', 'cialis', 'casino', 'crypto airdrop', 'work from home',
        'seo services', 'backlink', 'weight loss', 'forex', 'bitcoin investment',
    ];

    /**
     * @param array<string,int> $weights Signal name => score contribution when the signal fires.
     * @param int $challengeThreshold Score at/above which a submission moves ACCEPT -> CHALLENGE.
     * @param int $rejectThreshold Score at/above which a submission is REJECTed outright.
     * @param int $tokenExpirySeconds How long an issued form token remains valid.
     * @param int $minFormFillSeconds Elapsed time below this fires `timing_too_fast`.
     * @param int $reputationMaxContribution Cap on how much decaying IP reputation alone can add,
     *   so a stale/partially-decayed bad score can't singlehandedly force a REJECT.
     * @param int $reputationHalfLifeSeconds Half-life for decaying IP/netblock reputation.
     * @param int $reputationPenaltyOnReject Reputation bump applied to the IP on a REJECT.
     * @param int $reputationPenaltyOnChallenge Reputation bump applied to the IP on a CHALLENGE.
     * @param int $rateLimitMaxSubmissions Submissions allowed per IP within the window before it fires.
     * @param int $rateLimitWindowSeconds Sliding window size for the rate-limit signal.
     * @param string $rejectResponseMode 'silent' (looks successful, message dropped) or 'explicit' (neutral rejection page) — a hint for the host app, not enforced by Engine itself.
     * @param string[] $spamKeywords Keywords the content heuristic signal scans for.
     * @param int $maxLinksBeforePenalty Link count above which the content heuristic signal starts penalizing.
     * @param string[] $trustedProxies IPs allowed to set X-Forwarded-For when resolving the client IP.
     */
    public function __construct(
        public readonly array $weights = self::DEFAULT_WEIGHTS,
        public readonly int $challengeThreshold = 30,
        public readonly int $rejectThreshold = 70,
        public readonly int $tokenExpirySeconds = 1800,
        public readonly int $minFormFillSeconds = 2,
        public readonly int $reputationMaxContribution = 50,
        public readonly int $reputationHalfLifeSeconds = 604800,
        public readonly int $reputationPenaltyOnReject = 30,
        public readonly int $reputationPenaltyOnChallenge = 10,
        public readonly int $rateLimitMaxSubmissions = 5,
        public readonly int $rateLimitWindowSeconds = 600,
        public readonly string $rejectResponseMode = 'silent',
        public readonly array $spamKeywords = self::DEFAULT_SPAM_KEYWORDS,
        public readonly int $maxLinksBeforePenalty = 3,
        public readonly array $trustedProxies = [],
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    public function withWeights(array $overrides): self
    {
        return new self(
            weights: array_merge($this->weights, $overrides),
            challengeThreshold: $this->challengeThreshold,
            rejectThreshold: $this->rejectThreshold,
            tokenExpirySeconds: $this->tokenExpirySeconds,
            minFormFillSeconds: $this->minFormFillSeconds,
            reputationMaxContribution: $this->reputationMaxContribution,
            reputationHalfLifeSeconds: $this->reputationHalfLifeSeconds,
            reputationPenaltyOnReject: $this->reputationPenaltyOnReject,
            reputationPenaltyOnChallenge: $this->reputationPenaltyOnChallenge,
            rateLimitMaxSubmissions: $this->rateLimitMaxSubmissions,
            rateLimitWindowSeconds: $this->rateLimitWindowSeconds,
            rejectResponseMode: $this->rejectResponseMode,
            spamKeywords: $this->spamKeywords,
            maxLinksBeforePenalty: $this->maxLinksBeforePenalty,
            trustedProxies: $this->trustedProxies,
        );
    }
}
