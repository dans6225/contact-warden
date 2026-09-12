<?php

declare(strict_types=1);

namespace ContactWarden\Challenge;

use ContactWarden\Http\RequestContext;

/** Default stub: never passes. A CHALLENGE decision behaves like a soft reject until a real handler is configured. */
final class NullChallengeHandler implements ChallengeHandlerInterface
{
    public function verify(RequestContext $context): bool
    {
        return false;
    }
}
