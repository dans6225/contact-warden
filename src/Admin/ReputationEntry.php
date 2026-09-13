<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

use ContactWarden\Reputation\ReputationRecord;

final class ReputationEntry
{
    public function __construct(
        public readonly string $subject,
        public readonly ReputationRecord $record,
    ) {
    }
}
