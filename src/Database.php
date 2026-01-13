<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private ?PDO $connection = null;

    public function __construct()
    {
        $config = require __DIR__ . '/../config.php';
        $dbConfig = $config['db'];

        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            // For production, log error and show generic message. For dev, show error.
            die("Database Connect Error: " . $e->getMessage());
        }
    }

    public function getConnection(): ?PDO
    {
        return $this->connection;
    }

    public function getFileDefinition(string $originalName): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM file_definitions WHERE original_name = :name LIMIT 1");
        $stmt->execute(['name' => $originalName]);
        $result = $stmt->fetch();

        return $result ?: null;
    }
}
