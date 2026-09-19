<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Class DatadogPayloadFormatter
 *
 * Encodes a batch as a JSON array of Datadog Logs intake API objects:
 *
 *   [{"message":"...","status":"error","timestamp":<ms-epoch>,"service":"...","ddsource":"...","context":{...}}, ...]
 *
 * `service`/`ddsource` are Datadog's standard reserved tag fields for
 * filtering in the Logs UI; both are optional and simply omitted when not
 * configured. Structured context is nested under `context` rather than
 * spread onto the top level, to avoid ever colliding with a Datadog
 * reserved key (`message`, `status`, `timestamp`, `service`, `ddsource`, …).
 *
 * @package EzPhp\LogTransport
 */
final class DatadogPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param string $service Value for the reserved `service` field; omitted when empty.
     * @param string $source  Value for the reserved `ddsource` field; omitted when empty.
     */
    public function __construct(
        private readonly string $service = '',
        private readonly string $source = '',
    ) {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        $records = [];

        foreach ($entries as $entry) {
            $record = [
                'message' => $entry->message,
                'status' => $entry->level->value,
                'timestamp' => $entry->timestamp->getTimestamp() * 1000,
                'context' => $entry->context,
            ];

            if ($this->service !== '') {
                $record['service'] = $this->service;
            }

            if ($this->source !== '') {
                $record['ddsource'] = $this->source;
            }

            $records[] = $record;
        }

        return json_encode($records, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/json';
    }
}
