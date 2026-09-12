<?php

declare(strict_types=1);

namespace ContactWarden\Store;

use ContactWarden\Http\RequestContext;

/**
 * Persists the full content of an accepted submission — deliberately narrow
 * (persistence only, no read-back or inbox-state API). StorageInterface's
 * cw_submissions only logs the scoring decision, never the message itself;
 * this is for consumers who want the actual content kept somewhere reviewable.
 * Building an inbox (read/archive/delete) on top is left to the host app.
 */
interface ContactRecordStore
{
    /** @param array<string,mixed> $data */
    public function record(array $data, RequestContext $context): void;
}
