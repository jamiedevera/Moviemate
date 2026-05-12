<?php
require_once __DIR__ . '/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(32) PRIMARY KEY,
        a_movies TEXT NULL,
        b_movies TEXT NULL
    )");
    echo "Setup complete!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
