<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$start_month = isset($_GET['start_month']) ? $_GET['start_month'] : date('m');
$start_year = isset($_GET['start_year']) ? $_GET['start_year'] : date('Y');
$end_month = isset($_GET['end_month']) ? $_GET['end_month'] : date('m');
$end_year = isset($_GET['end_year']) ? $_GET['end_year'] : date('Y');
$unit_filter = isset($_GET['unit_filter']) ? $_GET['unit_filter'] : 'ALL';
$record_filter = isset($_GET['record_filter']) ? $_GET['record_filter'] : 'ALL';

$start_date = "$start_year-$start_month-01";
$end_date = date('Y-m-t', strtotime("$end_year-$end_month-01"));

// Calculate months in range
$period_start = new DateTime($start_date);
$period_end = new DateTime($end_date);
$interval = new DateInterval('P1M');
$period = new DatePeriod($period_start, $interval, $period_end->modify('+1 day'));

$months_in_range = [];
foreach ($period as $dt) {
    $months_in_range[] = [
        'label' => $dt->format('M'),
        'key' => $dt->format('Y-m')
    ];
}

// Set headers for Excel download
// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Personnel_Health_Records_" . date('Ymd') . ".xls");
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
                    <x:Name>Health Records</x:Name>
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
        <tr>
            <th rowspan="2">No.</th>
            <th rowspan="2">Rank</th>
            <th rowspan="2">Last Name</th>
            <th rowspan="2">First Name</th>
            <th rowspan="2">Middle Name</th>
            <th rowspan="2">Suffix</th>
            <th rowspan="2">Height (m)</th>
            <th rowspan="2">Waist (cm)</th>
            <th rowspan="2">Hips (cm)</th>
            <th rowspan="2">Wrist (cm)</th>
            <?php 
            $m_idx = 0;
            foreach ($months_in_range as $month): 
                $bgColor = ($m_idx % 2 == 0) ? '#D9EAF7' : '#FFFFFF';
                $m_idx++;
            ?>
                <th colspan="3" style="background-color: <?php echo $bgColor; ?>;"><?php echo strtoupper($month['label']); ?></th>
            <?php endforeach; ?>
            <th rowspan="2">Unit</th>
            <th rowspan="2">Remarks</th>
        </tr>
        <tr>
            <?php 
            $m_idx = 0;
            foreach ($months_in_range as $month): 
                $bgColor = ($m_idx % 2 == 0) ? '#D9EAF7' : '#FFFFFF';
                $m_idx++;
            ?>
                <th style="background-color: <?php echo $bgColor; ?>;">Weight</th>
                <th style="background-color: <?php echo $bgColor; ?>;">BMI</th>
                <th style="background-color: <?php echo $bgColor; ?>;">BMI Classification</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        // Fetch users (filtered by unit if specified)
        if ($unit_filter === 'NO_UNIT') {
            $stmt_users = $pdo->query("SELECT * FROM users WHERE role = 'user' AND (unit IS NULL OR unit = '' OR unit = 'No Unit') ORDER BY name ASC");
        } elseif ($unit_filter !== 'ALL') {
            if ($unit_filter === 'CHQ') {
                $chq_branches = "('ACDEU', 'CIU', 'COMU', 'CIDMU', 'CARMU', 'CPPU', 'CCADU', 'GSO', 'LSO', 'HRAO', 'CPSMU', 'DCBA', 'ODCDO', 'PIO', 'BFO', 'CPHAU', 'OCD', 'OCESPO', 'WCPD', 'HRDD', 'TEU', 'CMFC')";
                $stmt_users = $pdo->query("SELECT * FROM users WHERE role = 'user' AND (unit = 'CHQ' OR unit IN $chq_branches) ORDER BY name ASC");
            } else {
                $stmt_users = $pdo->prepare("SELECT * FROM users WHERE role = 'user' AND unit = ? ORDER BY name ASC");
                $stmt_users->execute([$unit_filter]);
            }
        } else {
            $stmt_users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY name ASC");
        }
        $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 1;
        foreach ($users as $user) {
            // Fetch the latest health record for general data (height, waist, etc.) within or before the end date
            $stmt_latest = $pdo->prepare("SELECT height, waist, hip, wrist, intervention_package FROM health_records WHERE user_id = ? AND date_taken <= ? ORDER BY date_taken DESC LIMIT 1");
            $stmt_latest->execute([$user['id'], $end_date]);
            $latest = $stmt_latest->fetch(PDO::FETCH_ASSOC);
            
            // Map health data to months
            $health_map = [];
            $stmt_monthly = $pdo->prepare("SELECT weight, bmi_result, bmi_classification, DATE_FORMAT(date_taken, '%Y-%m') as ym 
                                          FROM health_records 
                                          WHERE user_id = ? AND date_taken BETWEEN ? AND ?");
            $stmt_monthly->execute([$user['id'], $start_date, $end_date]);
            while ($row = $stmt_monthly->fetch(PDO::FETCH_ASSOC)) {
                $health_map[$row['ym']] = $row;
            }

            // Evaluate Record Filter
            if ($record_filter !== 'ALL') {
                $has_missing = false;
                foreach ($months_in_range as $month) {
                    $m_data = $health_map[$month['key']] ?? null;
                    $bmi_class = $m_data['bmi_classification'] ?? '';
                    if (empty($bmi_class) || $bmi_class === 'N/A' || $bmi_class === '0') {
                        $has_missing = true;
                        break;
                    }
                }
                
                if ($record_filter === 'DEFICIENT' && !$has_missing) {
                    continue; // Skip because user has no missing records
                }
                if ($record_filter === 'COMPLETED' && $has_missing) {
                    continue; // Skip because user has missing records
                }
            }

            echo "<tr>";
            echo "<td>" . $count++ . "</td>";
            
            // Clean Rank: extract acronym from parentheses
            $rank = $user['rank'] ?? '';
            if (preg_match('/\((.*?)\)/', $rank, $matches)) {
                $rank = $matches[1];
            }
            echo "<td>" . $rank . "</td>";
            
            echo "<td>" . ($user['last_name'] ?? '') . "</td>";
            echo "<td>" . ($user['first_name'] ?? '') . "</td>";
            echo "<td>" . ($user['middle_name'] ?? '') . "</td>";
            echo "<td>" . ($user['suffix'] ?? '') . "</td>";
            echo "<td>" . ($latest['height'] ?? '') . "</td>";
            echo "<td>" . ($latest['waist'] ?? '') . "</td>";
            echo "<td>" . ($latest['hip'] ?? '') . "</td>";
            echo "<td>" . ($latest['wrist'] ?? '') . "</td>";
            
            $m_idx = 0;
            foreach ($months_in_range as $month) {
                $bgColor = ($m_idx % 2 == 0) ? '#D9EAF7' : '#FFFFFF';
                $m_idx++;
                $m_data = $health_map[$month['key']] ?? null;
                echo "<td style='background-color: $bgColor;'>" . ($m_data['weight'] ?? '') . "</td>";
                echo "<td style='background-color: $bgColor;'>" . ($m_data['bmi_result'] ?? '') . "</td>";
                echo "<td style='background-color: $bgColor;'>" . ($m_data['bmi_classification'] ?? '') . "</td>";
            }
            
            $display_unit = (!empty($user['unit']) && $user['unit'] !== 'No Unit') ? $user['unit'] : 'No Unit';
            echo "<td>" . $display_unit . "</td>";
            echo "<td>" . ($latest['intervention_package'] ?? '') . "</td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>
</body>
</html>
