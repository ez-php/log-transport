<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Interface PayloadFormatterInterface
 *
 * Turns a batch of buffered log entries into a request body a remote log
 * platform (ELK, Datadog, Loki, ...) can ingest.
 *
 * @package EzPhp\LogTransport
 */
interface PayloadFormatterInterface
{
    /**
     * @param list<LogEntry> $entries Buffered entries, oldest first.
     *
     * @return string Encoded request body.
     */
    public function format(array $entries): string;

    /**
     * The `Content-Type` header value the formatted body requires.
     *
     * @return string
     */
    public function contentType(): string;
}
