<?php
declare(strict_types=1);
/** Private FPM-only control. Stage OUTSIDE all web roots, with root-owned key/manifest. */
header('Content-Type: application/json; charset=utf-8');
function control_reply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
$keyFile = __DIR__ . '/control.key';
$provided = $_SERVER['NOVIXFIN_CONTROL_KEY'] ?? '';
$key = is_file($keyFile) ? trim((string) file_get_contents($keyFile)) : '';
if (PHP_SAPI !== 'fpm-fcgi' || !is_string($provided) || strlen($key) < 64 || !hash_equals($key, $provided)) {
    control_reply(['ok' => false, 'error' => 'Control access denied.'], 403);
}
try {
    $operation = $_GET['op'] ?? 'inspect';
    if ($operation === 'status') {
        if (!function_exists('fpm_get_status')) { throw new RuntimeException('Pool observation unavailable.'); }
        $raw = fpm_get_status();
        if (!is_array($raw) || !isset($raw['procs'])) { throw new RuntimeException('Invalid pool snapshot.'); }
        $status = [];
        foreach (['pool', 'start-time', 'accepted-conn', 'listen-queue', 'active-processes', 'idle-processes', 'total-processes'] as $field) {
            if (!array_key_exists($field, $raw)) { throw new RuntimeException('Incomplete pool snapshot.'); }
            $status[str_replace('-', ' ', $field)] = $raw[$field];
        }
        $status['processes'] = [];
        $status['observer pid'] = getmypid();
        foreach ($raw['procs'] as $process) {
            $status['processes'][] = ['pid' => $process['pid'], 'state' => $process['state'], 'start time' => $process['start-time'], 'requests' => $process['requests']];
        }
        control_reply(['ok' => true, 'sapi' => PHP_SAPI, 'status' => $status]);
    }
    $manifest = json_decode((string) file_get_contents(__DIR__ . '/sheets-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $allowed = ['actions/sheets/_bootstrap.php', 'actions/sheets/load.php', 'actions/sheets/save.php', 'actions/sheets/snapshot.php', 'actions/sheets/restore.php', 'public/scripts/sheet.js'];
    $root = realpath((string) ($manifest['root'] ?? ''));
    if ($root === false || $root === '/' || array_keys($manifest['sha256'] ?? []) !== $allowed) {
        throw new RuntimeException('Invalid private manifest.');
    }
    if (!in_array($operation, ['inspect', 'invalidate'], true)) { throw new RuntimeException('Invalid operation.'); }
    $cache = opcache_get_status(false);
    if ($cache === false || empty($cache['opcache_enabled'])) { throw new RuntimeException('OPcache must be enabled.'); }
    $files = [];
    // Validate ALL source hashes before invalidating any cached script.
    foreach ($allowed as $file) {
        $path = $root . '/' . $file;
        $expected = $manifest['sha256'][$file];
        $actual = hash_file('sha256', $path);
        if (!is_string($expected) || !hash_equals($expected, (string) $actual)) { throw new RuntimeException('Source hash mismatch: ' . $file); }
        $files[$file] = ['sha256' => $actual, 'cached_before' => str_ends_with($file, '.php') && opcache_is_script_cached($path)];
    }
    if ($operation === 'invalidate') {
        foreach ($allowed as $file) {
            if (!str_ends_with($file, '.php')) { continue; }
            $path = $root . '/' . $file;
            if (!opcache_invalidate($path, true)) { throw new RuntimeException('Invalidation failed: ' . $file); }
            $files[$file]['cached_after'] = opcache_is_script_cached($path);
            if ($files[$file]['cached_after']) { throw new RuntimeException('Script still cached: ' . $file); }
        }
    }
    control_reply(['ok' => true, 'sapi' => PHP_SAPI, 'pid' => getmypid(), 'opcache_start_time' => $cache['opcache_statistics']['start_time'], 'operation' => $operation, 'files' => $files]);
} catch (Throwable $e) {
    control_reply(['ok' => false, 'error' => $e->getMessage()], 500);
}
