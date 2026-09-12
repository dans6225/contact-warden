<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Mail;

use ContactWarden\Http\RequestContext;
use ContactWarden\Mail\ConditionalMailer;
use ContactWarden\Mail\MailerInterface;
use ContactWarden\Store\ContactRecordStore;
use PHPUnit\Framework\TestCase;

final class ConditionalMailerTest extends TestCase
{
    private function context(): RequestContext
    {
        return new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());
    }

    public function test_both_enabled_calls_both(): void
    {
        $mailer = new SpyMailer();
        $store = new SpyContactRecordStore();
        $conditional = new ConditionalMailer($mailer, $store, deliverEmail: true, deliverDatabase: true);

        $conditional->send(['name' => 'Jane'], $this->context());

        $this->assertSame(1, $mailer->calls);
        $this->assertSame(1, $store->calls);
    }

    public function test_email_only(): void
    {
        $mailer = new SpyMailer();
        $store = new SpyContactRecordStore();
        $conditional = new ConditionalMailer($mailer, $store, deliverEmail: true, deliverDatabase: false);

        $conditional->send(['name' => 'Jane'], $this->context());

        $this->assertSame(1, $mailer->calls);
        $this->assertSame(0, $store->calls);
    }

    public function test_database_only(): void
    {
        $mailer = new SpyMailer();
        $store = new SpyContactRecordStore();
        $conditional = new ConditionalMailer($mailer, $store, deliverEmail: false, deliverDatabase: true);

        $conditional->send(['name' => 'Jane'], $this->context());

        $this->assertSame(0, $mailer->calls);
        $this->assertSame(1, $store->calls);
    }

    public function test_neither_enabled_calls_neither(): void
    {
        $mailer = new SpyMailer();
        $store = new SpyContactRecordStore();
        $conditional = new ConditionalMailer($mailer, $store, deliverEmail: false, deliverDatabase: false);

        $conditional->send(['name' => 'Jane'], $this->context());

        $this->assertSame(0, $mailer->calls);
        $this->assertSame(0, $store->calls);
    }

    public function test_database_enabled_but_no_store_configured_does_not_error(): void
    {
        $mailer = new SpyMailer();
        $conditional = new ConditionalMailer($mailer, null, deliverEmail: false, deliverDatabase: true);

        $conditional->send(['name' => 'Jane'], $this->context());

        $this->assertSame(0, $mailer->calls);
    }
}

final class SpyMailer implements MailerInterface
{
    public int $calls = 0;

    public function send(array $data, RequestContext $context): void
    {
        $this->calls++;
    }
}

final class SpyContactRecordStore implements ContactRecordStore
{
    public int $calls = 0;

    public function record(array $data, RequestContext $context): void
    {
        $this->calls++;
    }
}
