<?php

declare(strict_types=1);

namespace ContactWarden\Admin;

final class SubmissionRecord
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $createdAt,
        public readonly string $ip,
        public readonly string $decision,
        public readonly int $score,
        public readonly array $meta,
    ) {
    }
}
