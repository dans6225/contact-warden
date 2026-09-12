<?php

declare(strict_types=1);

namespace ContactWarden\Mail;

use ContactWarden\Http\RequestContext;

/** Delegates to a host-supplied closure — avoids forcing a mail-library dependency on every consumer. */
final class CallbackMailer implements MailerInterface
{
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function send(array $data, RequestContext $context): void
    {
        ($this->callback)($data, $context);
    }
}
