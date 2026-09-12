<?php

declare(strict_types=1);

namespace ContactWarden\Mail;

use ContactWarden\Http\RequestContext;
use ContactWarden\Store\ContactRecordStore;

/**
 * Delivers an accepted submission by persisting it and/or by email, each
 * independently toggle-able (e.g. from admin-managed settings) — drops
 * straight into Engine's mailer slot since it's itself a MailerInterface.
 * Storage runs first so a mail failure (or a misconfigured mailer) never
 * loses the message.
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
        if ($this->deliverDatabase && $this->recordStore !== null) {
            $this->recordStore->record($data, $context);
        }

        if ($this->deliverEmail) {
            $this->mailer->send($data, $context);
        }
    }
}
