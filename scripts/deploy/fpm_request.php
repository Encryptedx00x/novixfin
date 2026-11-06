<?php
declare(strict_types=1);
/** CLI FastCGI client for private status/admin listeners. Does not change FPM config. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
try {
    $mode = $argv[1] ?? '';
    $address = $argv[2] ?? '';
    if (!preg_match('~^tcp://127\.0\.0\.1:[0-9]{1,5}$~D', $address)) { throw new RuntimeException('Only a loopback TCP listener is permitted.'); }
    $script = $mode === 'status' ? '/__novixfin_fpm_status' : ($argv[3] ?? '');
    $params = ['GATEWAY_INTERFACE' => 'CGI/1.1', 'REQUEST_METHOD' => 'GET', 'SCRIPT_FILENAME' => $script, 'SCRIPT_NAME' => $mode === 'status' ? $script : '/__private_control', 'QUERY_STRING' => $mode === 'status' ? 'json&full' : ($mode === 'guard' ? '' : 'op=' . ($argv[4] ?? 'inspect')), 'SERVER_PROTOCOL' => 'HTTP/1.1', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1', 'CONTENT_LENGTH' => '0'];
    if ($mode === 'admin') {
        if (!str_starts_with($script, '/') || !isset($argv[5])) { throw new RuntimeException('Admin requires absolute script and key-file paths.'); }
        $key = trim((string) file_get_contents($argv[5]));
        if (strlen($key) < 64) { throw new RuntimeException('Invalid private key.'); }
        $params['NOVIXFIN_CONTROL_KEY'] = $key;
    } elseif ($mode === 'guard') {
        if (!str_starts_with($script, '/')) { throw new RuntimeException('Guard check requires an absolute script path.'); }
    } elseif ($mode !== 'status') { throw new RuntimeException('Use status, admin or guard.'); }
    $socket = stream_socket_client($address, $errno, $error, 5);
    if ($socket === false) { throw new RuntimeException('FastCGI listener unavailable.'); }
    stream_set_timeout($socket, 5);
    $record = static fn (int $type, string $data = ''): string => pack('CCnnCC', 1, $type, 1, strlen($data), 0, 0) . $data;
    $length = static fn (int $n): string => $n < 128 ? chr($n) : pack('N', $n | 0x80000000);
    $encoded = '';
    foreach ($params as $name => $value) { $encoded .= $length(strlen($name)) . $length(strlen($value)) . $name . $value; }
    $request = $record(1, pack('nCxxxxx', 1, 0)) . $record(4, $encoded) . $record(4) . $record(5);
    while ($request !== '') {
        $written = fwrite($socket, $request);
        if ($written === false || $written === 0) { throw new RuntimeException('FastCGI write failed.'); }
        $request = substr($request, $written);
    }
    $read = static function (int $n) use ($socket): string {
        $data = '';
        while (strlen($data) < $n) {
            $chunk = fread($socket, $n - strlen($data));
            if ($chunk === false || $chunk === '') { throw new RuntimeException('Incomplete or timed out FastCGI response.'); }
            $data .= $chunk;
        }
        return $data;
    };
    $stdout = '';
    while (true) {
        $header = unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved', $read(8));
        $data = $read($header['length']);
        if ($header['padding']) { $read($header['padding']); }
        if ($header['type'] === 6) { $stdout .= $data; }
        if ($header['type'] === 7 && $data !== '') { throw new RuntimeException('FPM reported a script error.'); }
        if ($header['type'] === 3) { break; }
    }
    fclose($socket);
    $parts = explode("\r\n\r\n", $stdout, 2);
    $decoded = json_decode($parts[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
    if ($mode === 'status') {
        // Exclude request URIs and user names from operational evidence.
        $decoded = array_intersect_key($decoded, array_flip(['pool', 'start time', 'accepted conn', 'listen queue', 'active processes', 'idle processes', 'total processes', 'processes']));
        $decoded['processes'] = array_map(static fn (array $p): array => array_intersect_key($p, array_flip(['pid', 'state', 'start time', 'requests'])), $decoded['processes']);
    } elseif ($mode === 'guard') {
        if (!preg_match('/(?:^|\r\n)Status: 503\b/i', $parts[0]) || ($decoded['deploy_guard'] ?? false) !== true) {
            throw new RuntimeException('The PHP entry guard is not active.');
        }
        $decoded = ['ok' => true, 'entry_guard_verified' => true, 'status' => 503];
    } elseif (($decoded['ok'] ?? false) !== true) { throw new RuntimeException('FPM control refused operation: ' . ($decoded['error'] ?? 'unknown')); }
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
