<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

use EzPhp\Logging\LogLevel;

/**
 * Class OtlpLogsPayloadFormatter
 *
 * Formats a batch as an OTLP/HTTP JSON logs request (`resourceLogs`), for
 * `POST <collector>/v1/logs` on an OpenTelemetry collector — the same collector
 * `ez-php/otel` (traces) and `ez-php/metrics-otlp` (metrics) export to, so logs
 * need no second pipeline.
 *
 * - severity: DEBUG 5, INFO 9, WARNING 13, ERROR 17, CRITICAL 21 (OTel severity numbers)
 * - context entries become attributes (strings, ints, doubles, bools; anything else JSON-encoded)
 * - `trace_id` (32 hex) and `span_id` (16 hex) in the context — what ez-php/otel's
 *   `TraceContextLogger` adds — become the record's `traceId`/`spanId` instead of
 *   attributes, so the collector links the log to its span. No ez-php/otel dependency.
 *
 * Protobuf and gRPC are out of scope (OTLP/HTTP JSON only).
 *
 * @package EzPhp\LogTransport
 */
final readonly class OtlpLogsPayloadFormatter implements PayloadFormatterInterface
{
    public const string SCOPE = 'ez-php/log-transport';

    /**
     * @param string                $serviceName        `service.name` resource attribute.
     * @param array<string, string> $resourceAttributes Further resource attributes (e.g. `deployment.environment`).
     */
    public function __construct(
        private string $serviceName,
        private array $resourceAttributes = [],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function format(array $entries): string
    {
        $records = [];

        foreach ($entries as $entry) {
            $records[] = $this->record($entry);
        }

        return json_encode([
            'resourceLogs' => [[
                'resource' => ['attributes' => self::attributes(['service.name' => $this->serviceName, ...$this->resourceAttributes])],
                'scopeLogs' => [[
                    'scope' => ['name' => self::SCOPE],
                    'logRecords' => $records,
                ]],
            ]],
        ], self::JSON_FLAGS | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * {@inheritDoc}
     */
    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * @param LogEntry $entry
     *
     * @return array<string, mixed>
     */
    private function record(LogEntry $entry): array
    {
        $context = $entry->context;
        $record = [
            'timeUnixNano' => $entry->timestamp->format('Uu') . '000',
            'severityNumber' => self::severityNumber($entry->level),
            'severityText' => strtoupper($entry->level->value),
            'body' => ['stringValue' => $entry->message],
        ];

        foreach (['trace_id' => ['traceId', 32], 'span_id' => ['spanId', 16]] as $key => [$field, $length]) {
            $id = $context[$key] ?? null;

            if (is_string($id) && strlen($id) === $length && ctype_xdigit($id)) {
                $record[$field] = strtolower($id);
                unset($context[$key]);
            }
        }

        $attributes = self::attributes($context);

        if ($attributes !== []) {
            $record['attributes'] = $attributes;
        }

        return $record;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<array{key: string, value: array<string, mixed>}>
     */
    private static function attributes(array $values): array
    {
        $attributes = [];

        foreach ($values as $key => $value) {
            $attributes[] = ['key' => (string) $key, 'value' => self::anyValue($value)];
        }

        return $attributes;
    }

    /**
     * An OTLP AnyValue. int64 travels as a string in OTLP JSON.
     *
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private static function anyValue(mixed $value): array
    {
        return match (true) {
            is_string($value) => ['stringValue' => $value],
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) && is_finite($value) => ['doubleValue' => $value],
            $value instanceof \Stringable => ['stringValue' => (string) $value],
            default => ['stringValue' => (string) json_encode($value, self::JSON_FLAGS)],
        };
    }

    /**
     * @param LogLevel $level
     *
     * @return int OpenTelemetry severity number.
     */
    private static function severityNumber(LogLevel $level): int
    {
        return match ($level) {
            LogLevel::DEBUG => 5,
            LogLevel::INFO => 9,
            LogLevel::WARNING => 13,
            LogLevel::ERROR => 17,
            LogLevel::CRITICAL => 21,
        };
    }
}
