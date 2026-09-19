<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\LogTransport\CurlHttpSender;
use RuntimeException;

/**
 * Class CurlHttpSenderTest
 *
 * Exercises the real cURL transport against a `php -S` server on 127.0.0.1;
 * no internet access needed.
 *
 * @package Tests
 */
final class CurlHttpSenderTest extends TestCase
{
    /**
     * @var resource|null
     */
    private static mixed $server = null;

    private static int $port = 0;

    private static string $capture = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$port = self::reservePort();
        self::$capture = sys_get_temp_dir() . '/ez-php-curl-sender-' . bin2hex(random_bytes(4)) . '.json';

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/Support/curl-sender-server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['PHP_CLI_SERVER_WORKERS' => '2', 'CURL_SENDER_CAPTURE' => self::$capture],
        );

        if (!is_resource($server)) {
            throw new RuntimeException('Could not start the loopback server.');
        }

        self::$server = $server;
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            // Connection-refused warnings are expected until the server is up.
            set_error_handler(static fn (): bool => true, E_WARNING);

            try {
                $probe = stream_socket_client('tcp://127.0.0.1:' . self::$port, $errno, $errstr, 0.2);
            } finally {
                restore_error_handler();
            }

            if ($probe !== false) {
                fclose($probe);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException('The loopback server did not accept connections within 5 seconds.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        self::$server = null;

        if (is_file(self::$capture)) {
            unlink(self::$capture);
        }

        parent::tearDownAfterClass();
    }

    public function test_it_posts_payload_and_headers_and_returns_true_on_2xx(): void
    {
        $sender = new CurlHttpSender();

        $ok = $sender->send(
            $this->url('/status/204'),
            '{"a":1}',
            ['Content-Type' => 'application/json', 'X-Test-Header' => 'hello'],
        );

        self::assertTrue($ok);

        $request = $this->lastRequest();
        self::assertSame('POST', $request['method']);
        self::assertSame('/status/204', $request['path']);
        self::assertSame('application/json', $request['content_type']);
        self::assertSame('hello', $request['custom_header']);
        self::assertSame('{"a":1}', $request['body']);
    }

    public function test_it_returns_false_on_a_non_2xx_response(): void
    {
        self::assertFalse((new CurlHttpSender())->send($this->url('/status/500'), 'x', []));
        self::assertFalse((new CurlHttpSender())->send($this->url('/status/404'), 'x', []));
    }

    public function test_it_returns_false_when_the_connection_is_refused(): void
    {
        $closedPort = self::reservePort();

        self::assertFalse((new CurlHttpSender())->send('http://127.0.0.1:' . $closedPort . '/', 'x', []));
    }

    public function test_it_returns_false_when_the_request_times_out(): void
    {
        $started = microtime(true);

        self::assertFalse((new CurlHttpSender(timeoutSeconds: 1))->send($this->url('/slow'), 'x', []));
        self::assertLessThan(3.0, microtime(true) - $started);
    }

    private function url(string $path): string
    {
        return 'http://127.0.0.1:' . self::$port . $path;
    }

    /**
     * @return array{method: string, path: string, content_type: string, custom_header: string, body: string}
     */
    private function lastRequest(): array
    {
        $raw = file_get_contents(self::$capture);
        self::assertIsString($raw);

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        $string = static fn (mixed $value): string => is_string($value) ? $value : '';

        return [
            'method' => $string($data['method'] ?? null),
            'path' => $string($data['path'] ?? null),
            'content_type' => $string($data['content_type'] ?? null),
            'custom_header' => $string($data['custom_header'] ?? null),
            'body' => $string($data['body'] ?? null),
        ];
    }

    private static function reservePort(): int
    {
        $errno = 0;
        $errstr = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException('Could not reserve a port: ' . $errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
