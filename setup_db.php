<?php
try {
    $config = require __DIR__ . '/config.php';
    $dbConfig = $config['db'];

    // Connect without dbname first to create it
    $pdo = new PDO("mysql:host={$dbConfig['host']}", $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/database.sql');

    $pdo->exec($sql);

    echo "Database setup completed successfully.\n";
} catch (Exception $e) {
    echo "Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
}
