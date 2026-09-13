<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

final class SubmissionQuery
{
    public function __construct(
        public readonly ?string $decision = null,
        public readonly ?string $ip = null,
        public readonly ?\DateTimeImmutable $since = null,
        public readonly ?\DateTimeImmutable $until = null,
        public readonly int $limit = 50,
        public readonly int $offset = 0,
    ) {
    }
}
