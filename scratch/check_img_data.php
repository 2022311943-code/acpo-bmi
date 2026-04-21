<?php
require_once 'db_connection.php';
$stmt = $pdo->query("SELECT id, name, img_front, img_right, img_left FROM users WHERE role = 'user' LIMIT 10");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . PHP_EOL;
    echo "  Front: " . (empty($row['img_front']) ? "EMPTY" : substr($row['img_front'], 0, 30) . "...") . PHP_EOL;
    echo "  Right: " . (empty($row['img_right']) ? "EMPTY" : substr($row['img_right'], 0, 30) . "...") . PHP_EOL;
    echo "  Left:  " . (empty($row['img_left']) ? "EMPTY" : substr($row['img_left'], 0, 30) . "...") . PHP_EOL;
}
?>
