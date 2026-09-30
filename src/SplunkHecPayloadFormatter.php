<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Class SplunkHecPayloadFormatter
 *
 * Encodes a batch for the Splunk HTTP Event Collector (`POST /services/collector/event`):
 * one JSON event object per entry, concatenated without separators or an
 * enclosing array — the HEC batch format:
 *
 *   {"time":1774094400.123,"event":{"message":"...","severity":"error","context":{...}},"sourcetype":"..."}{...}
 *
 * `host`, `source`, `sourcetype` and `index` are HEC metadata keys, sent only
 * when configured. The token goes into the `Authorization: Splunk <token>`
 * header of the sender, not the payload.
 *
 * @package EzPhp\LogTransport
 */
final readonly class SplunkHecPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param string $sourcetype HEC `sourcetype`; omitted when empty.
     * @param string $source     HEC `source`; omitted when empty.
     * @param string $index      HEC `index`; omitted when empty (the token's default index applies).
     * @param string $host       HEC `host`; omitted when empty.
     */
    public function __construct(
        private string $sourcetype = '',
        private string $source = '',
        private string $index = '',
        private string $host = '',
    ) {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        $body = '';

        foreach ($entries as $entry) {
            $record = [
                'time' => (float) $entry->timestamp->format('U.v'),
                'event' => [
                    'message' => $entry->message,
                    'severity' => $entry->level->value,
                    'context' => (object) $entry->context,
                ],
            ];

            foreach (['sourcetype' => $this->sourcetype, 'source' => $this->source, 'index' => $this->index, 'host' => $this->host] as $key => $value) {
                if ($value !== '') {
                    $record[$key] = $value;
                }
            }

            $body .= json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        }

        return $body;
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/json';
    }
}
