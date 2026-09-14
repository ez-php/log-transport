<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

use CurlHandle;
use RuntimeException;

/**
 * Class CurlHttpSender
 *
 * Default `HttpSenderInterface` implementation, backed by ext-curl.
 *
 * @package EzPhp\LogTransport
 */
final class CurlHttpSender implements HttpSenderInterface
{
    /**
     * CurlHttpSender Constructor
     *
     * @param int $timeoutSeconds Request timeout, in seconds.
     */
    public function __construct(private readonly int $timeoutSeconds = 5)
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
        $handle = curl_init($url);

        if (!$handle instanceof CurlHandle) {
            throw new RuntimeException('Failed to initialise a cURL handle.');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        curl_exec($handle);

        $failed = curl_errno($handle) !== 0;
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        return !$failed && $statusCode >= 200 && $statusCode < 300;
    }
}
