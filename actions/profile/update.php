<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\UserRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

verifyCsrf($_POST['_token'] ?? '');

$name = trim((string) ($_POST['name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$name || strlen($name) < 3) {
    flash('error', 'Informe um nome com pelo menos 3 caracteres.');
    redirect('profile.php');
}

if (!$email) {
    flash('error', 'Informe um email válido.');
    redirect('profile.php');
}

$pdo = Connection::get();
$userRepository = new UserRepository($pdo);
$currentUser = $userRepository->findById(currentUserId() ?? 0);

if ($currentUser && strcasecmp($currentUser['email'], $email) !== 0) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
    $stmt->execute([
        'email' => $email,
        'id' => currentUserId(),
    ]);

    if ($stmt->fetch()) {
        flash('error', 'Este email já está em uso por outro usuário.');
        redirect('profile.php');
    }
}

$userRepository->updateProfile(currentUserId() ?? 0, $name, $email);

$_SESSION['user']['name'] = $name;
$_SESSION['user']['email'] = $email;

flash('success', 'Perfil atualizado com sucesso.');
redirect('profile.php');

