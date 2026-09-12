<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Reputation\ReputationStore;

final class ReputationSignal implements SignalInterface
{
    public function __construct(
        private readonly ReputationStore $reputationStore,
        private readonly int $maxContribution,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $score = $this->reputationStore->getDecayedScore($context->ip, $context->receivedAt);
        $contribution = (int) min($score, $this->maxContribution);

        return new SignalResult('reputation', max(0, $contribution), ['decayed_score' => $score]);
    }
}
