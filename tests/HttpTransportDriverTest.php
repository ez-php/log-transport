<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\LogTransport\HttpTransportDriver;
use EzPhp\LogTransport\NdjsonPayloadFormatter;

/**
 * Class HttpTransportDriverTest
 *
 * @package Tests
 */
final class HttpTransportDriverTest extends TestCase
{
    public function test_it_buffers_entries_without_sending_below_the_batch_size(): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 3);

        $driver->info('one');
        $driver->info('two');

        self::assertSame(2, $driver->bufferedCount());
        self::assertSame([], $sender->calls);
    }

    public function test_it_flushes_automatically_once_the_batch_size_is_reached(): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 2);

        $driver->info('one');
        $driver->error('two');

        self::assertSame(0, $driver->bufferedCount());
        self::assertCount(1, $sender->calls);
        self::assertSame('https://logs.example.test/ingest', $sender->calls[0]['url']);
        self::assertStringContainsString('"message":"one"', $sender->calls[0]['payload']);
        self::assertStringContainsString('"message":"two"', $sender->calls[0]['payload']);
        self::assertSame('application/x-ndjson', $sender->calls[0]['headers']['Content-Type']);
    }

    public function test_manual_flush_sends_and_clears_a_partial_batch(): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 10);

        $driver->warning('partial');

        $result = $driver->flush();

        self::assertTrue($result);
        self::assertSame(0, $driver->bufferedCount());
        self::assertCount(1, $sender->calls);
    }

    public function test_flushing_an_empty_buffer_does_not_call_the_sender(): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 10);

        $result = $driver->flush();

        self::assertTrue($result);
        self::assertSame([], $sender->calls);
    }

    public function test_a_failed_send_still_clears_the_buffer(): void
    {
        $sender = new FakeHttpSender(result: false);
        $driver = new HttpTransportDriver('https://logs.example.test/ingest', $sender, new NdjsonPayloadFormatter(), batchSize: 1);

        $driver->critical('boom');

        self::assertSame(0, $driver->bufferedCount());
        self::assertCount(1, $sender->calls);
    }

    public function test_extra_headers_are_merged_over_the_content_type(): void
    {
        $sender = new FakeHttpSender();
        $driver = new HttpTransportDriver(
            'https://logs.example.test/ingest',
            $sender,
            new NdjsonPayloadFormatter(),
            batchSize: 1,
            headers: ['Authorization' => 'Bearer token'],
        );

        $driver->info('one');

        self::assertSame('Bearer token', $sender->calls[0]['headers']['Authorization']);
        self::assertSame('application/x-ndjson', $sender->calls[0]['headers']['Content-Type']);
    }
}
