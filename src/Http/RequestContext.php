<?php

declare(strict_types=1);

namespace ContactWarden\Http;

/**
 * Normalized view of an inbound submission. `attributes` carries data the
 * Engine derives while handling the request (token status, resolved field
 * values, reported interaction count) so every Signal can read from one
 * object instead of each needing its own bespoke wiring.
 */
final class RequestContext
{
    /**
     * @param array<string,string> $headers Header name (upper-case, dash-separated) => value
     * @param array<string,mixed> $body Submitted body, keyed by whatever field names arrived
     * @param array<string,mixed> $attributes Engine-derived data, set via withAttributes()
     */
    public function __construct(
        public readonly string $ip,
        public readonly array $headers,
        public readonly array $body,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * @param array<string,mixed> $server $_SERVER
     * @param array<string,mixed> $body $_POST
     * @param string[] $trustedProxies IPs allowed to set X-Forwarded-For (e.g. your load balancer)
     */
    public static function fromGlobals(array $server, array $body, array $trustedProxies = []): self
    {
        return new self(
            ip: self::resolveIp($server, $trustedProxies),
            headers: self::extractHeaders($server),
            body: $body,
            receivedAt: new \DateTimeImmutable(),
        );
    }

    /** @param array<string,mixed> $attributes merged into the existing ones */
    public function withAttributes(array $attributes): self
    {
        return new self($this->ip, $this->headers, $this->body, $this->receivedAt, [...$this->attributes, ...$attributes]);
    }

    /** @param array<string,mixed> $server */
    private static function resolveIp(array $server, array $trustedProxies): string
    {
        $remoteAddr = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!in_array($remoteAddr, $trustedProxies, true)) {
            return $remoteAddr;
        }

        $forwardedFor = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedFor === '') {
            return $remoteAddr;
        }

        $client = trim(explode(',', $forwardedFor)[0]);

        return $client !== '' ? $client : $remoteAddr;
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = (string) $server['CONTENT_TYPE'];
        }

        return $headers;
    }
}
