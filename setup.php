<?php
require_once __DIR__ . '/db.php';
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(32) PRIMARY KEY,
        a_movies TEXT NULL,
        b_movies TEXT NULL
    )");
    
    $pdo->query("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Setup complete!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
