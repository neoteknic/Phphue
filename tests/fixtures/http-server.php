<?php
// Loopback-only HTTP fixture, terminated by LocalHttpServer after each test.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(1);
}
echo stream_socket_get_name($server, false), "\n";
flush();
$connectionId = 0;
while ($connection = @stream_socket_accept($server, 10)) {
    ++$connectionId;
    stream_set_timeout($connection, 10);
    while ($line = fgets($connection)) {
        $parts = explode(' ', trim($line));
        if (count($parts) < 2) {
            break;
        }
        [$method, $path] = $parts;
        $headers = [];
        while (($line = fgets($connection)) !== false && trim($line) !== '') {
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower($key)] = trim($value);
        }
        $body = '';
        $length = (int) ($headers['content-length'] ?? 0);
        while (strlen($body) < $length) {
            $chunk = fread($connection, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }
        if (str_starts_with($path, '/stall')) {
            sleep(6); // No headers: this is a connection/request failure, not an SSE duration limit.
            break;
        }
        if (str_starts_with($path, '/idle')) {
            fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nConnection: close\r\n\r\n");
            fflush($connection);
            sleep(6);
            break;
        }
        $status = '200 OK';
        if (str_starts_with($path, '/forbidden-sse')) {
            // An error body that looks like an SSE frame must never be dispatched.
            $status = '403 Forbidden';
            $response = 'data: ' . json_encode([['id' => 'leak', 'type' => 'update', 'data' => []]]) . "\n\n";
            $contentType = 'text/event-stream';
        } elseif (str_starts_with($path, '/forbidden')) {
            $status = '403 Forbidden';
            $response = json_encode(['errors' => [['description' => 'unauthorized user']], 'data' => []]);
            $contentType = 'application/json';
        } elseif (str_ends_with($path, '/eventstream/clip/v2')) {
            $events = [
                ['id' => 'event-1', 'type' => 'update', 'data' => []],
                ['id' => 'event-2', 'type' => 'update', 'data' => []],
            ];
            $response = 'data: ' . json_encode($events) . "\n\n";
            $contentType = 'text/event-stream';
        } else {
            $response = json_encode(['errors' => [], 'data' => [[
                'method' => $method, 'body' => $body, 'connection' => $connectionId,
                'headers' => $headers,
            ]]]);
            $contentType = 'application/json';
        }
        fwrite($connection, "HTTP/1.1 {$status}\r\nContent-Type: {$contentType}\r\nContent-Length: " . strlen($response) . "\r\n\r\n" . $response);
        fflush($connection);
    }
    fclose($connection);
}
