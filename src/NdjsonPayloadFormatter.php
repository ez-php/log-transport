<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Class NdjsonPayloadFormatter
 *
 * Encodes a batch as newline-delimited JSON, one object per entry:
 * {"timestamp":"2026-03-21T12:00:00+00:00","level":"error","message":"...","context":{...}}
 *
 * Newline-delimited JSON is accepted directly by Elasticsearch/OpenSearch
 * bulk-style HTTP intake and by most generic log-collector HTTP endpoints.
 * Platforms with a stricter body shape (e.g. Loki's streams/labels format)
 * need their own `PayloadFormatterInterface` implementation — see
 * `CLAUDE.md` → "What Does NOT Belong Here".
 *
 * @package EzPhp\LogTransport
 */
final class NdjsonPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = json_encode([
                'timestamp' => $entry->timestamp->format(DATE_ATOM),
                'level' => $entry->level->value,
                'message' => $entry->message,
                'context' => $entry->context,
            ]) ?: '{}';
        }

        return implode("\n", $lines);
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/x-ndjson';
    }
}
