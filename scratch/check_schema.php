<?php
require_once 'db_connection.php';
$stmt = $pdo->query('DESCRIBE health_records');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
?>
