<?php

declare(strict_types=1);

/**
 * NovixFin v3.1 — Task 5: persistence action for the "hide balances by
 * default" privacy preference. Mirrors the structure of `update-theme.php`
 * (POST + CSRF + auth + simple UPDATE) for minimal architectural noise.
 *
 * On POST it writes 0/1 into `users.hide_balances` and into `$_SESSION['user']`
 * so the layout reads the new state on the next render without a DB lookup.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

verifyCsrf($_POST['_token'] ?? '');

// Accept either "1"/"0" (form checkbox semantics send "1" when checked, absent
// when unchecked) OR explicit true/false strings/json. Normalise to int.
$raw      = $_POST['hide_balances'] ?? '0';
$enabled  = in_array($raw, ['1', 'true', 'on', true], true) ? 1 : 0;

$pdo    = Connection::get();
$userId = currentUserId() ?? 0;

try {
    $stmt = $pdo->prepare('UPDATE users SET hide_balances = :val WHERE id = :id');
    $stmt->execute([
        'val' => $enabled,
        'id'  => $userId,
    ]);

    $_SESSION['user']['hide_balances'] = (bool) $enabled;

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // AJAX caller (balance-visibility.js eye-icon): return JSON instead
        // of a redirect so the page doesn't reload.
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'hide_balances' => (bool) $enabled]);
        exit;
    }

    flash('success', 'Preferência de privacidade atualizada com sucesso.');
} catch (Throwable $e) {
    error_log('update-balance-visibility error: ' . $e->getMessage());

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'internal']);
        exit;
    }

    flash('error', 'Não foi possível salvar a preferência.');
}

redirect('profile.php');
