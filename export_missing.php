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

// Build query to find users WITHOUT a valid BMI record for this month/year
// A valid record means bmi_classification is NOT NULL, not empty, not 'N/A', and not '0'
$sql = "SELECT u.* FROM users u WHERE u.role = 'user'
        AND u.id NOT IN (
            SELECT DISTINCT h.user_id FROM health_records h 
            WHERE MONTH(h.date_taken) = ? AND YEAR(h.date_taken) = ?
            AND h.bmi_classification IS NOT NULL 
            AND h.bmi_classification != '' 
            AND h.bmi_classification != 'N/A' 
            AND h.bmi_classification != '0'
        )";

$params = [$month, $year];

if (!empty($unit_filter)) {
    if ($unit_filter === 'CHQ') {
        $chq_branches = "('ACDEU', 'CIU', 'COMU', 'CIDMU', 'CARMU', 'CPPU', 'CCADU', 'GSO', 'LSO', 'HRAO', 'CPSMU', 'DCBA', 'ODCDO', 'PIO', 'BFO', 'CPHAU', 'OCD', 'OCESPO', 'WCPD', 'HRDD', 'TEU', 'CMFC')";
        $sql .= " AND (u.unit = 'CHQ' OR u.unit IN $chq_branches)";
    } else {
        $sql .= " AND u.unit = ?";
        $params[] = $unit_filter;
    }
}

$sql .= " ORDER BY u.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Missing_BMI_Records_" . $monthName . "_" . $year . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Missing Records</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
</head>
<body>
<table border="1">
    <thead>
        <tr style="background-color: #1700AD; color: white; font-weight: bold;">
            <th colspan="7">PERSONNEL WITH MISSING BMI RECORDS — <?php echo strtoupper($monthName) . " " . $year; ?></th>
        </tr>
        <tr style="background-color: #D9EAF7; font-weight: bold;">
            <th>No.</th>
            <th>Rank</th>
            <th>Last Name</th>
            <th>First Name</th>
            <th>Middle Name</th>
            <th>Suffix</th>
            <th>Unit/Office</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $count = 1;
        foreach ($users as $user) {
            // Clean Rank: extract acronym from parentheses
            $rank = $user['rank'] ?? '';
            if (preg_match('/\((.*?)\)/', $rank, $matches)) {
                $rank = $matches[1];
            }
            
            $display_unit = (!empty($user['unit']) && $user['unit'] !== 'No Unit') ? $user['unit'] : 'No Unit';
            
            echo "<tr>";
            echo "<td>" . $count++ . "</td>";
            echo "<td>" . $rank . "</td>";
            echo "<td>" . ($user['last_name'] ?? '') . "</td>";
            echo "<td>" . ($user['first_name'] ?? '') . "</td>";
            echo "<td>" . ($user['middle_name'] ?? '') . "</td>";
            echo "<td>" . ($user['suffix'] ?? '') . "</td>";
            echo "<td>" . $display_unit . "</td>";
            echo "</tr>";
        }
        ?>
    </tbody>
    <tfoot>
        <tr style="background-color: #F8D7DA; font-weight: bold;">
            <td colspan="7">Total Missing: <?php echo count($users); ?> personnel</td>
        </tr>
    </tfoot>
</table>
</body>
</html>
