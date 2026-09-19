<?php

declare(strict_types=1);

/**
 * Router for the `php -S` server started by CurlHttpSenderTest.
 *
 * Records the last request it received to the file named by the
 * `CURL_SENDER_CAPTURE` environment variable, then answers with the status
 * code encoded in the path (`/status/<code>`) or stalls on `/slow`.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($requestUri) ? $requestUri : '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

$capture = getenv('CURL_SENDER_CAPTURE');

if (is_string($capture) && $capture !== '') {
    file_put_contents($capture, json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'path' => $path,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'custom_header' => $_SERVER['HTTP_X_TEST_HEADER'] ?? '',
        'body' => file_get_contents('php://input'),
    ]));
}

if ($path === '/slow') {
    sleep(3);
    http_response_code(200);

    return true;
}

if (preg_match('#^/status/(\d{3})$#', $path, $m) === 1) {
    http_response_code((int) $m[1]);

    return true;
}

http_response_code(404);

return true;
