<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

/**
 * Deliberately conservative: only an empty UA or an unambiguous scripting
 * client's default string fires this. Never key off generic browser tokens —
 * the old implementation flagged Firefox's `resistFingerprinting` UA and
 * false-positived on privacy-conscious real users.
 */
final class UserAgentSignal implements SignalInterface
{
    private const KNOWN_SCRIPT_CLIENTS = '/^(curl|wget|python-requests|python-urllib|scrapy|libwww-perl|go-http-client|java\/|axios\/)/i';

    public function __construct(private readonly int $weight)
    {
    }

    public function evaluate(RequestContext $context): SignalResult
    {
        $ua = trim($context->headers['USER-AGENT'] ?? '');

        if ($ua === '' || preg_match(self::KNOWN_SCRIPT_CLIENTS, $ua) === 1) {
            return new SignalResult('user_agent', $this->weight, ['user_agent' => $ua]);
        }

        return new SignalResult('user_agent', 0);
    }
}
