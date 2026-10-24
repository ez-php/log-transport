<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

use EzPhp\Logging\LogLevel;

/**
 * Class GelfPayloadFormatter
 *
 * Encodes a batch as GELF 1.1 messages for Graylog's GELF HTTP input, one JSON
 * object per entry, newline-separated:
 *
 *   {"version":"1.1","host":"web-1","short_message":"...","timestamp":1774094400.123,"level":3,"_user_id":42}
 *
 * The level is the syslog severity GELF expects (debug 7, info 6, warning 4,
 * error 3, critical 2). Context keys become `_`-prefixed additional fields;
 * a key outside GELF's `[\w.-]` field-name set has the offending characters
 * replaced by `_`, the reserved `_id` is renamed `_ctx_id`, and a value that
 * is not a string or number (array, bool, null, object) is JSON-encoded,
 * since GELF additional fields only carry strings and numbers.
 *
 * Graylog's GELF HTTP input takes one message per request unless "bulk
 * receiving" is enabled on the input, which accepts these newline-delimited
 * batches. Without it, construct HttpTransportDriver with `batchSize: 1`.
 * No UDP/chunked or TCP transport — GELF over HTTP only.
 *
 * @package EzPhp\LogTransport
 */
final class GelfPayloadFormatter implements PayloadFormatterInterface
{
    /**
     * @param string $host Value of GELF's required `host` field; defaults to gethostname().
     */
    public function __construct(private readonly string $host = '')
    {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return string
     */
    public function format(array $entries): string
    {
        $host = $this->host !== '' ? $this->host : (gethostname() ?: 'localhost');
        $lines = [];

        foreach ($entries as $entry) {
            $message = [
                'version' => '1.1',
                'host' => $host,
                'short_message' => $entry->message,
                'timestamp' => (float) $entry->timestamp->format('U.v'),
                'level' => self::syslogLevel($entry->level),
            ];

            foreach ($entry->context as $key => $value) {
                $message[self::fieldName((string) $key)] = is_string($value) || is_int($value) || is_float($value)
                    ? $value
                    : json_encode($value, JSON_THROW_ON_ERROR);
            }

            $lines[] = json_encode($message, JSON_THROW_ON_ERROR);
        }

        return implode("\n", $lines);
    }

    /**
     * @return string
     */
    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * @param LogLevel $level
     *
     * @return int Syslog severity.
     */
    private static function syslogLevel(LogLevel $level): int
    {
        return match ($level) {
            LogLevel::DEBUG => 7,
            LogLevel::INFO => 6,
            LogLevel::WARNING => 4,
            LogLevel::ERROR => 3,
            LogLevel::CRITICAL => 2,
        };
    }

    /**
     * @param string $key Context key.
     *
     * @return string GELF additional field name.
     */
    private static function fieldName(string $key): string
    {
        $name = '_' . (string) preg_replace('/[^\w.\-]/', '_', $key);

        return $name === '_id' ? '_ctx_id' : $name;
    }
}
