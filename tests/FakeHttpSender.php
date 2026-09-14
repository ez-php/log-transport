<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\LogTransport\HttpSenderInterface;

/**
 * Class FakeHttpSender
 *
 * Test double that records every call instead of making a real HTTP request.
 * Public properties are used (rather than reference-backed privates) so
 * PHPStan level 9 does not flag them as write-only.
 *
 * @package Tests
 */
final class FakeHttpSender implements HttpSenderInterface
{
    /**
     * @var list<array{url: string, payload: string, headers: array<string, string>}>
     */
    public array $calls = [];

    /**
     * @param bool $result Value every call to `send()` returns.
     */
    public function __construct(private readonly bool $result = true)
    {
    }

    /**
     * @param string                $url
     * @param string                $payload
     * @param array<string, string> $headers
     *
     * @return bool
     */
    public function send(string $url, string $payload, array $headers): bool
    {
        $this->calls[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];

        return $this->result;
    }
}
