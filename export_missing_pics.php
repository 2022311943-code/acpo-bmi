<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$unit_filter = isset($_GET['unit']) ? $_GET['unit'] : '';

$monthName = date('F', mktime(0, 0, 0, (int)$month, 1));

// Query to find personnel who HAVE a BMI record this month but are missing pictures
$sql = "SELECT u.name, u.rank, u.unit 
        FROM users u 
        INNER JOIN health_records h ON u.id = h.user_id 
        WHERE u.role = 'user' 
        AND MONTH(h.date_taken) = ? AND YEAR(h.date_taken) = ?
        AND (u.img_front IS NULL OR u.img_front = '') 
        AND (u.img_right IS NULL OR u.img_right = '') 
        AND (u.img_left IS NULL OR u.img_left = '')";

$params = [$month, $year];

if (!empty($unit_filter)) {
    $sql .= " AND u.unit = ?";
    $params[] = $unit_filter;
}

$sql .= " GROUP BY u.id ORDER BY u.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Custom Sort for Export: CHQ, PS1, PS2, PS3, PS4, PS5, PS6, then the rest
$priority_units = ['CHQ', 'PS1', 'PS2', 'PS3', 'PS4', 'PS5', 'PS6'];
usort($users, function($a, $b) use ($priority_units) {
    $unitA = $a['unit'] ?? '';
    $unitB = $b['unit'] ?? '';
    
    $posA = array_search($unitA, $priority_units);
    $posB = array_search($unitB, $priority_units);
    
    if ($posA !== false && $posB !== false) return $posA - $posB;
    if ($posA !== false) return -1;
    if ($posB !== false) return 1;
    
    // Within the same non-priority group, sort by Unit then Name
    $unitCmp = strcmp($unitA, $unitB);
    if ($unitCmp !== 0) return $unitCmp;
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// Set headers for Excel download
$filename = "Missing_BMI_Pictures_";
if (!empty($unit_filter)) {
    $filename .= str_replace(' ', '_', $unit_filter) . "_";
}
$filename .= $monthName . "_" . $year . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<table border="1">
    <thead>
        <tr style="background-color: #dc3545; color: white; font-weight: bold;">
            <th colspan="4">PERSONNEL WITH BMI RECORDS BUT MISSING FORM PICTURES — <?php echo strtoupper($monthName) . " " . $year; ?></th>
        </tr>
        <tr style="background-color: #F8D7DA; font-weight: bold;">
            <th>No.</th>
            <th>Name of Personnel</th>
            <th>Rank</th>
            <th>Unit</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $count = 1;
        foreach ($users as $user) {
            // Clean Rank: extract acronym from parentheses if exists
            $rank = $user['rank'] ?? '';
            if (preg_match('/\((.*?)\)/', $rank, $matches)) {
                $rank = $matches[1];
            }
            
            echo "<tr>";
            echo "<td>" . $count++ . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($rank) . "</td>";
            echo "<td>" . htmlspecialchars($user['unit'] ?? 'No Unit') . "</td>";
            echo "</tr>";
        }
        if (empty($users)) {
            echo "<tr><td colspan='4' style='text-align:center;'>No personnel missing pictures for this period.</td></tr>";
        }
        ?>
    </tbody>
</table>
</body>
</html>
