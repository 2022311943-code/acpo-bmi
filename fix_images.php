<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

$host = '127.0.0.1';
$db   = 'bmi';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function fixBackground($base64Data) {
    if (empty($base64Data) || strpos($base64Data, 'data:image/') !== 0) return $base64Data;
    
    $parts = explode(',', $base64Data);
    if (!isset($parts[1])) return $base64Data;
    
    $data = base64_decode($parts[1]);
    $img = @imagecreatefromstring($data);
    if (!$img) return $base64Data;
    
    $w = imagesx($img);
    $h = imagesy($img);
    
    // Look at top-left, top-right, bottom-left, bottom-right
    $corners = [[0, 0], [$w-1, 0], [0, $h-1], [$w-1, $h-1]];
    
    $needsFix = false;
    foreach ($corners as $c) {
        if ($c[0] < 0 || $c[1] < 0) continue;
        $rgb = imagecolorat($img, $c[0], $c[1]);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        if ($r <= 25 && $g <= 25 && $b <= 25) {
            $needsFix = true;
            break;
        }
    }
    
    if (!$needsFix) {
        imagedestroy($img);
        return $base64Data;
    }
    
    $white = imagecolorallocate($img, 255, 255, 255);
    
    // BFS configuration
    // We will use SplQueue for speed
    $visited = new SplFixedArray($w);
    for($i=0; $i<$w; $i++) {
        $visited[$i] = new SplFixedArray($h);
    }
    
    $queue = new SplQueue();
    
    // Seed queue with black corners
    foreach ($corners as $c) {
        $cx = $c[0]; $cy = $c[1];
        if ($cx >= 0 && $cy >= 0) {
            $rgb = imagecolorat($img, $cx, $cy);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r <= 25 && $g <= 25 && $b <= 25) {
                if (!$visited[$cx][$cy]) {
                    $visited[$cx][$cy] = true;
                    $queue->enqueue([$cx, $cy]);
                }
            }
        }
    }
    
    $tolerance = 30; // JPEG artifacts can make background off-black
    
    while (!$queue->isEmpty()) {
        $p = $queue->dequeue();
        $px = $p[0];
        $py = $p[1];
        
        imagesetpixel($img, $px, $py, $white);
        
        $neighbors = [
            [$px+1, $py], [$px-1, $py],
            [$px, $py+1], [$px, $py-1]
        ];
        
        foreach ($neighbors as $n) {
            $nx = $n[0]; $ny = $n[1];
            if ($nx >= 0 && $nx < $w && $ny >= 0 && $ny < $h) {
                if (!$visited[$nx][$ny]) {
                    $visited[$nx][$ny] = true;
                    $n_rgb = imagecolorat($img, $nx, $ny);
                    $nr = ($n_rgb >> 16) & 0xFF;
                    $ng = ($n_rgb >> 8) & 0xFF;
                    $nb = $n_rgb & 0xFF;
                    
                    if ($nr <= $tolerance && $ng <= $tolerance && $nb <= $tolerance) {
                        $queue->enqueue([$nx, $ny]);
                    }
                }
            }
        }
    }
    
    ob_start();
    imagejpeg($img, null, 75);
    $newData = ob_get_clean();
    imagedestroy($img);
    
    return 'data:image/jpeg;base64,' . base64_encode($newData);
}

echo "Starting image fix process...\n";
$stmt = $pdo->query("SELECT id, img_right, img_front, img_left FROM users WHERE img_right IS NOT NULL OR img_front IS NOT NULL OR img_left IS NOT NULL");

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ir = fixBackground($row['img_right']);
    $if = fixBackground($row['img_front']);
    $il = fixBackground($row['img_left']);
    if ($ir !== $row['img_right'] || $if !== $row['img_front'] || $il !== $row['img_left']) {
        $up = $pdo->prepare("UPDATE users SET img_right=?, img_front=?, img_left=? WHERE id=?");
        $up->execute([$ir, $if, $il, $row['id']]);
        echo "Updated User ID: {$row['id']}\n";
        $count++;
    }
}
echo "Done processing images. Total users updated: $count\n";
?>
