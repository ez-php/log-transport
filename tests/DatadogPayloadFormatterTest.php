<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\DatadogPayloadFormatter;
use EzPhp\LogTransport\LogEntry;

/**
 * Class DatadogPayloadFormatterTest
 *
 * @package Tests
 */
final class DatadogPayloadFormatterTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function decode(string $json): array
    {
        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($json, true);

        return $decoded;
    }

    public function test_it_formats_an_empty_batch_as_an_empty_json_array(): void
    {
        $formatter = new DatadogPayloadFormatter();

        self::assertSame('[]', $formatter->format([]));
    }

    public function test_it_encodes_one_object_per_entry_with_reserved_keys(): void
    {
        $formatter = new DatadogPayloadFormatter();

        $entries = [
            new LogEntry(LogLevel::ERROR, 'boom', ['user_id' => 42], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
        ];

        $records = $this->decode($formatter->format($entries));

        self::assertCount(1, $records);
        self::assertSame('boom', $records[0]['message']);
        self::assertSame('error', $records[0]['status']);
        self::assertSame(1774094400000, $records[0]['timestamp']);
        self::assertSame(['user_id' => 42], $records[0]['context']);
    }

    public function test_it_includes_configurable_service_and_source_tags(): void
    {
        $formatter = new DatadogPayloadFormatter(service: 'my-app', source: 'php');

        $entries = [
            new LogEntry(LogLevel::INFO, 'hello', [], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
        ];

        $records = $this->decode($formatter->format($entries));

        self::assertSame('my-app', $records[0]['service']);
        self::assertSame('php', $records[0]['ddsource']);
    }

    public function test_content_type_is_json(): void
    {
        $formatter = new DatadogPayloadFormatter();

        self::assertSame('application/json', $formatter->contentType());
    }
}
