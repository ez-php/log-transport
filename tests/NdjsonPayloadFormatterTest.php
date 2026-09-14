<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\LogEntry;
use EzPhp\LogTransport\NdjsonPayloadFormatter;

/**
 * Class NdjsonPayloadFormatterTest
 *
 * @package Tests
 */
final class NdjsonPayloadFormatterTest extends TestCase
{
    public function test_it_formats_an_empty_batch_as_an_empty_string(): void
    {
        $formatter = new NdjsonPayloadFormatter();

        self::assertSame('', $formatter->format([]));
    }

    public function test_it_encodes_one_json_object_per_line(): void
    {
        $formatter = new NdjsonPayloadFormatter();

        $entries = [
            new LogEntry(LogLevel::INFO, 'first', ['a' => 1], new DateTimeImmutable('2026-03-21T12:00:00+00:00')),
            new LogEntry(LogLevel::ERROR, 'second', [], new DateTimeImmutable('2026-03-21T12:00:01+00:00')),
        ];

        $lines = explode("\n", $formatter->format($entries));

        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        self::assertIsArray($first);
        self::assertSame('2026-03-21T12:00:00+00:00', $first['timestamp']);
        self::assertSame('info', $first['level']);
        self::assertSame('first', $first['message']);
        self::assertSame(['a' => 1], $first['context']);

        $second = json_decode($lines[1], true);
        self::assertIsArray($second);
        self::assertSame('error', $second['level']);
        self::assertSame('second', $second['message']);
        self::assertSame([], $second['context']);
    }

    public function test_content_type_is_ndjson(): void
    {
        $formatter = new NdjsonPayloadFormatter();

        self::assertSame('application/x-ndjson', $formatter->contentType());
    }
}
