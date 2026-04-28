<?php
require_once dirname(__DIR__) . '/db_connection.php';

try {
    // Query to find duplicate first_name and last_name combinations
    $sql = "SELECT first_name, last_name, COUNT(*) as duplicate_count, 
                   GROUP_CONCAT(id SEPARATOR ', ') as ids,
                   GROUP_CONCAT(username SEPARATOR ', ') as usernames,
                   GROUP_CONCAT(unit SEPARATOR ', ') as units
            FROM users 
            WHERE first_name != '' AND last_name != ''
            GROUP BY first_name, last_name
            HAVING COUNT(*) > 1
            ORDER BY duplicate_count DESC";

    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== DUPLICATE ACCOUNTS CHECK (SAME FIRST & LAST NAME) ===\n";
    echo "Total Groups with Duplicates: " . count($duplicates) . "\n\n";

    if (count($duplicates) > 0) {
        foreach ($duplicates as $row) {
            echo "Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
            echo "Count: " . $row['duplicate_count'] . "\n";
            echo "IDs: " . $row['ids'] . "\n";
            echo "Usernames: " . $row['usernames'] . "\n";
            echo "Units: " . $row['units'] . "\n";
            echo "--------------------------------------------------\n";
        }
    } else {
        echo "No duplicate accounts found based on first and last name.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
