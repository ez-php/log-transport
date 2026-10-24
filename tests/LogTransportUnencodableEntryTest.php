<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\LogTransport\DatadogPayloadFormatter;
use EzPhp\LogTransport\GelfPayloadFormatter;
use EzPhp\LogTransport\HttpSenderInterface;
use EzPhp\LogTransport\HttpTransportDriver;
use EzPhp\LogTransport\LogEntry;
use EzPhp\LogTransport\LokiPayloadFormatter;
use EzPhp\LogTransport\NdjsonPayloadFormatter;
use EzPhp\LogTransport\NewRelicPayloadFormatter;
use EzPhp\LogTransport\PayloadFormatterInterface;
use EzPhp\LogTransport\SplunkHecPayloadFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * Class LogTransportUnencodableEntryTest
 *
 * An entry json_encode() cannot represent as is (invalid UTF-8, NAN/INF) must
 * neither throw out of log() nor stay in the buffer and break later entries.
 *
 * @package Tests
 */
#[CoversClass(HttpTransportDriver::class)]
#[CoversClass(NdjsonPayloadFormatter::class)]
#[CoversClass(LokiPayloadFormatter::class)]
#[CoversClass(GelfPayloadFormatter::class)]
#[CoversClass(SplunkHecPayloadFormatter::class)]
#[CoversClass(NewRelicPayloadFormatter::class)]
#[CoversClass(DatadogPayloadFormatter::class)]
#[UsesClass(LogEntry::class)]
final class LogTransportUnencodableEntryTest extends TestCase
{
    /**
     * @return array<string, array{PayloadFormatterInterface}>
     */
    public static function formatters(): array
    {
        return [
            'ndjson' => [new NdjsonPayloadFormatter()],
            'loki' => [new LokiPayloadFormatter()],
            'gelf' => [new GelfPayloadFormatter('web-1')],
            'splunk' => [new SplunkHecPayloadFormatter()],
            'new relic' => [new NewRelicPayloadFormatter()],
            'datadog' => [new DatadogPayloadFormatter()],
        ];
    }

    #[DataProvider('formatters')]
    public function test_unencodable_entries_are_shipped_substituted_and_do_not_block_later_ones(PayloadFormatterInterface $formatter): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, $formatter, batchSize: 1);

        $driver->info("bad \xff byte", ['raw' => "ctx \xfe"]);
        $driver->warning('not a number', ['ratio' => NAN, 'limit' => INF]);
        $driver->error('fine');

        self::assertSame(0, $driver->bufferedCount());
        self::assertCount(3, $sender->calls);
        self::assertStringContainsString('\\ufffd', $sender->calls[0]['payload']);
        self::assertStringContainsString('not a number', $sender->calls[1]['payload']);
        self::assertStringContainsString('fine', $sender->calls[2]['payload']);
        self::assertStringNotContainsString('bad', $sender->calls[2]['payload']);
    }

    public function test_a_throwing_formatter_drops_the_batch_instead_of_throwing_from_log(): void
    {
        $sender = new FakeHttpSender();
        $formatter = new class () implements PayloadFormatterInterface {
            /**
             * @param list<LogEntry> $entries
             *
             * @return string
             */
            public function format(array $entries): string
            {
                throw new RuntimeException('cannot format');
            }

            /**
             * @return string
             */
            public function contentType(): string
            {
                return 'text/plain';
            }
        };
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, $formatter, batchSize: 10);

        $driver->info('one');

        self::assertFalse($driver->flush());
        self::assertSame(0, $driver->bufferedCount());
        self::assertSame([], $sender->calls);
    }

    public function test_a_throwing_sender_drops_the_batch_instead_of_throwing_from_log(): void
    {
        $sender = new class () implements HttpSenderInterface {
            /**
             * @param array<string, string> $headers
             */
            public function send(string $url, string $payload, array $headers): bool
            {
                throw new RuntimeException('Failed to initialise a cURL handle.');
            }
        };
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 1);

        $driver->info('one');

        self::assertSame(0, $driver->bufferedCount());
    }
}
