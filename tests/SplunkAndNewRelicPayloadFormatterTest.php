<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\LogEntry;
use EzPhp\LogTransport\NewRelicPayloadFormatter;
use EzPhp\LogTransport\SplunkHecPayloadFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class SplunkAndNewRelicPayloadFormatterTest
 *
 * @package Tests
 */
#[CoversClass(SplunkHecPayloadFormatter::class)]
#[CoversClass(NewRelicPayloadFormatter::class)]
#[UsesClass(LogEntry::class)]
final class SplunkAndNewRelicPayloadFormatterTest extends TestCase
{
    /**
     * @return list<LogEntry>
     */
    private function entries(): array
    {
        return [
            new LogEntry(LogLevel::ERROR, 'boom', ['user_id' => 42], new DateTimeImmutable('2026-03-21T12:00:00.250+00:00')),
            new LogEntry(LogLevel::INFO, 'ok', [], new DateTimeImmutable('2026-03-21T12:00:01+00:00')),
        ];
    }

    public function test_splunk_concatenates_one_event_object_per_entry(): void
    {
        $body = (new SplunkHecPayloadFormatter())->format($this->entries());

        self::assertSame(
            '{"time":1774094400.25,"event":{"message":"boom","severity":"error","context":{"user_id":42}}}'
            . '{"time":1774094401.0,"event":{"message":"ok","severity":"info","context":{}}}',
            $body,
        );
    }

    public function test_splunk_metadata_is_sent_only_when_configured(): void
    {
        $body = (new SplunkHecPayloadFormatter(sourcetype: '_json', source: 'app', index: 'main', host: 'web-1'))
            ->format([$this->entries()[1]]);

        $event = json_decode($body, true);
        self::assertIsArray($event);
        self::assertSame(['_json', 'app', 'main', 'web-1'], [$event['sourcetype'], $event['source'], $event['index'], $event['host']]);
        self::assertSame('', (new SplunkHecPayloadFormatter())->format([]));
        self::assertSame('application/json', (new SplunkHecPayloadFormatter())->contentType());
    }

    public function test_new_relic_detailed_format(): void
    {
        $body = (new NewRelicPayloadFormatter(serviceName: 'shop', hostname: 'web-1'))->format($this->entries());

        self::assertSame(
            '[{"common":{"attributes":{"service.name":"shop","hostname":"web-1"}},"logs":['
            . '{"timestamp":1774094400250,"message":"boom","attributes":{"level":"error","context":{"user_id":42}}},'
            . '{"timestamp":1774094401000,"message":"ok","attributes":{"level":"info","context":{}}}]}]',
            $body,
        );
    }

    public function test_new_relic_without_common_attributes(): void
    {
        $formatter = new NewRelicPayloadFormatter();

        self::assertSame('[{"common":{"attributes":{}},"logs":[]}]', $formatter->format([]));
        self::assertSame('application/json', $formatter->contentType());
    }
}
