<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\LogEntry;
use EzPhp\LogTransport\OtlpLogsPayloadFormatter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class OtlpLogsPayloadFormatterTest
 *
 * @package Tests
 */
#[CoversClass(OtlpLogsPayloadFormatter::class)]
final class OtlpLogsPayloadFormatterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $json): array
    {
        /** @var array{resourceLogs: list<array{scopeLogs: list<array{logRecords: list<array<string, mixed>>}>}>} $body */
        $body = $this->decode($json);

        return $body['resourceLogs'][0]['scopeLogs'][0]['logRecords'];
    }

    public function test_it_wraps_records_in_resource_and_scope(): void
    {
        $formatter = new OtlpLogsPayloadFormatter('billing', ['deployment.environment' => 'prod']);
        $entry = new LogEntry(LogLevel::WARNING, 'disk low', [], new DateTimeImmutable('@1700000000.250000'));

        $body = $this->decode($formatter->format([$entry]));

        self::assertSame([
            'resourceLogs' => [[
                'resource' => ['attributes' => [
                    ['key' => 'service.name', 'value' => ['stringValue' => 'billing']],
                    ['key' => 'deployment.environment', 'value' => ['stringValue' => 'prod']],
                ]],
                'scopeLogs' => [[
                    'scope' => ['name' => 'ez-php/log-transport'],
                    'logRecords' => [[
                        'timeUnixNano' => '1700000000250000000',
                        'severityNumber' => 13,
                        'severityText' => 'WARNING',
                        'body' => ['stringValue' => 'disk low'],
                    ]],
                ]],
            ]],
        ], $body);
        self::assertSame('application/json', $formatter->contentType());
    }

    public function test_severity_numbers_follow_the_otel_ranges(): void
    {
        $formatter = new OtlpLogsPayloadFormatter('app');
        $entries = array_map(
            static fn (LogLevel $level): LogEntry => new LogEntry($level, 'm', [], new DateTimeImmutable()),
            [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL],
        );

        $records = $this->records($formatter->format($entries));

        self::assertSame([5, 9, 13, 17, 21], array_column($records, 'severityNumber'));
    }

    public function test_context_becomes_typed_attributes_and_trace_ids_link_the_span(): void
    {
        $formatter = new OtlpLogsPayloadFormatter('app');
        $entry = new LogEntry(LogLevel::ERROR, 'failed', [
            'user' => 'ayla',
            'attempt' => 3,
            'ratio' => 0.5,
            'retry' => false,
            'tags' => ['a', 'b'],
            'trace_id' => '4BF92F3577B34DA6A3CE929D0E0E4736',
            'span_id' => '00f067aa0ba902b7',
        ], new DateTimeImmutable());

        $record = $this->records($formatter->format([$entry]))[0] ?? [];

        self::assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $record['traceId']);
        self::assertSame('00f067aa0ba902b7', $record['spanId']);
        self::assertSame([
            ['key' => 'user', 'value' => ['stringValue' => 'ayla']],
            ['key' => 'attempt', 'value' => ['intValue' => '3']],
            ['key' => 'ratio', 'value' => ['doubleValue' => 0.5]],
            ['key' => 'retry', 'value' => ['boolValue' => false]],
            ['key' => 'tags', 'value' => ['stringValue' => '["a","b"]']],
        ], $record['attributes']);
    }

    public function test_malformed_trace_ids_stay_attributes(): void
    {
        $formatter = new OtlpLogsPayloadFormatter('app');
        $entry = new LogEntry(LogLevel::INFO, 'm', ['trace_id' => 'not-a-trace'], new DateTimeImmutable());

        $record = $this->records($formatter->format([$entry]))[0] ?? [];

        self::assertArrayNotHasKey('traceId', $record);
        self::assertSame([['key' => 'trace_id', 'value' => ['stringValue' => 'not-a-trace']]], $record['attributes']);
    }
}
