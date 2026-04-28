<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$unit_filter = isset($_GET['unit']) ? $_GET['unit'] : '';
$cat_filter = isset($_GET['cat']) ? $_GET['cat'] : '';

$monthName = date('F', mktime(0, 0, 0, (int)$month, 1));

// CHQ Sub-Units: These branches are part of CHQ for tracking/filtering purposes
$chq_sub_units = ['CHQ', 'ACDEU', 'CIU', 'COMU', 'CIDMU', 'CARMU', 'CPPU', 'CCADU', 'GSO', 'LSO', 'HRAO', 'CPSMU', 'DCBA', 'ODCDO', 'PIO', 'BFO', 'CPHAU', 'OCD', 'OCESPO', 'WCPD', 'HRDD', 'TEU', 'CMFC'];

// Optimized logic to match main.php's deficiency tracking
$sql = "SELECT u.id, u.name, u.rank, u.unit, u.img_front, u.img_right, u.img_left,
               (CASE WHEN hr.user_id IS NOT NULL THEN 1 ELSE 0 END) as has_monthly_record
        FROM users u 
        LEFT JOIN (
            SELECT DISTINCT user_id FROM health_records 
            WHERE MONTH(date_taken) = ? AND YEAR(date_taken) = ?
            AND bmi_classification IS NOT NULL AND bmi_classification != '' AND bmi_classification != 'N/A' AND bmi_classification != '0'
        ) hr ON u.id = hr.user_id
        WHERE u.role = 'user'";

$params = [$month, $year];

if (!empty($unit_filter)) {
    if ($unit_filter === 'CHQ') {
        $chq_placeholders = implode(',', array_fill(0, count($chq_sub_units), '?'));
        $sql .= " AND u.unit IN ($chq_placeholders)";
        $params = array_merge($params, $chq_sub_units);
    } else {
        $sql .= " AND u.unit = ?";
        $params[] = $unit_filter;
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = [];
foreach ($raw_users as $u) {
    $hasPics = (!empty($u['img_front']) || !empty($u['img_right']) || !empty($u['img_left']));
    $hasRec = (int)$u['has_monthly_record'];
    
    if ($hasPics && $hasRec) continue; // Fully compliant
    
    $def_cat = '';
    if (!$hasRec && !$hasPics) $def_cat = 'missing_all';
    elseif ($hasRec && !$hasPics) $def_cat = 'missing_pics';
    elseif (!$hasRec && $hasPics) $def_cat = 'missing_record';
    
    if (empty($cat_filter) || $cat_filter == $def_cat) {
        $u['def_label'] = strtoupper(str_replace('_', ' ', $def_cat));
        $users[] = $u;
    }
}

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
    
    $unitCmp = strcmp($unitA, $unitB);
    if ($unitCmp !== 0) return $unitCmp;
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// Set headers for Excel download
$unit_label = !empty($unit_filter) ? str_replace(' ', '_', $unit_filter) : "ALL_UNITS";
$cat_label = !empty($cat_filter) ? "_" . strtoupper($cat_filter) : "";
$filename = $unit_label . $cat_label . "_Missing_Data.xls";

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
            <th colspan="5">PERSONNEL RECORD SUBMISSION DEFICIENCY — <?php echo strtoupper($monthName) . " " . $year; ?></th>
        </tr>
        <tr style="background-color: #F8D7DA; font-weight: bold;">
            <th>No.</th>
            <th>Name of Personnel</th>
            <th>Rank</th>
            <th>Unit</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $count = 1;
        foreach ($users as $user) {
            $rank = $user['rank'] ?? '';
            if (preg_match('/\((.*?)\)/', $rank, $matches)) {
                $rank = $matches[1];
            }
            
            echo "<tr>";
            echo "<td>" . $count++ . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($rank) . "</td>";
            echo "<td>" . htmlspecialchars($user['unit'] ?? 'No Unit') . "</td>";
            echo "<td>" . $user['def_label'] . "</td>";
            echo "</tr>";
        }
        if (empty($users)) {
            echo "<tr><td colspan='5' style='text-align:center;'>No personnel found for this criteria.</td></tr>";
        }
        ?>
    </tbody>
</table>
</body>
</html>
