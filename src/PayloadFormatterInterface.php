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
     * json_encode() flags every shipped formatter uses. Log data is not under
     * the logger's control, so a value JSON cannot carry must not make format()
     * throw: invalid UTF-8 becomes U+FFFD and NAN/INF (or another unencodable
     * value) is replaced instead of failing the whole batch.
     */
    public const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

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
