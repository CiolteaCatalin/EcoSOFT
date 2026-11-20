<?php

$db = require '/home/backend.ecoautofin.shop/private/database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $db['host'] .
        ';port=' . ($db['port'] ?? 3306) .
        ';dbname=' . $db['database'] .
        ';charset=' . ($db['charset'] ?? 'utf8mb4'),
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    exit('Database connection failed.');
}