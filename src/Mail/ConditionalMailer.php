<?php

declare(strict_types=1);

namespace ContactWarden\Mail;

use ContactWarden\Http\RequestContext;
use ContactWarden\Store\ContactRecordStore;

/**
 * Delivers an accepted submission by email and/or by persisting it, each
 * independently toggle-able (e.g. from admin-managed settings) — drops
 * straight into Engine's mailer slot since it's itself a MailerInterface.
 */
final class ConditionalMailer implements MailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ?ContactRecordStore $recordStore,
        private readonly bool $deliverEmail = true,
        private readonly bool $deliverDatabase = true,
    ) {
    }

    public function send(array $data, RequestContext $context): void
    {
        if ($this->deliverEmail) {
            $this->mailer->send($data, $context);
        }

        if ($this->deliverDatabase && $this->recordStore !== null) {
            $this->recordStore->record($data, $context);
        }
    }
}
