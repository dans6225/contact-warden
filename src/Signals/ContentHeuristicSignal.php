<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

final class ContentHeuristicSignal implements SignalInterface
{
    /** @param string[] $keywords */
    public function __construct(
        private readonly array $keywords,
        private readonly int $maxLinksBeforePenalty,
        private readonly int $maxWeight,
    ) {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $text = strtolower(implode(' ', array_filter($context->body, 'is_string')));

        $keywordHits = 0;
        foreach ($this->keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                $keywordHits++;
            }
        }

        $linkCount = preg_match_all('#https?://#i', $text);
        $excessLinks = max(0, $linkCount - $this->maxLinksBeforePenalty);

        $score = min($this->maxWeight, $keywordHits * 8 + $excessLinks * 10);

        return new SignalResult('content_heuristic', $score, [
            'keyword_hits' => $keywordHits,
            'link_count' => $linkCount,
        ]);
    }
}
