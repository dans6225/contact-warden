<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

final class ReputationQuery
{
    public function __construct(
        public readonly ?float $minScore = null,
        public readonly int $limit = 50,
        public readonly int $offset = 0,
    ) {
    }
}
