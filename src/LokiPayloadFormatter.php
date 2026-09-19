<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

/**
 * Class LokiPayloadFormatter
 *
 * Encodes a batch as Grafana Loki's push-API "streams" format:
 *
 *   {"streams":[{"stream":{"level":"error", ...labels},"values":[["<unix-nano>","<line>"], ...]}]}
 *
 * Entries are grouped into one stream per distinct level value (Loki streams
 * are keyed by their full label set; level is the one label this module can
 * derive without configuration). Additional static labels (e.g. `service`,
 * `env`) can be supplied via the constructor and are merged into every
 * stream's label set.
 *
 * @package EzPhp\LogTransport
 */
final class LokiPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param array<string, string> $labels Static labels merged into every stream (e.g. ['service' => 'my-app']).
     */
    public function __construct(
        private readonly array $labels = [],
    ) {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        /** @var array<string, array{stream: array<string, string>, values: list<array{0: string, 1: string}>}> $streams */
        $streams = [];

        foreach ($entries as $entry) {
            $level = $entry->level->value;

            if (!isset($streams[$level])) {
                $streams[$level] = [
                    'stream' => [...$this->labels, 'level' => $level],
                    'values' => [],
                ];
            }

            $streams[$level]['values'][] = [
                $this->unixNanoseconds($entry),
                $this->line($entry),
            ];
        }

        return json_encode(['streams' => array_values($streams)], JSON_THROW_ON_ERROR);
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * @param LogEntry $entry
     *
     * @return string
     */
    private function unixNanoseconds(LogEntry $entry): string
    {
        return $entry->timestamp->format('U') . '000000000';
    }

    /**
     * @param LogEntry $entry
     *
     * @return string
     */
    private function line(LogEntry $entry): string
    {
        if ($entry->context === []) {
            return $entry->message;
        }

        return $entry->message . ' ' . (json_encode($entry->context, JSON_THROW_ON_ERROR));
    }
}
