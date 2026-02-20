<?php

namespace Core;

use PDO;
use PDOException;
use App\Exceptions\DatabaseException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }

        return self::$instance;
    }

    private static function connect(): PDO
    {
        $config = require ROOT_PATH . '/config/database.php';

        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            return new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new DatabaseException('Database connection failed: ' . $e->getMessage());
        }
    }

    // Prevent cloning
    private function __clone() {}
}
