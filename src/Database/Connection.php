<?php

declare(strict_types=1);

namespace Novix\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $config = require __DIR__ . '/../../config/config.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['database'],
            $config['db']['charset']
        );

        try {
            self::$instance = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('DB connection error: ' . $exception->getMessage());
            throw new RuntimeException('Database connection failed.');
        }

        return self::$instance;
    }
}

