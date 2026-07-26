<?php

declare(strict_types=1);

namespace Novix\Repositories;

use PDO;

final class UserRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function findById(int $id): ?array
    {
        // v3.1 Task 5: select hide_balances too. Using SELECT * + filtering
        // in PHP because the column may not exist until migration
        // 20240101000005_add_hide_balances_v3.sql is applied. The simple
        // SELECT-list approach risked a PDO error if the migration was
        // pending; SELECT * is robust to additions across versions.
        $stmt = $this->connection->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(string $name, string $email, string $passwordHash): array
    {
        $stmt = $this->connection->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
        ]);

        $id = (int) $this->connection->lastInsertId();

        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => null,
            'level' => 'Iniciante',
            'points' => 0,
            'badges' => null,
        ];
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $stmt = $this->connection->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }

    public function updateProgress(int $id, int $points, string $level, ?array $badges = null): void
    {
        $stmt = $this->connection->prepare('UPDATE users SET points = :points, level = :level, badges = :badges WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'points' => $points,
            'level' => $level,
            'badges' => $badges ? json_encode($badges, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}

