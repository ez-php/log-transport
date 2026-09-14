<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

use DateTimeImmutable;
use EzPhp\Logging\LogLevel;

/**
 * Class LogEntry
 *
 * Immutable value object for a single buffered log record awaiting shipment.
 *
 * @package EzPhp\LogTransport
 */
final readonly class LogEntry
{
    /**
     * LogEntry Constructor
     *
     * @param LogLevel             $level     Severity level.
     * @param string               $message   Human-readable description.
     * @param array<string, mixed> $context   Structured context data.
     * @param DateTimeImmutable    $timestamp When the entry was recorded.
     */
    public function __construct(
        public LogLevel $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
