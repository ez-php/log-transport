<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;
use EzPhp\LogTransport\GelfPayloadFormatter;
use EzPhp\LogTransport\LogEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(GelfPayloadFormatter::class)]
#[UsesClass(LogEntry::class)]
final class GelfPayloadFormatterTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function decodeLines(string $body): array
    {
        $messages = [];
        foreach (explode("\n", $body) as $line) {
            /** @var array<string, mixed> $message */
            $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $messages[] = $message;
        }

        return $messages;
    }

    public function test_empty_batch_is_an_empty_body(): void
    {
        self::assertSame('', (new GelfPayloadFormatter('web-1'))->format([]));
    }

    public function test_one_gelf_message_per_line_with_required_fields(): void
    {
        $entries = [
            new LogEntry(LogLevel::ERROR, 'boom', [], new DateTimeImmutable('2026-03-21T12:00:00.123+00:00')),
            new LogEntry(LogLevel::DEBUG, 'detail', [], new DateTimeImmutable('2026-03-21T12:00:01+00:00')),
        ];

        $messages = $this->decodeLines((new GelfPayloadFormatter('web-1'))->format($entries));

        self::assertCount(2, $messages);
        self::assertSame('1.1', $messages[0]['version']);
        self::assertSame('web-1', $messages[0]['host']);
        self::assertSame('boom', $messages[0]['short_message']);
        self::assertSame(1774094400.123, $messages[0]['timestamp']);
        self::assertSame(3, $messages[0]['level']);
        self::assertSame(7, $messages[1]['level']);
    }

    public function test_levels_map_to_syslog_severities(): void
    {
        $levels = [];
        foreach ([LogLevel::INFO, LogLevel::WARNING, LogLevel::CRITICAL] as $level) {
            $entry = new LogEntry($level, 'x', [], new DateTimeImmutable());
            $levels[] = $this->decodeLines((new GelfPayloadFormatter('h'))->format([$entry]))[0]['level'];
        }

        self::assertSame([6, 4, 2], $levels);
    }

    public function test_context_becomes_prefixed_additional_fields(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'hello', [
            'user_id' => 42,
            'ratio' => 0.5,
            'route' => '/orders',
            'tags' => ['a', 'b'],
            'flag' => true,
            'missing' => null,
            'id' => 'abc',
            'bad key!' => 'v',
        ], new DateTimeImmutable());

        $message = $this->decodeLines((new GelfPayloadFormatter('h'))->format([$entry]))[0];

        self::assertSame(42, $message['_user_id']);
        self::assertSame(0.5, $message['_ratio']);
        self::assertSame('/orders', $message['_route']);
        self::assertSame('["a","b"]', $message['_tags']);
        self::assertSame('true', $message['_flag']);
        self::assertSame('null', $message['_missing']);
        self::assertSame('abc', $message['_ctx_id']);
        self::assertArrayNotHasKey('_id', $message);
        self::assertSame('v', $message['_bad_key_']);
    }

    public function test_host_defaults_to_the_machine_name(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'x', [], new DateTimeImmutable());
        $message = $this->decodeLines((new GelfPayloadFormatter())->format([$entry]))[0];

        self::assertSame(gethostname() ?: 'localhost', $message['host']);
    }

    public function test_content_type_is_json(): void
    {
        self::assertSame('application/json', (new GelfPayloadFormatter())->contentType());
    }
}
