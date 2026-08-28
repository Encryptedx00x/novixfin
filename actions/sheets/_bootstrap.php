<?php

declare(strict_types=1);

/**
 * NovixFin — base comum dos handlers do Modo Planilha.
 * Responde SEMPRE em JSON; valida CSRF, auth e a posse do sheet pelo user_id.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;

/** Resposta JSON e encerra. */
function sheets_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Exige POST + CSRF válido. */
function sheets_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        sheets_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        verifyCsrf($_POST['_token'] ?? '');
    } catch (Throwable $e) {
        sheets_json(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 419);
    }
}

/**
 * Devolve o sheet do usuário, criando um vazio na primeira vez.
 * Sempre filtrado por user_id — nunca confia no id vindo do cliente.
 */
function sheets_get_or_create(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, name, content, updated_at FROM sheets WHERE user_id = :u ORDER BY id ASC LIMIT 1');
    $stmt->execute(['u' => $userId]);
    $sheet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sheet) {
        return $sheet;
    }

    $empty = json_encode(['tabs' => [['name' => 'Página 1', 'cells' => new stdClass()]]], JSON_UNESCAPED_UNICODE);
    $ins = $pdo->prepare('INSERT INTO sheets (user_id, name, content) VALUES (:u, :n, :c)');
    $ins->execute(['u' => $userId, 'n' => 'Rascunho', 'c' => $empty]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'name' => 'Rascunho',
        'content' => $empty,
        'updated_at' => null,
    ];
}

/** Valida e normaliza o content recebido (array de abas com células). */
function sheets_sanitize_content(string $raw): ?string
{
    if (strlen($raw) > 4 * 1024 * 1024) { // 4 MB de guarda
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['tabs']) || !is_array($data['tabs']) || $data['tabs'] === []) {
        return null;
    }

    $tabs = [];
    foreach ($data['tabs'] as $tab) {
        if (!is_array($tab)) {
            continue;
        }
        $name = trim((string) ($tab['name'] ?? 'Página'));
        if ($name === '') {
            $name = 'Página';
        }
        $cells = [];
        if (isset($tab['cells']) && is_array($tab['cells'])) {
            foreach ($tab['cells'] as $ref => $value) {
                // Só referências A1 válidas; o valor é o que o usuário digitou
                // (texto, número OU fórmula) — nunca o resultado calculado.
                if (!is_string($ref) || !preg_match('/^[A-Z]{1,3}[0-9]{1,5}$/', $ref)) {
                    continue;
                }
                if (is_array($value) || is_object($value)) {
                    continue;
                }
                $cells[$ref] = (string) $value;
            }
        }
        $tabs[] = ['name' => mb_substr($name, 0, 60), 'cells' => (object) $cells];
        if (count($tabs) >= 30) { // teto de abas
            break;
        }
    }

    if ($tabs === []) {
        return null;
    }

    return json_encode(['tabs' => $tabs], JSON_UNESCAPED_UNICODE);
}
