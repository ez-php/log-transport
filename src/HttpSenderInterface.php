<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Interface HttpSenderInterface
 *
 * Abstraction over the actual HTTP call, so `HttpTransportDriver` can be
 * unit-tested without a real network round trip.
 *
 * @package EzPhp\LogTransport
 */
interface HttpSenderInterface
{
    /**
     * @param string                $url     Destination endpoint.
     * @param string                $payload Encoded request body.
     * @param array<string, string> $headers Request headers, including `Content-Type`.
     *
     * @return bool True on a successful (2xx) response, false otherwise.
     */
    public function send(string $url, string $payload, array $headers): bool;
}
