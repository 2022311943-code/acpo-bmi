<?php
require_once 'db_connection.php';

try {
    // Add nationality column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) AFTER birthday");
    echo "Added nationality column.<br>";

    // Add address column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT AFTER nationality");
    echo "Added address column.<br>";

    // Add religion column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS religion VARCHAR(50) AFTER address");
    echo "Added religion column.<br>";

    // Add contact column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS contact VARCHAR(20) AFTER religion");
    echo "Added contact column.<br>";

    // Add email column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER contact");
    echo "Added email column.<br>";

    // Add profile_pic column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic LONGTEXT AFTER email");
    echo "Added profile_pic column.<br>";

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>