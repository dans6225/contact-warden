<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Signals;

use ContactWarden\Http\RequestContext;
use ContactWarden\Signals\UserAgentSignal;
use PHPUnit\Framework\TestCase;

final class UserAgentSignalTest extends TestCase
{
    public function test_fires_on_empty_user_agent(): void
    {
        $signal = new UserAgentSignal(10);
        $context = new RequestContext('1.2.3.4', [], [], new \DateTimeImmutable());

        $this->assertSame(10, $signal->evaluate($context)->score);
    }

    public function test_fires_on_known_script_client(): void
    {
        $signal = new UserAgentSignal(10);
        $context = new RequestContext('1.2.3.4', ['USER-AGENT' => 'curl/8.4.0'], [], new \DateTimeImmutable());

        $this->assertSame(10, $signal->evaluate($context)->score);
    }

    /**
     * Regression test: the old implementation flagged Firefox's generic
     * `Gecko/20100101` resistFingerprinting token, false-positiving on real
     * privacy-conscious users. This must never fire on ordinary browser UAs.
     */
    public function test_does_not_fire_on_firefox_resist_fingerprinting_ua(): void
    {
        $signal = new UserAgentSignal(10);
        $context = new RequestContext(
            '1.2.3.4',
            ['USER-AGENT' => 'Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0'],
            [],
            new \DateTimeImmutable(),
        );

        $this->assertSame(0, $signal->evaluate($context)->score);
    }

    public function test_does_not_fire_on_ordinary_chrome_ua(): void
    {
        $signal = new UserAgentSignal(10);
        $context = new RequestContext(
            '1.2.3.4',
            ['USER-AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'],
            [],
            new \DateTimeImmutable(),
        );

        $this->assertSame(0, $signal->evaluate($context)->score);
    }
}
