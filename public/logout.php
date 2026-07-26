<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$token = $_POST['_token'] ?? '';
verifyCsrf($token);

logout();
flash('success', 'Você saiu com segurança.');
redirect('login.php');

