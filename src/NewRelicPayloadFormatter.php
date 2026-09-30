<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Class NewRelicPayloadFormatter
 *
 * Encodes a batch for the New Relic Log API (`POST https://log-api.newrelic.com/log/v1`)
 * in its detailed format — one block with shared attributes and the entries:
 *
 *   [{"common":{"attributes":{"service.name":"...","hostname":"..."}},
 *     "logs":[{"timestamp":<ms-epoch>,"message":"...","attributes":{"level":"error","context":{...}}}]}]
 *
 * Context is nested under `context` (New Relic flattens it to `context.<key>`),
 * so it never collides with `level`, `message` or `timestamp`. The license key
 * goes into the sender's `Api-Key` / `X-License-Key` header.
 *
 * @package EzPhp\LogTransport
 */
final readonly class NewRelicPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param string $serviceName `service.name` common attribute; omitted when empty.
     * @param string $hostname    `hostname` common attribute; omitted when empty.
     */
    public function __construct(
        private string $serviceName = '',
        private string $hostname = '',
    ) {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        $logs = [];

        foreach ($entries as $entry) {
            $logs[] = [
                'timestamp' => $entry->timestamp->getTimestamp() * 1000 + (int) $entry->timestamp->format('v'),
                'message' => $entry->message,
                'attributes' => [
                    'level' => $entry->level->value,
                    'context' => (object) $entry->context,
                ],
            ];
        }

        $common = [];

        if ($this->serviceName !== '') {
            $common['service.name'] = $this->serviceName;
        }

        if ($this->hostname !== '') {
            $common['hostname'] = $this->hostname;
        }

        return json_encode([['common' => ['attributes' => (object) $common], 'logs' => $logs]], JSON_THROW_ON_ERROR);
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/json';
    }
}
