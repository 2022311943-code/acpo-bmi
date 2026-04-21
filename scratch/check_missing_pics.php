<?php
require_once 'db_connection.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND (img_front IS NULL OR img_front = '' OR img_right IS NULL OR img_right = '' OR img_left IS NULL OR img_left = '')");
echo "Count: " . $stmt->fetchColumn();
?>
