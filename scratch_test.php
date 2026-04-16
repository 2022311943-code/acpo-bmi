<?php
$host = '127.0.0.1';
$db   = 'bmi';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

$stmt = $pdo->query("SELECT id, img_front FROM users WHERE img_front IS NOT NULL AND img_front != '' LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    if (strpos($row['img_front'], 'data:image/') === 0) {
        $parts = explode(',', $row['img_front']);
        $data = base64_decode($parts[1]);
        file_put_contents('scratch/test_img.jpg', $data);
        echo "Saved to scratch/test_img.jpg\n";
    }
}
?>
