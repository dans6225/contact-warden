<?php

declare(strict_types=1);

namespace ContactWarden\Challenge;

use ContactWarden\Http\RequestContext;

/**
 * Hook point for a secondary verification step on CHALLENGE decisions
 * (hCaptcha, Turnstile, a delayed confirm-click). v1 ships only this
 * interface plus a no-op default — wiring a real provider is left to the
 * host app so the package doesn't force a captcha-vendor dependency on
 * everyone using it.
 */
interface ChallengeHandlerInterface
{
    public function verify(RequestContext $context): bool;
}
