<?php

declare(strict_types=1);

namespace ContactWarden\Mail;

use ContactWarden\Http\RequestContext;

interface MailerInterface
{
    /** @param array<string,mixed> $data Resolved submission fields to deliver */
    public function send(array $data, RequestContext $context): void;
}
