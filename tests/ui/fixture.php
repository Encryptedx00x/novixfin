<?php
declare(strict_types=1);
$c = require __DIR__ . '/../../config/config.php';
$d = $c['db'];
if (!in_array($d['host'], ['127.0.0.1', 'localhost'], true) || !preg_match('/(^|[_-])(test|app)$/i', $d['database'])) {
    fwrite(STDERR, "Somente banco local descartável.\n"); exit(1);
}
$p = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$email = $argv[2] ?? '';
if (!preg_match('/^ui-[a-f0-9]+@novixfin\.test$/', $email)) { exit(1); }
if (($argv[1] ?? '') === 'create') {
    $p->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)')->execute(['UI Teste', $email, password_hash('SenhaTeste123!', PASSWORD_BCRYPT)]);
    $p->prepare('INSERT INTO accounts (user_id,name,type,balance) VALUES (?,?,?,?)')->execute([(int) $p->lastInsertId(), 'Conta teste', 'bank', 0]);
} elseif (($argv[1] ?? '') === 'delete') {
    $p->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
} else { exit(1); }
