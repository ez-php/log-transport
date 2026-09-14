<?php

declare(strict_types=1);

namespace EzPhp\LogTransport;

use DateTimeImmutable;
use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;

/**
 * Class HttpTransportDriver
 *
 * Buffers log entries in memory and ships them to a remote log platform
 * (ELK, Datadog, Loki, ...) as a single batched HTTP request once the buffer
 * reaches `$batchSize`. Implements `LoggerInterface` so it composes behind
 * `EzPhp\Logging\StackDriver` like any other driver.
 *
 * @package EzPhp\LogTransport
 */
final class HttpTransportDriver implements LoggerInterface
{
    /**
     * @var list<LogEntry>
     */
    private array $buffer = [];

    /**
     * HttpTransportDriver Constructor
     *
     * @param string                        $endpoint  Destination URL.
     * @param HttpSenderInterface           $sender    Performs the actual HTTP call.
     * @param PayloadFormatterInterface     $formatter Encodes a batch into a request body.
     * @param int                           $batchSize Entries buffered before an automatic flush (must be >= 1).
     * @param array<string, string>         $headers   Extra request headers merged over `Content-Type`.
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly HttpSenderInterface $sender,
        private readonly PayloadFormatterInterface $formatter,
        private readonly int $batchSize = 50,
        private readonly array $headers = [],
    ) {
    }

    /**
     * @param LogLevel             $level
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->buffer[] = new LogEntry($level, $message, $context, new DateTimeImmutable());

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Ship the current buffer, if any, and clear it.
     *
     * Best-effort: the buffer is cleared whether the send succeeds or fails,
     * so a remote outage cannot grow it without bound. Callers that need
     * delivery guarantees should wrap this driver in their own retry/queue
     * layer rather than relying on it here.
     *
     * @return bool True when the buffer was empty or the send succeeded.
     */
    public function flush(): bool
    {
        if ($this->buffer === []) {
            return true;
        }

        $payload = $this->formatter->format($this->buffer);
        $headers = ['Content-Type' => $this->formatter->contentType(), ...$this->headers];

        $success = $this->sender->send($this->endpoint, $payload, $headers);

        $this->buffer = [];

        return $success;
    }

    /**
     * @return int Number of entries currently buffered, awaiting flush.
     */
    public function bufferedCount(): int
    {
        return count($this->buffer);
    }
}
