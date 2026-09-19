<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\LogEntry;
use EzPhp\LogTransport\LokiPayloadFormatter;

/**
 * Class LokiPayloadFormatterTest
 *
 * @package Tests
 */
final class LokiPayloadFormatterTest extends TestCase
{
    /**
     * @return array{streams: list<array{stream: array<string, string>, values: list<array{0: string, 1: string}>}>}
     */
    private function decode(string $json): array
    {
        /** @var array{streams: list<array{stream: array<string, string>, values: list<array{0: string, 1: string}>}>} $decoded */
        $decoded = json_decode($json, true);

        return $decoded;
    }

    public function test_it_formats_an_empty_batch_as_an_empty_streams_array(): void
    {
        $formatter = new LokiPayloadFormatter();

        self::assertSame(['streams' => []], $this->decode($formatter->format([])));
    }

    public function test_it_groups_entries_into_one_stream_per_level(): void
    {
        $formatter = new LokiPayloadFormatter();

        $entries = [
            new LogEntry(LogLevel::INFO, 'first', [], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
            new LogEntry(LogLevel::ERROR, 'second', [], new DateTimeImmutable('2026-03-21T12:00:01+00:00')),
            new LogEntry(LogLevel::INFO, 'third', [], new DateTimeImmutable('2026-03-21T12:00:02+00:00')),
        ];

        $streams = $this->decode($formatter->format($entries))['streams'];

        self::assertCount(2, $streams);

        $infoStream = array_values(array_filter(
            $streams,
            static fn (array $s): bool => $s['stream']['level'] === 'info',
        ))[0];

        self::assertCount(2, $infoStream['values']);
        self::assertSame('first', $infoStream['values'][0][1]);
        self::assertSame('third', $infoStream['values'][1][1]);
    }

    public function test_timestamps_are_unix_nanoseconds_as_strings(): void
    {
        $formatter = new LokiPayloadFormatter();

        $entries = [
            new LogEntry(LogLevel::INFO, 'first', [], new DateTimeImmutable('@1700000000')),
        ];

        $streams = $this->decode($formatter->format($entries))['streams'];

        self::assertSame('1700000000000000000', $streams[0]['values'][0][0]);
    }

    public function test_labels_include_a_configurable_service_label(): void
    {
        $formatter = new LokiPayloadFormatter(labels: ['service' => 'my-app']);

        $entries = [
            new LogEntry(LogLevel::INFO, 'first', [], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
        ];

        $streams = $this->decode($formatter->format($entries))['streams'];

        self::assertSame('my-app', $streams[0]['stream']['service']);
        self::assertSame('info', $streams[0]['stream']['level']);
    }

    public function test_context_is_appended_to_the_log_line_as_json(): void
    {
        $formatter = new LokiPayloadFormatter();

        $entries = [
            new LogEntry(LogLevel::ERROR, 'boom', ['user_id' => 42], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
        ];

        $streams = $this->decode($formatter->format($entries))['streams'];
        $line = $streams[0]['values'][0][1];

        self::assertStringContainsString('boom', $line);
        self::assertStringContainsString('"user_id":42', $line);
    }

    public function test_content_type_is_json(): void
    {
        $formatter = new LokiPayloadFormatter();

        self::assertSame('application/json', $formatter->contentType());
    }
}
