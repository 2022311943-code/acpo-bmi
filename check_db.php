<?php
require 'db_connection.php';
$stmt = $pdo->query("DESCRIBE health_records");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
