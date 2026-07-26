<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/themes.php';

use Novix\Database\Connection;

function findUserByEmail(string $email): ?array
{
    try {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Throwable $e) {
        error_log('findUserByEmail error: ' . $e->getMessage());
        return null;
    }
}

function createUser(string $name, string $email, string $password, string $theme = NOVIX_THEME_DEFAULT): array
{
    try {
        $pdo = Connection::get();
        $authConfig = require __DIR__ . '/../config/auth.php';

        // Entrada slice: landing/localStorage bridge — persist the pre-auth
        // theme choice at signup. Invalid/absent slugs fall back to default.
        $theme = novix_valid_theme($theme);

        // Verifica se email já existe
        if (findUserByEmail($email)) {
            throw new RuntimeException('Email já cadastrado.');
        }

        $hashedPassword = password_hash($password, $authConfig['password_algo'], $authConfig['password_options']);
        
        if ($hashedPassword === false) {
            throw new RuntimeException('Erro ao gerar hash da senha.');
        }

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, theme, created_at) VALUES (:name, :email, :password, :theme, NOW())');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'theme' => $theme,
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($id === 0) {
            throw new RuntimeException('Falha ao obter ID do usuário criado.');
        }

        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];
    } catch (PDOException $e) {
        error_log('createUser PDO error: ' . $e->getMessage());
        throw new RuntimeException('Erro ao criar usuário no banco de dados.');
    } catch (Throwable $e) {
        error_log('createUser error: ' . $e->getMessage());
        throw $e;
    }
}

function authenticate(string $email, string $password): bool
{
    try {
        $user = findUserByEmail($email);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'theme' => novix_valid_theme($user['theme'] ?? NOVIX_THEME_DEFAULT),
            // v3.1 Task 5: privacy default — "hide balances by default" is
            // server-persisted. Truthy default hedged with `?? true`
            // (until the migration is applied the column doesn't exist on
            // legacy rows; falling back to "hidden" matches the spec).
            'hide_balances' => isset($user['hide_balances']) ? (bool) $user['hide_balances'] : true,
        ];

        return true;
    } catch (Throwable $e) {
        error_log('authenticate error: ' . $e->getMessage());
        return false;
    }
}

function logout(): void
{
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, 
            $params['path'], 
            $params['domain'], 
            $params['secure'], 
            $params['httponly']
        );
    }
    
    session_destroy();
}

