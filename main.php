<?php
// main.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Basic role check: if not logged in, redirect to index with note
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Not so fast";
    header("Location: index.php");
    exit;
}
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$users_list = [];

// Handle Edit User Form Submission (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $edit_user_id = $_POST['user_id'];
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $suffix = trim($_POST['suffix']);
    $rank = trim($_POST['rank']);
    $birthday = $_POST['birthday'];
    $gender = $_POST['gender'];
    $nationality = trim($_POST['nationality']);
    $address = trim($_POST['address']);
    $religion = trim($_POST['religion']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $unit_input = $_POST['unit'] ?? '';
    $unit = is_array($unit_input) ? implode(", ", array_map('trim', array_filter($unit_input))) : trim($unit_input);

    // Construct full name for legacy 'name' column
    $name_parts = [];
    if (!empty($last_name)) $name_parts[] = $last_name . ",";
    if (!empty($first_name)) $name_parts[] = $first_name;
    if (!empty($middle_name)) $name_parts[] = $middle_name;
    if (!empty($suffix)) $name_parts[] = $suffix;
    $name = trim(implode(" ", $name_parts));

    // Calculate age if birthday changes
    $age = 0;
    if ($birthday) {
        $dob = new DateTime($birthday);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
    }

    try {
        require_once 'logger.php';
        
        // Fetch current data to compare (Optional, but better to see what changed)
        $stmt_old = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_old->execute([$edit_user_id]);
        $old_data = $stmt_old->fetch();

        $stmt = $pdo->prepare("UPDATE users SET last_name=?, first_name=?, middle_name=?, suffix=?, name=?, rank=?, gender=?, birthday=?, age=?, nationality=?, address=?, religion=?, contact=?, email=?, unit=? WHERE id=?");
        if ($stmt->execute([$last_name, $first_name, $middle_name, $suffix, $name, $rank, $gender, $birthday, $age, $nationality, $address, $religion, $contact, $email, $unit, $edit_user_id])) {
            
            // Build Change Details
            $changes = [];
            if ($old_data['first_name'] != $first_name) $changes[] = "First Name: {$old_data['first_name']} -> $first_name";
            if ($old_data['last_name'] != $last_name) $changes[] = "Last Name: {$old_data['last_name']} -> $last_name";
            if ($old_data['rank'] != $rank) $changes[] = "Rank: {$old_data['rank']} -> $rank";
            if ($old_data['birthday'] != $birthday) $changes[] = "Birthday: {$old_data['birthday']} -> $birthday";
            if ($old_data['unit'] != $unit) $changes[] = "Unit: {$old_data['unit']} -> $unit";
            if ($old_data['address'] != $address) $changes[] = "Address: {$old_data['address']} -> $address";

            if (!empty($changes)) {
                logAction($_SESSION['user_id'], $edit_user_id, 'Update User Info', implode(", ", $changes));
            }

            $_SESSION['flash_success'] = "User details updated successfully!";
        } else {
            $_SESSION['flash_error'] = "Failed to update user details.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
    }
    
    $redirect_params = $_POST['redirect_params'] ?? '';
    header("Location: main.php" . ($redirect_params ? "?" . $redirect_params : ""));
    exit;
}

// Handle Delete User (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $delete_user_id = $_POST['user_id'];
    
    try {
        require_once 'logger.php';
        $pdo->beginTransaction();

        // 1. Get user name for logging
        $stmt_user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt_user->execute([$delete_user_id]);
        $user_name = $stmt_user->fetchColumn();

        // 2. Delete Health Records first
        $stmt_hr = $pdo->prepare("DELETE FROM health_records WHERE user_id = ?");
        $stmt_hr->execute([$delete_user_id]);

        // 3. Delete User
        $stmt_u = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt_u->execute([$delete_user_id])) {
            logAction($_SESSION['user_id'], $delete_user_id, 'Delete User Permanently', "Admin deleted personnel $user_name and all associated records.");
            $pdo->commit();
            $_SESSION['flash_success'] = "User $user_name has been permanently deleted.";
        } else {
            throw new Exception("Failed to delete user.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    
    $redirect_params = $_POST['redirect_params'] ?? '';
    header("Location: main.php" . ($redirect_params ? "?" . $redirect_params : ""));
    exit;
}

// Handle Bulk Delete (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_multiple_users') {
    $user_ids = $_POST['user_ids'] ?? [];
    if (!empty($user_ids)) {
        try {
            require_once 'logger.php';
            $pdo->beginTransaction();
            $count = 0;
            foreach ($user_ids as $uid) {
                // Delete Health Records first
                $stmt_hr = $pdo->prepare("DELETE FROM health_records WHERE user_id = ?");
                $stmt_hr->execute([$uid]);
                
                // Get user name for logging
                $stmt_u_info = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt_u_info->execute([$uid]);
                $user_name = $stmt_u_info->fetchColumn();

                // Delete User
                $stmt_u = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt_u->execute([$uid])) {
                    logAction($_SESSION['user_id'], $uid, 'Bulk Delete User', "Admin deleted personnel $user_name in a bulk operation.");
                    $count++;
                }
            }
            $pdo->commit();
            $_SESSION['flash_success'] = "Successfully deleted $count personnel and their associated records.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = "Bulk Delete Error: " . $e->getMessage();
        }
    }
    header("Location: main.php" . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")); 
    exit;
}

if ($is_admin) {
    // Handle Month/Year Filtering for Masterlist (to check status)
    $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $selected_status = isset($_GET['status']) ? $_GET['status'] : '';
    $selected_unit = isset($_GET['unit']) ? $_GET['unit'] : '';
    $selected_gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $selected_search = isset($_GET['q']) ? $_GET['q'] : '';
    $selected_bmi = isset($_GET['bmi_class']) ? $_GET['bmi_class'] : '';
    $selected_rank_filter = isset($_GET['rank_filter']) ? $_GET['rank_filter'] : '';
    $selected_sort = isset($_GET['sort']) ? $_GET['sort'] : '';
    
    // Fetch users sorted by rank, and also check if they have a record for the selected month/year
    $rank_order = [
        'Police General (PGEN)',
        'Police Lieutenant General (PLTGEN)',
        'Police Major General (PMGEN)',
        'Police Brigadier General (PBGEN)',
        'Police Colonel (PCOL)',
        'Police Lieutenant Colonel (PLTCOL)',
        'Police Major (PMAJ)',
        'Police Captain (PCPT)',
        'Police Lieutenant (PLT)',
        'Police Executive Master Sergeant (PEMS)',
        'Police Chief Master Sergeant (PCMS)',
        'Police Senior Master Sergeant (PSMS)',
        'Police Master Sergeant (PMSg)',
        'Police Staff Sergeant (PSSg)',
        'Police Corporal (PCpl)',
        'Patrolman / Patrolwoman (Pat)'
    ];

    // Optimize query: Avoid O(N) correlated subqueries, avoid fetching massive base64 images (img_right, img_front, img_left)
    $sql = "SELECT u.id, u.last_name, u.first_name, u.middle_name, u.suffix, u.rank, u.gender, u.birthday, u.age, u.nationality, u.address, u.religion, u.contact, u.email, u.unit, u.username, u.name, u.created_at, u.status, u.profile_pic,
            CASE WHEN hr.bmi_classification IS NOT NULL THEN 1 ELSE 0 END as has_record,
            hr.bmi_classification as bmi_class
            FROM users u 
            LEFT JOIN (
                SELECT user_id, bmi_classification
                FROM health_records
                WHERE id IN (
                    SELECT MAX(id)
                    FROM health_records
                    WHERE MONTH(date_taken) = ? AND YEAR(date_taken) = ?
                      AND bmi_classification IS NOT NULL AND bmi_classification != '' AND bmi_classification != 'N/A' AND bmi_classification != '0'
                    GROUP BY user_id
                )
            ) hr ON u.id = hr.user_id
            WHERE u.role = 'user' ORDER BY CASE u.rank ";
    foreach ($rank_order as $index => $rank) {
        $sql .= "WHEN ? THEN " . ($index + 1) . " ";
    }
    $sql .= "ELSE 100 END ASC, name ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$selected_month, $selected_year], $rank_order);
        $stmt->execute($params);
        $users_list = $stmt->fetchAll();

        // --- COMPLIANCE PRESSURE ANALYTICS ---
        $compliance_summary = ['completed' => 0, 'total' => 0, 'rate' => 0];
        $unit_compliance_rankings = [];

        // 1. Completion Rate (Filters by selected unit if applicable)
        $where_total = "u.role = 'user'";
        $where_comp = "u.role = 'user' AND MONTH(h.date_taken) = ? AND YEAR(h.date_taken) = ?";
        $params_total = [];
        $params_comp = [$selected_month, $selected_year];

        if (!empty($selected_unit)) {
            $where_total .= " AND u.unit = ?";
            $where_comp .= " AND u.unit = ?";
            $params_total[] = $selected_unit;
            $params_comp[] = $selected_unit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_total");
        $stmt->execute($params_total);
        $compliance_summary['total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT h.user_id) FROM health_records h JOIN users u ON h.user_id = u.id WHERE $where_comp AND h.bmi_classification IS NOT NULL AND h.bmi_classification != '' AND h.bmi_classification != 'N/A' AND h.bmi_classification != '0'");
        $stmt->execute($params_comp);
        $compliance_summary['completed'] = $stmt->fetchColumn();

        if ($compliance_summary['total'] > 0) {
            $compliance_summary['rate'] = round(($compliance_summary['completed'] / $compliance_summary['total']) * 100);
        }

        // 2. Units Ranking (Always keep top 10 globally for context, OR filter if requested)
        $rank_sort = isset($_GET['rank_sort']) ? $_GET['rank_sort'] : 'nc_count';
        $valid_sorts = ['nc_count', 'sev_uw', 'uw', 'normal', 'acceptable', 'ow', 'obese1', 'obese2', 'obese3'];
        if (!in_array($rank_sort, $valid_sorts)) $rank_sort = 'nc_count';

        $nc_sql = "
            SELECT u.unit, 
                   SUM(CASE WHEN h.bmi_classification NOT IN ('NORMAL', 'ACCEPTABLE BMI', 'N/A', '0', '') AND h.bmi_classification IS NOT NULL THEN 1 ELSE 0 END) as nc_count,
                   SUM(CASE WHEN h.bmi_classification = 'SEVERELY UNDERWEIGHT' THEN 1 ELSE 0 END) as sev_uw,
                   SUM(CASE WHEN h.bmi_classification = 'UNDERWEIGHT' THEN 1 ELSE 0 END) as uw,
                   SUM(CASE WHEN h.bmi_classification = 'NORMAL' THEN 1 ELSE 0 END) as normal,
                   SUM(CASE WHEN h.bmi_classification = 'ACCEPTABLE BMI' THEN 1 ELSE 0 END) as acceptable,
                   SUM(CASE WHEN h.bmi_classification = 'OVERWEIGHT' THEN 1 ELSE 0 END) as ow,
                   SUM(CASE WHEN h.bmi_classification = 'OBESE CLASS 1' THEN 1 ELSE 0 END) as obese1,
                   SUM(CASE WHEN h.bmi_classification = 'OBESE CLASS 2' THEN 1 ELSE 0 END) as obese2,
                   SUM(CASE WHEN h.bmi_classification = 'OBESE CLASS 3' THEN 1 ELSE 0 END) as obese3
            FROM users u
            INNER JOIN health_records h ON u.id = h.user_id
            INNER JOIN (
                SELECT user_id, MAX(id) as latest_id
                FROM health_records
                WHERE MONTH(date_taken) = ? AND YEAR(date_taken) = ?
                GROUP BY user_id
            ) latest ON h.id = latest.latest_id
            WHERE u.role = 'user' 
              AND u.unit IS NOT NULL AND u.unit != ''
        ";
        if (!empty($selected_unit)) {
            $nc_sql .= " AND u.unit = ?";
            $nc_params = [$selected_month, $selected_year, $selected_unit];
        } else {
            $nc_params = [$selected_month, $selected_year];
        }
        $nc_sql .= " GROUP BY u.unit ORDER BY $rank_sort DESC";
        
        $stmt = $pdo->prepare($nc_sql);
        $stmt->execute($nc_params);
        $unit_compliance_rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Helper to get unit color
        function getUnitColor($unitName) {
            $unitMap = [
                'CHQ'   => '#1700ad', // Navy
                'PS1'   => '#0d6efd', // Blue
                'PS2'   => '#6610f2', // Indigo
                'PS3'   => '#6f42c1', // Purple
                'PS4'   => '#d63384', // Pink
                'PS5'   => '#dc3545', // Red
                'PS6'   => '#fd7e14', // Orange
                'CMFC'  => '#ff8c00', // Dark Orange
                'TPU'   => '#198754', // Green
                'MPU'   => '#20c997', // Teal
                'CARMU' => '#ff5722', // Deep Orange
                'CIU'   => '#e91e63', // Pinkish
                'AOMU'  => '#9c27b0', // Purple
                'CCADU' => '#3f51b5', // Indigo
                'ARDDO' => '#2196f3', // Light Blue
                'CPPU'  => '#4caf50', // Bright Green
                'GSO'   => '#795548', // Brown
                'DEU'   => '#607d8b'  // Blue Gray
            ];
            return $unitMap[$unitName] ?? '#6c757d'; // Default gray
        }

    } catch (PDOException $e) {
        $users_list = [];
    }
} else {
    // For Regular Users: Fetch their own data for Health Record display
    $user_data = [];
    $health_data = [];
    $age = 0;

    try {
        // Get User Details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            // Calculate Age
            if ($user_data['birthday']) {
                $dob = new DateTime($user_data['birthday']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
            } else {
                $age = $user_data['age'] ?? 0;
            }

            // Handle Filter Values
            $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
            $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
            $filter_date = "$selected_year-$selected_month-01";

            // Get Health Record for specific month/year (for the main results)
            $stmt = $pdo->prepare("SELECT * FROM health_records WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ? ORDER BY date_taken DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $selected_month, $selected_year]);
            $health_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch ALL historical records to build a complete weight picture
            $stmt = $pdo->prepare("SELECT weight, monthly_weights, date_taken FROM health_records WHERE user_id = ? ORDER BY date_taken ASC");
            $stmt->execute([$user_data['id']]);
            $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $json_weights = [];
            $record_weights = [];
            foreach ($all_records as $rec) {
                // Collect Record Weight (Primary)
                $m_key_rec = date('Y-m', strtotime($rec['date_taken']));
                if (!empty($rec['weight']) && $rec['weight'] > 0) {
                    $record_weights[$m_key_rec] = $rec['weight'];
                }

                // Collect Monitoring Weights (Filler)
                $weights_json = json_decode($rec['monthly_weights'] ?? '[]', true);
                if (is_array($weights_json)) {
                    foreach ($weights_json as $mk => $wv) {
                        if (!empty($wv)) {
                            $json_weights[$mk] = $wv;
                        }
                    }
                }
            }
            // RECORD weights always win over JSON historical weights
            $cumulative_weights = array_merge($json_weights, $record_weights);

            // FILTER correctly for display: Show NO weights AFTER the selected date
            $display_weights = [];
            foreach ($cumulative_weights as $mk => $wv) {
                $check_date = $mk . "-01";
                if (strtotime($check_date) <= strtotime($filter_date)) {
                    $display_weights[$mk] = $wv;
                }
            }
            $monthly_weights = $display_weights;

            // Prepare Progress Chart Data
            $chartMonths = array_keys($cumulative_weights);
            sort($chartMonths);
            
            $latestHeight = $health_data['height'] ?? 0;
            if (!$latestHeight) {
                foreach($all_records as $r) {
                    if (!empty($r['height'])) { $latestHeight = $r['height']; break; }
                }
            }

            $bmiData = [];
            $labels = [];
            foreach ($chartMonths as $m) {
                $w = $cumulative_weights[$m];
                if ($latestHeight > 0) {
                    $hM = $latestHeight / 100;
                    $bmi = $w / ($hM * $hM);
                    $bmiData[] = round($bmi, 2);
                    $labels[] = date('M Y', strtotime($m . "-01"));
                }
            }

            // Age Threshold
            $thresholdVal = 24.9;
            if ($age >= 30 && $age <= 34) $thresholdVal = 25.0;
            elseif ($age >= 35 && $age <= 39) $thresholdVal = 25.5;
            elseif ($age >= 40 && $age <= 44) $thresholdVal = 26.0;
            elseif ($age >= 45 && $age <= 50) $thresholdVal = 26.5;
            elseif ($age >= 51) $thresholdVal = 27.0;

            $thresholdData = array_fill(0, count($bmiData), $thresholdVal);
            $normalLine = array_fill(0, count($bmiData), 24.9);
        }
    } catch (PDOException $e) {
        $monthly_weights = [];
    }
}

// Helper for safe display (same as in editor.php)
function hval($data, $key, $default = '') {
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}
function hval_raw($data, $key, $default = '') {
    return isset($data[$key]) ? $data[$key] : $default;
}
function getRankAcronym($rank) {
    if (preg_match('/\((.*?)\)/', $rank, $matches)) {
        return $matches[1];
    }
    return $rank;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Main Dashboard</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
        $stmt->execute($params_comp);
        $compliance_summary['completed'] = $stmt->fetchColumn();

        if ($compliance_summary['total'] > 0) {
            $compliance_summary['rate'] = round(($compliance_summary['completed'] / $compliance_summary['total']) * 100);
        }

        // 2. Units Ranking (Always keep top 5 globally for context, OR filter if requested)
        // Let's keep it global so the admin can see where they stand, but we could also filter it.
        // The user said "compliance rate... should show of that office only".
        // The list below is "Units with Most Non-Compliant". If one unit is selected, maybe we show only that unit's detail?
        // Let's modify it to only show the selected unit if the filter is active.
        $nc_sql = "
            SELECT u.unit, COUNT(*) as nc_count
            FROM users u
            INNER JOIN health_records h ON u.id = h.user_id
            WHERE u.role = 'user' 
              AND MONTH(h.date_taken) = ? 
              AND YEAR(h.date_taken) = ?
              AND h.bmi_classification NOT IN ('NORMAL', 'ACCEPTABLE BMI')
              AND u.unit IS NOT NULL AND u.unit != ''
        ";
        if (!empty($selected_unit)) {
            $nc_sql .= " AND u.unit = ?";
            $nc_params = [$selected_month, $selected_year, $selected_unit];
        } else {
            $nc_params = [$selected_month, $selected_year];
        }
        $nc_sql .= " GROUP BY u.unit ORDER BY nc_count DESC LIMIT 5";
        
        $stmt = $pdo->prepare($nc_sql);
        $stmt->execute($nc_params);
        $unit_compliance_rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Helper to get unit color
        function getUnitColor($unitName) {
            $unitMap = [
                'CHQ'   => '#1700ad', // Navy
                'PS1'   => '#0d6efd', // Blue
                'PS2'   => '#6610f2', // Indigo
                'PS3'   => '#6f42c1', // Purple
                'PS4'   => '#d63384', // Pink
                'PS5'   => '#dc3545', // Red
                'PS6'   => '#fd7e14', // Orange
                'CMFC'  => '#ff8c00', // Dark Orange
                'TPU'   => '#198754', // Green
                'MPU'   => '#20c997'  // Teal
            ];
            return $unitMap[$unitName] ?? '#6c757d'; // Default gray
        }

    } catch (PDOException $e) {
        $users_list = [];
    }
} else {
    // For Regular Users: Fetch their own data for Health Record display
    $user_data = [];
    $health_data = [];
    $age = 0;

    try {
        // Get User Details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            // Calculate Age
            if ($user_data['birthday']) {
                $dob = new DateTime($user_data['birthday']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
            } else {
                $age = $user_data['age'] ?? 0;
            }

            // Handle Filter Values
            $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
            $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
            $filter_date = "$selected_year-$selected_month-01";

            // Get Health Record for specific month/year (for the main results)
            $stmt = $pdo->prepare("SELECT * FROM health_records WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ? ORDER BY date_taken DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $selected_month, $selected_year]);
            $health_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch ALL historical records to build a complete weight picture
            $stmt = $pdo->prepare("SELECT weight, monthly_weights, date_taken FROM health_records WHERE user_id = ? ORDER BY date_taken ASC");
            $stmt->execute([$user_data['id']]);
            $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $json_weights = [];
            $record_weights = [];
            foreach ($all_records as $rec) {
                // Collect Record Weight (Primary)
                $m_key_rec = date('Y-m', strtotime($rec['date_taken']));
                if (!empty($rec['weight']) && $rec['weight'] > 0) {
                    $record_weights[$m_key_rec] = $rec['weight'];
                }

                // Collect Monitoring Weights (Filler)
                $weights_json = json_decode($rec['monthly_weights'] ?? '[]', true);
                if (is_array($weights_json)) {
                    foreach ($weights_json as $mk => $wv) {
                        if (!empty($wv)) {
                            $json_weights[$mk] = $wv;
                        }
                    }
                }
            }
            // RECORD weights always win over JSON historical weights
            $cumulative_weights = array_merge($json_weights, $record_weights);

            // FILTER correctly for display: Show NO weights AFTER the selected date
            $display_weights = [];
            foreach ($cumulative_weights as $mk => $wv) {
                $check_date = $mk . "-01";
                if (strtotime($check_date) <= strtotime($filter_date)) {
                    $display_weights[$mk] = $wv;
                }
            }
            $monthly_weights = $display_weights;

            // Prepare Progress Chart Data
            $chartMonths = array_keys($cumulative_weights);
            sort($chartMonths);
            
            $latestHeight = $health_data['height'] ?? 0;
            if (!$latestHeight) {
                foreach($all_records as $r) {
                    if (!empty($r['height'])) { $latestHeight = $r['height']; break; }
                }
            }

            $bmiData = [];
            $labels = [];
            foreach ($chartMonths as $m) {
                $w = $cumulative_weights[$m];
                if ($latestHeight > 0) {
                    $hM = $latestHeight / 100;
                    $bmi = $w / ($hM * $hM);
                    $bmiData[] = round($bmi, 2);
                    $labels[] = date('M Y', strtotime($m . "-01"));
                }
            }

            // Age Threshold
            $thresholdVal = 24.9;
            if ($age >= 30 && $age <= 34) $thresholdVal = 25.0;
            elseif ($age >= 35 && $age <= 39) $thresholdVal = 25.5;
            elseif ($age >= 40 && $age <= 44) $thresholdVal = 26.0;
            elseif ($age >= 45 && $age <= 50) $thresholdVal = 26.5;
            elseif ($age >= 51) $thresholdVal = 27.0;

            $thresholdData = array_fill(0, count($bmiData), $thresholdVal);
            $normalLine = array_fill(0, count($bmiData), 24.9);
        }
    } catch (PDOException $e) {
        $monthly_weights = [];
    }
}

// Helper for safe display (same as in editor.php)
function hval($data, $key, $default = '') {
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}
function hval_raw($data, $key, $default = '') {
    return isset($data[$key]) ? $data[$key] : $default;
}
function getRankAcronym($rank) {
    if (preg_match('/\((.*?)\)/', $rank, $matches)) {
        return $matches[1];
    }
    return $rank;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Main Dashboard</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }

        /* ----- Navbar CSS (matching other pages) ----- */
        body {
            background-color: #f8f9fa;
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card, .acpo-nav, .user-sidebar, .offcanvas, .table, .rank-item-bg, main, .progress, .form-control, .form-select {
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        /* Theme reveal animation */
        .theme-transition-active {
            animation: theme-reveal 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes theme-reveal {
            0% { opacity: 0.9; transform: scale(0.995); filter: brightness(1.2); }
            100% { opacity: 1; transform: scale(1); filter: brightness(1); }
        }

        .acpo-blue {
            background-color: #1700ad !important;
        }
        .acpo-nav {
            position: relative;
            z-index: 1030;
            border-bottom: 2px solid #e9ecef;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .acpo-nav .navbar-brand {
            display: flex;
            align-items: center;
        }
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #212529;
            font-family: inherit;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .acpo-nav .acpo-header-text {
            color: #ffffff !important;
        }
        .acpo-nav .nav-link.acpo-header-text:hover {
            color: #e0e0e0 !important;
        }
        .acpo-nav .navbar-nav {
            gap: 2.5rem !important;
        }
        @media (min-width: 992px) {
            .acpo-nav .navbar-nav {
                gap: 5rem !important;
            }
        }
        @media (max-width: 991.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.9rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 45px !important;
            }
            /* Reduce brand spacing on smaller screens */
            .acpo-brand-container {
                margin-left: 0.5rem !important;
                padding-left: 0 !important;
            }
            .acpo-btn-container {
                margin-right: 0.5rem !important;
                padding-right: 0 !important;
            }
            .hamburger-icon {
                font-size: 2rem !important;
            }
        }
        @media (max-width: 575.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.8rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 38px !important;
            }
            .hamburger-icon {
                font-size: 1.8rem !important;
            }
            /* Possible hide brand text if it's still too long on very narrow screens */
            @media (max-width: 400px) {
                .acpo-brand-text {
                    display: none;
                }
            }
        }
        .hamburger-icon {
            font-size: 2.8rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .hamburger-icon:hover {
            color: #e0e0e0 !important;
        }
        .nav-link-underline {
            position: relative;
            display: inline-block;
        }
        .nav-link-underline::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            height: 3px;
            width: 0;
            background: currentColor;
            transition: width 0.35s ease;
        }
        .nav-link-underline:hover::after {
            width: 100%;
        }
        @keyframes activeLoop {
            0% { width: 20%; opacity: 0.5; }
            50% { width: 100%; opacity: 1; }
            100% { width: 20%; opacity: 0.5; }
        }
        .nav-link-underline.active-nav-link::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: -2px;
            height: 3px;
            background: currentColor;
            animation: activeLoop 2.5s ease-in-out infinite;
        }

        /* ----- Table Adaptability ----- */
        .table-masterlist th, .table-masterlist td {
            padding-left: 0.7rem !important;
            padding-right: 0.7rem !important;
        }
        .table-masterlist {
            font-size: 0.95rem;
        }
        .table-masterlist th {
            font-size: 0.88rem;
            white-space: nowrap;
        }
        @media (max-width: 1400px) {
            .table-masterlist {
                font-size: 0.9rem;
            }
        }

        /* ----- BMI Dashboard Table CSS ----- */
        .bmi-container {
            width: 100%;
            max-width: 1300px;
            margin: 2rem auto;
            border: 2px solid #000;
            background-color: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 0.85rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .border-black {
            border-color: #000 !important;
        }

        .bg-lavender {
            background-color: #e2e4ff;
        }

        .bg-purple {
            background-color: #7b85ff;
            color: #fff;
        }

        .small-text {
            font-size: 0.85rem;
        }
        
        .fw-bold {
            font-weight: 700;
        }

        /* Top Block Layout */
        .top-block {
            display: flex;
            width: 100%;
        }

        .left-col {
            width: 40%;
            display: flex;
            flex-direction: column;
        }

        .right-col {
            width: 60%;
            display: flex;
            flex-direction: column;
        }

        /* Left Column Specifics */
        .pics-area {
            display: flex;
            flex-grow: 1;
        }

        .pic-box {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
            padding: 20px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: background-color 0.2s ease;
        }
        
        .pic-box:hover {
            background-color: #d0d3ff;
        }

        .pic-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .pic-box span {
            position: relative;
            z-index: 2;
        }

        .view-label {
            flex: 1;
            padding: 4px 0;
        }

        .classification-box {
            min-height: 80px;
        }

        .class-standard {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 6px;
        }

        /* Right Column Specifics */
        .right-header {
            display: flex;
            padding: 6px 12px;
        }

        .data-row {
            display: flex;
            padding: 4px 12px;
            align-items: center;
        }

        .label-col {
            width: 50%;
        }

        .val-col {
            width: 50%;
        }

        .bottom-right-box {
            display: flex;
            flex-grow: 1;
        }

        .sig-box {
            width: 50%;
            display: flex;
            flex-direction: column;
            padding: 6px 12px;
        }

        /* Bottom Monthly Section */
        .monthly-col-yr {
            width: 15%;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .monthly-col-2025 {
            width: 12.14%;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .monthly-col-2026 {
            flex-grow: 1;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .dashed-arrow {
            position: relative;
            flex-grow: 1;
            border-bottom: 1px dashed #000;
            height: 1px;
            margin: 0 10px;
        }

        .dashed-arrow::after {
            content: '>';
            position: absolute;
            right: -6px;
            top: -9px;
            font-weight: bold;
            font-family: monospace;
            font-size: 16px;
        }
        
        .timeline-month {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }


        /* ----- Hamburger Menu Sidebar CSS ----- */
        .user-sidebar {
            width: 380px !important;
            border-left: none;
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%) !important;
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
            color: #ffffff;
        }
        .user-sidebar .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .user-sidebar .btn-close:hover {
            opacity: 1;
        }
        .user-sidebar .offcanvas-body {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .profile-placeholder {
            width: 100%;
            max-width: 130px;
            aspect-ratio: 1 / 1;
            margin-bottom: 1rem;
            border-radius: 50%;
            padding: 5px;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }
        .profile-placeholder:hover {
            transform: scale(1.05);
        }
        .profile-placeholder img {
            border: 3px solid #ffffff !important;
            border-radius: 50%;
        }
        .sidebar-name {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.6rem;
            color: #ffffff;
            margin-bottom: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            text-align: center;
        }
        .sidebar-links-container {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.8rem;
            width: 100%;
            margin-bottom: auto;
        }
                .sidebar-links-container a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.85) !important;
            text-decoration: none;
            font-family: 'Agrandir', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 8px;
        }
        .sidebar-links-container a i {
            font-size: 1.3rem;
            margin-right: 15px;
            color: #33AFFF;
            transition: transform 0.3s ease;
        }
        .sidebar-links-container a:hover, .sidebar-links-container a.active-sidebar-link {
            background: rgba(255,255,255,0.15);
            color: #ffffff !important;
            transform: translateX(5px);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .sidebar-links-container a:hover i, .sidebar-links-container a.active-sidebar-link i {
            transform: scale(1.2);
            color: #fff;
        }
        .logout-btn-container {
            margin-top: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff !important;
            border-radius: 50px;
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 12px 40px;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.4);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 65, 108, 0.6);
            color: #fff !important;
        }
        .logout-btn i {
            font-size: 1.3rem;
        }
        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-lavender {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .bg-white {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .bg-purple {
            background-color: #3b429f !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .pic-box:hover {
            background-color: #3b429f !important;
        }
        [data-bs-theme="dark"] [style*="background: white"], [data-bs-theme="dark"] [style*="background:white"] {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
        }
        [data-bs-theme="dark"] .pic-box:hover {
            background-color: #3b429f !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }
        .bmi-card-adaptive {
            border: 1px solid #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bmi-card-adaptive {
            border-color: #333 !important;
        }
        [data-bs-theme="dark"] .table {
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .table-striped>tbody>tr:nth-of-type(odd)>* {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.05) !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select, [data-bs-theme="dark"] .input-group-text {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .text-black, [data-bs-theme="dark"] .text-dark, [data-bs-theme="dark"] .text-primary, [data-bs-theme="dark"] .text-secondary {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6, [data-bs-theme="dark"] .acpo-brand-text {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] [style*="color: #1700ad"], [data-bs-theme="dark"] [style*="color: rgb(23, 0, 173)"] {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] [style*="border-color: #1700ad"] {
            border-color: #444 !important;
        }
        [data-bs-theme="dark"] .border-black {
            border-color: #444 !important;
        }
        [data-bs-theme="dark"] .modal-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .acpo-nav {
            border-bottom: none !important;
            box-shadow: none !important;
            position: relative;
            z-index: 1030;
            background: #0d005e !important;
            border-bottom: none !important;
            box-shadow: none !important;
        }
        [data-bs-theme="dark"] .vr {
            background-color: #444 !important;
            opacity: 1;
        }
        [data-bs-theme="dark"] thead.bg-light, [data-bs-theme="dark"] thead {
            background-color: #2d2d2d !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .btn-outline-dark {
            border-color: #666;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .btn-outline-dark:hover {
            background-color: #333;
            color: #fff;
        }

        /* Premium Modern Solar-Lunar Toggle */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
        }
        .theme-switch {
            display: inline-block;
            height: 40px;
            position: relative;
            width: 76px;
            user-select: none;
        }
        .theme-switch input {
            display: none;
        }
        .theme-switch-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            cursor: pointer;
            position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        [data-bs-theme="dark"] .theme-switch-track {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        .theme-switch-knob {
            background: #ffffff;
            border-radius: 50%;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: absolute;
            left: 3px;
            top: 2.5px;
            z-index: 2;
        }
        [data-bs-theme="dark"] .theme-switch-knob {
            transform: translateX(36px);
            background: #2b2e38;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4), 0 0 10px rgba(51, 175, 255, 0.3);
        }
        .theme-switch-knob i {
            font-size: 18px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .sun-icon {
            color: #f39c12;
            transform: rotate(0deg) scale(1);
        }
        .moon-icon {
            color: #33AFFF;
            position: absolute;
            opacity: 0;
            transform: rotate(-90deg) scale(0);
        }
        [data-bs-theme="dark"] .sun-icon {
            opacity: 0;
            transform: rotate(90deg) scale(0);
        }
        [data-bs-theme="dark"] .moon-icon {
            opacity: 1;
            transform: rotate(0deg) scale(1);
        }
        .theme-switch:hover .theme-switch-knob {
            transform: scale(1.05) <?php echo (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') || (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'translateX(34px)' : ''; ?>;
        }
        /* Custom hover behavior for JS calculated state */
        [data-bs-theme="dark"] .theme-switch:hover .theme-switch-knob {
            transform: translateX(36px) scale(1.05);
        }
        .theme-switch:active .theme-switch-knob {
            transform: scale(0.95) <?php echo (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') || (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'translateX(38px)' : ''; ?>;
        }
        [data-bs-theme="dark"] .theme-switch:active .theme-switch-knob {
            transform: translateX(36px) scale(0.95);
        }
        /* Print Styles - MOVED TO END FOR SPECIFICITY */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            body {
                background-color: white !important;
                color: black !important;
                zoom: 80%; /* Scale down to fit Legal-like layout on A4 */
            }
            /* Explicitly override theme-specific dark mode colors during print */
            [data-bs-theme="dark"] body {
                background-color: white !important;
                color: black !important;
            }
            .acpo-nav, 
            .no-print,
            .btn-primary,
            .btn-dark {
                display: none !important;
            }
            .bmi-container {
                background-color: white !important;
                color: black !important;
                box-shadow: none !important;
                border: 2px solid #000 !important;
                margin: 50px auto 0 !important;
                max-width: 100% !important;
                page-break-inside: avoid;
            }
            [data-bs-theme="dark"] .bmi-container {
                background-color: white !important;
                color: black !important;
                border-color: black !important;
            }
            /* Forced black text for all labels and content in print */
            .bg-lavender, [data-bs-theme="dark"] .bg-lavender,
            .bg-purple, [data-bs-theme="dark"] .bg-purple,
            .bg-white, [data-bs-theme="dark"] .bg-white,
            .bmi-container, [data-bs-theme="dark"] .bmi-container,
            .data-row, .label-col, .val-col, .view-label, .small-text,
            h1, h2, h3, h4, h5, h6, .display-4, span, div {
                color: black !important;
            }
            .bg-lavender, [data-bs-theme="dark"] .bg-lavender {
                background-color: #e2e4ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-purple, [data-bs-theme="dark"] .bg-purple {
                background-color: #7b85ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-white, [data-bs-theme="dark"] .bg-white {
                background-color: white !important;
            }
            .border-black, [data-bs-theme="dark"] .border-black {
                border-color: #000 !important;
            }
            .left-col, .right-col, .pic-box,
            [data-bs-theme="dark"] .left-col, 
            [data-bs-theme="dark"] .right-col,
            [data-bs-theme="dark"] .pic-box {
                border-color: #000 !important;
            }
            /* Reset any inline blue colors to black during print */
            [style*="color: #1700ad"], [data-bs-theme="dark"] [style*="color: #1700ad"],
            [style*="color: rgb(23, 0, 173)"], [data-bs-theme="dark"] [style*="color: rgb(23, 0, 173)"] {
                color: black !important;
            }
            main {
                padding: 0 !important;
            }
        }
    
        .rank-item-bg {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
        [data-bs-theme="dark"] .rank-item-bg {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff !important;
        }
        /* ----- Optimized Masterlist Actions Bar ----- */
        .masterlist-actions-bar {
            background: #ffffff;
            border: 1px solid rgba(23, 0, 173, 0.1);
            transition: all 0.3s ease;
        }
        
        [data-bs-theme="dark"] .masterlist-actions-bar {
            background: #1e1e2d !important;
            border-color: rgba(255, 255, 255, 0.05);
        }

        .filter-group {
            display: flex;
            align-items: center;
            padding: 4px 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        [data-bs-theme="dark"] .filter-group {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        .filter-group:focus-within {
            border-color: #1700ad;
            box-shadow: 0 0 0 3px rgba(23, 0, 173, 0.1);
        }

        .filter-group select {
            border: none !important;
            background: transparent !important;
            padding-top: 4px;
            padding-bottom: 4px;
            font-weight: 600;
            color: #1700ad !important;
            box-shadow: none !important;
            cursor: pointer;
        }
        
        [data-bs-theme="dark"] .filter-group select,
        [data-bs-theme="dark"] .filter-group i {
            color: #a29fff !important;
        }

        .search-box .form-control {
            border: 2px solid #e9ecef;
            padding-left: 2.8rem;
            font-weight: 500;
        }

        .search-box .form-control:focus {
            border-color: #1700ad;
            box-shadow: 0 5px 15px rgba(23, 0, 173, 0.1);
        }

        .search-box .search-icon {
            position: absolute;
            top: 50%;
            left: 1.2rem;
            transform: translateY(-50%);
            color: #1700ad;
            font-size: 1.1rem;
            z-index: 5;
        }
        
        [data-bs-theme="dark"] .search-box .search-icon {
            color: #a29fff;
        }

        .action-btn {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.15);
        }

        [data-bs-theme="dark"] .search-box .form-control {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff;
        }

        .shadow-inner {
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06) !important;
        }

        /* Fix sorting dropdown hover text visibility */
        .dropdown-toggle.btn-outline-secondary:hover {
            color: #6c757d !important;
            background-color: rgba(0, 0, 0, 0.05) !important;
        }
        [data-bs-theme="dark"] .dropdown-toggle.btn-outline-secondary:hover {
            color: #adb5bd !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Clickable BMI breakdown styles */
        .bmi-breakdown-badge {
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .bmi-breakdown-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-color: rgba(0,0,0,0.3) !important;
            filter: brightness(0.95);
        }
        .bmi-breakdown-badge:active {
            transform: translateY(0);
        }

        /* Units Scroll Container Styling */
        .units-scroll-container::-webkit-scrollbar {
            width: 6px;
        }
        .units-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .units-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        [data-bs-theme="dark"] .units-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }
        .units-scroll-container::-webkit-scrollbar-thumb:hover {
            background: rgba(23, 0, 173, 0.3);
        }
    </style>
    <script>
        // Apply theme immediately to prevent flicker
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="d-flex flex-column min-vh-100 overflow-x-hidden">

    <!-- Header (same as other pages) -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-2 px-lg-4">
            <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5 acpo-brand-container">
                <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
            </a>
            <div class="d-flex align-items-center justify-content-end me-3 me-lg-5 pe-lg-5 acpo-btn-container">
                                <button class="btn border-0 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar" aria-controls="userSidebar" aria-label="Toggle user menu">
                    <i class="bi bi-list text-white hamburger-icon" style="font-size: 2rem; cursor: pointer; position: relative; "></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hamburger Overlay Menu -->
    <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar" aria-labelledby="userSidebarLabel">
        <div class="offcanvas-header pb-0 border-0">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <!-- Profile Image (Clickable to settings) -->
            <a href="settings.php" class="profile-placeholder d-flex justify-content-center text-decoration-none">
                <?php 
                $sidebar_pfp = "images/placeholder.png";
                if (isset($_SESSION['user_id'])) {
                    $stmt_pfp = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
                    $stmt_pfp->execute([$_SESSION['user_id']]);
                    $pfp_row = $stmt_pfp->fetch();
                    if ($pfp_row && !empty($pfp_row['profile_pic'])) {
                        $sidebar_pfp = $pfp_row['profile_pic'];
                    }
                }
                ?>
                <img src="<?php echo $sidebar_pfp; ?>" alt="Profile" class="img-fluid rounded-circle shadow-sm" style="width: 130px; height: 130px; object-fit: cover; border: 3px solid #fff;" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23e9ecef\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
            </a>
            
            <h4 class="sidebar-name"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h4>
            
            <!-- Dark Mode Switch in Sidebar -->
            <div class="d-flex justify-content-center mb-4">
                    <div class="theme-switch-wrapper">
                        <label class="theme-switch" for="theme-switch-checkbox">
                            <input type="checkbox" id="theme-switch-checkbox">
                            <div class="theme-switch-track">
                                <div class="theme-switch-knob">
                                    <i class="bi bi-sun-fill sun-icon"></i>
                                    <i class="bi bi-moon-stars-fill moon-icon"></i>
                                </div>
                            </div>
                        </label>
                    </div>
            </div>
            
            <div class="sidebar-links-container">
                <a href="main.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'main.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-house-door"></i> HOME</a>
                <a href="editor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'editor.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-calculator"></i> BMI CALCULATOR</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                    <a href="audit_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-shield-lock"></i> AUDIT LOGS</a>
                <?php endif; ?>
                <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-gear"></i> USER SETTINGS</a>
                <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-info-circle"></i> ABOUT</a>
                <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-envelope"></i> CONTACT</a>
            </div>
            
            <div class="logout-btn-container">
                <a href="login.php?action=logout" class="logout-btn">
                    LOGOUT <i class="bi bi-box-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow-1 p-3">
        <div class="container-fluid">
            
            <?php if ($is_admin): ?>
                <!-- Admin View: Masterlist -->
                <div class="container mt-4">
                    
                    <!-- COMPLIANCE PRESSURE DASHBOARD -->
                    <div class="row mb-4 g-3">
                        <!-- Monitoring Rate Card -->
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #1700ad 0%, #000851 100%); color: white;">
                                <div class="card-body p-4 d-flex flex-column justify-content-center text-center">
                                    <h5 class="text-uppercase fw-bold mb-4 small opacity-75">Monitoring Compliance Rate</h5>
                                    <div class="d-flex align-items-end justify-content-center gap-2 mb-2">
                                        <div class="display-4 fw-bold"><?php echo $compliance_summary['rate']; ?>%</div>
                                        <div class="mb-2 fs-5 opacity-75">Completed</div>
                                    </div>
                                    <div class="progress bg-white bg-opacity-25 rounded-pill mb-3" style="height: 10px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $compliance_summary['rate']; ?>%"></div>
                                    </div>
                                    <div class="row text-center small opacity-75">
                                        <div class="col-6 border-end">
                                            <div class="fw-bold fs-6"><?php echo $compliance_summary['completed']; ?></div>
                                            <div>RECORDED</div>
                                        </div>
                                        <div class="col-6">
                                            <a href="export_missing.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo urlencode($selected_unit); ?>" 
                                               class="text-white text-decoration-none d-block" 
                                               title="Click to download list of missing personnel"
                                               style="transition: opacity 0.2s;"
                                               onmouseover="this.style.opacity='0.7'" 
                                               onmouseout="this.style.opacity='1'">
                                                <div class="fw-bold fs-6"><?php echo $compliance_summary['total'] - $compliance_summary['completed']; ?></div>
                                                <div>MISSING <i class="bi bi-download" style="font-size: 0.6rem;"></i></div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BMI Classification Legend Box -->
                            <div class="card border-0 shadow-sm rounded-4 mt-3 bmi-card-adaptive">
                                <div class="card-body p-4">
                                    <h5 class="text-uppercase fw-bold mb-3 small text-secondary">BMI Legend</h5>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(255, 0, 255, 0.1); border-color: rgba(255, 0, 255, 0.2) !important; font-size: 0.65rem;">SUW</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Severely Underweight</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(0, 0, 255, 0.1); border-color: rgba(0, 0, 255, 0.2) !important; font-size: 0.65rem;">UW</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Underweight</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(0, 255, 0, 0.1); border-color: rgba(0, 255, 0, 0.2) !important; font-size: 0.65rem;">N</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Normal</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(0, 255, 255, 0.1); border-color: rgba(0, 255, 255, 0.2) !important; font-size: 0.65rem;">A</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Acceptable</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(255, 255, 0, 0.1); border-color: rgba(255, 255, 0, 0.2) !important; font-size: 0.65rem;">OW</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Overweight</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(255, 204, 0, 0.1); border-color: rgba(255, 204, 0, 0.2) !important; font-size: 0.65rem;">O1</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Obese Class 1</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mb-2 pb-1">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(255, 153, 0, 0.1); border-color: rgba(255, 153, 0, 0.2) !important; font-size: 0.65rem;">O2</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Obese Class 2</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge border text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 22px; background: rgba(255, 0, 0, 0.1); border-color: rgba(255, 0, 0, 0.2) !important; font-size: 0.65rem;">O3</span>
                                                <span class="small opacity-75 fw-semibold" style="font-size: 0.7rem;">Obese Class 3</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weight Priority Units Card -->
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm rounded-4 h-100 bmi-card-adaptive">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="text-uppercase fw-bold mb-0 small text-secondary">Units Tracking</h5>
                                        <div class="dropdown no-print">
                                             <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3 border-0 bg-light-subtle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Sorted by: <?php echo strtoupper(str_replace('_', ' ', $rank_sort == 'nc_count' ? 'Non-Compliant' : $rank_sort)); ?>
                                             </button>
                                             <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                                 <li><a class="dropdown-item small fw-bold" href="?rank_sort=nc_count&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">TOTAL NON-COMPLIANT</a></li>
                                                 <li><hr class="dropdown-divider"></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=sev_uw&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">SEVERELY UNDERWEIGHT</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=uw&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">UNDERWEIGHT</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=normal&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">NORMAL</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=acceptable&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">ACCEPTABLE BMI</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=ow&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">OVERWEIGHT</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=obese1&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">OBESE CLASS 1</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=obese2&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">OBESE CLASS 2</a></li>
                                                 <li><a class="dropdown-item small" href="?rank_sort=obese3&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&unit=<?php echo $selected_unit; ?>">OBESE CLASS 3</a></li>
                                             </ul>
                                         </div>
                                    </div>
                                    
                                    <?php if (empty($unit_compliance_rankings)): ?>
                                        <div class="text-center py-4 text-secondary opacity-50">
                                            <i class="bi bi-shield-check display-6 d-block mb-2"></i>
                                            No non-compliant records found for this period.
                                        </div>
                                    <?php else: ?>
                                        <div class="units-scroll-container px-1" style="max-height: 600px; overflow-y: auto; overflow-x: hidden;">
                                            <div class="row g-2">
                                                <?php foreach ($unit_compliance_rankings as $rank => $data): 
                                                    $unitColor = getUnitColor($data['unit']);
                                                ?>
                                                    <div class="col-12">
                                                        <div class="d-flex justify-content-between align-items-center p-2 rounded-3 rank-item-bg shadow-sm" style="border-left: 4px solid <?php echo $unitColor; ?>;">
                                                            <div class="d-flex flex-column gap-1 overflow-hidden" style="flex: 1;">
                                                                <div class="d-flex align-items-center mb-1">
                                                                    <div class="badge rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                                                                         style="width: 24px; height: 24px; background-color: <?php echo $unitColor; ?>; color: white; font-size: 0.75rem;">
                                                                        <?php echo $rank + 1; ?>
                                                                    </div>
                                                                    <span class="fw-bold text-truncate" style="cursor: pointer; text-decoration: underline dotted; font-size: 0.95rem;" onclick="filterAndSortByBMI('<?php echo addslashes($data['unit']); ?>')" title="Click to filter and sort by BMI"><?php echo htmlspecialchars($data['unit']); ?></span>
                                                                </div>
                                                                <!-- BMI Group Breakdown -->
                                                                <div class="d-flex flex-wrap gap-1 ps-2" style="margin-left: 1.5rem;">
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(255, 0, 255, 0.1); border-color: rgba(255, 0, 255, 0.2) !important;" title="Click to see Severely Underweight" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'SEVERELY UNDERWEIGHT')">SUW: <?php echo $data['sev_uw']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(0, 0, 255, 0.1); border-color: rgba(0, 0, 255, 0.2) !important;" title="Click to see Underweight" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'UNDERWEIGHT')">UW: <?php echo $data['uw']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(0, 255, 0, 0.1); border-color: rgba(0, 255, 0, 0.2) !important;" title="Click to see Normal" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'NORMAL')">N: <?php echo $data['normal']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(0, 255, 255, 0.1); border-color: rgba(0, 255, 255, 0.2) !important;" title="Click to see Acceptable BMI" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'ACCEPTABLE BMI')">A: <?php echo $data['acceptable']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(255, 255, 0, 0.1); border-color: rgba(255, 255, 0, 0.2) !important;" title="Click to see Overweight" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'OVERWEIGHT')">OW: <?php echo $data['ow']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(255, 204, 0, 0.1); border-color: rgba(255, 204, 0, 0.2) !important;" title="Click to see Obese Class 1" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'OBESE CLASS 1')">O1: <?php echo $data['obese1']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(255, 153, 0, 0.1); border-color: rgba(255, 153, 0, 0.2) !important;" title="Click to see Obese Class 2" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'OBESE CLASS 2')">O2: <?php echo $data['obese2']; ?></span>
                                                                    <span class="badge border text-dark fw-normal bmi-breakdown-badge" style="font-size: 0.62rem; background: rgba(255, 0, 0, 0.1); border-color: rgba(255, 0, 0, 0.2) !important;" title="Click to see Obese Class 3" onclick="filterByBmiCategory('<?php echo addslashes($data['unit']); ?>', 'OBESE CLASS 3')">O3: <?php echo $data['obese3']; ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex align-items-center ms-auto px-2">
                                                                <div class="text-end">
                                                                    <div class="fw-bold fs-5" style="color: <?php echo $unitColor; ?>; line-height: 1;"><?php echo $data['nc_count']; ?></div>
                                                                    <div class="small text-secondary fw-bold" style="font-size: 0.65rem;">NON-COMPLIANT</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <!-- Non-Compliance Total -->
                                        <?php 
                                            $total_nc = 0;
                                            foreach ($unit_compliance_rankings as $d) { $total_nc += $d['nc_count']; }
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 px-3 mt-3 rounded-3 shadow-sm" style="background: linear-gradient(135deg, #1700ad 0%, #000851 100%);">
                                            <span class="fw-bold text-white text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Total Non-Compliant</span>
                                            <span class="fw-bold text-white fs-4"><?php echo $total_nc; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                            <h2 class="fw-bold text-uppercase m-0" style="color: #1700ad; font-family: 'Agrandir', sans-serif;">
                                Masterlist of Personnel
                                <span id="activeFilterLabel" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fw-normal ms-2" style="font-size: 0.8rem; display: none;"></span>
                            </h2>
                            
                            <!-- External Search and Global Actions (Independent of Filter Bar) -->
                            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0 justify-content-end">
                                <!-- Bulk Action Button (Initially Hidden) -->
                                <div id="bulkActionContainer" style="display: none;">
                                    <button type="button" class="btn btn-danger rounded-pill px-3 py-1 fw-bold shadow-sm d-flex align-items-center" onclick="confirmBulkDelete()" style="font-size: 0.85rem;">
                                        <i class="bi bi-trash3-fill me-2"></i> Delete Selected (<span id="selectedCount">0</span>)
                                    </button>
                                </div>
                                
                                <div class="search-box position-relative" style="width: 250px;">
                                    <i class="bi bi-search search-icon" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #1700ad; opacity: 0.5;"></i>
                                    <input type="text" id="searchInput" name="q" class="form-control rounded-pill border shadow-sm fw-bold ps-5" placeholder="Search personnel..." style="height: 40px; border-color: rgba(23, 0, 173, 0.1);" value="<?php echo htmlspecialchars($selected_search); ?>">
                                </div>
                                <a href="admin_register_personnel.php" class="btn btn-primary rounded-circle p-0 action-btn shadow-sm flex-shrink-0" title="Register New Personnel" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: #1700ad; border-color: #1700ad;">
                                    <i class="bi bi-person-plus"></i>
                                </a>
                                <button type="button" class="btn btn-success rounded-circle p-0 action-btn shadow-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#exportExcelModal" title="Export to Excel" style="width: 40px; height: 40px;">
                                    <i class="bi bi-file-earmark-excel"></i>
                                </button>
                                <a href="audit_logs.php" class="btn btn-dark rounded-circle p-0 action-btn shadow-sm flex-shrink-0" title="Audit Logs" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-shield-lock"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Filter Bar: ONLY Filters -->
                        <div class="filter-bar p-2 rounded-4 shadow-sm mb-4 bg-white border">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <form action="main.php" method="GET" class="filter-group rounded-pill shadow-sm border d-flex align-items-center flex-wrap m-0 p-1 bg-light-subtle" id="masterlistFilterForm" style="width: 100%; border-color: rgba(23, 0, 173, 0.1) !important;">
                                    
                                    <!-- Timeline Group -->
                                    <div class="d-flex align-items-center px-1">
                                        <i class="bi bi-calendar3 text-primary ms-2 me-1"></i>
                                        <select name="month" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 90px; box-shadow: none; cursor: pointer; font-size: 0.8rem;" onchange="this.form.submit()">
                                            <?php for($m=1; $m<=12; $m++): ?>
                                                <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $selected_month == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <div class="vr mx-1 opacity-25" style="height: 15px;"></div>
                                        <select name="year" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 65px; box-shadow: none; cursor: pointer; font-size: 0.8rem;" onchange="this.form.submit()">
                                            <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                                <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="vr my-1 mx-1 bg-primary opacity-25" style="width: 1.5px; height: 25px;"></div>

                                    <!-- Status & BMI Classification -->
                                    <div class="d-flex align-items-center px-1">
                                        <i class="bi bi-funnel text-primary me-2"></i>
                                        <select name="status" id="statusFilter" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 105px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">ALL STATUS</option>
                                            <option value="Taken">TAKEN</option>
                                            <option value="Not Taken">NOT TAKEN</option>
                                        </select>
                                        <div class="vr mx-1 opacity-25" style="height: 15px;"></div>
                                        <select name="bmi_class" id="bmiClassFilter" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 105px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">ALL BMI</option>
                                            <?php 
                                            $bmi_opts = ['SEVERELY UNDERWEIGHT', 'UNDERWEIGHT', 'NORMAL', 'ACCEPTABLE BMI', 'OVERWEIGHT', 'OBESE CLASS 1', 'OBESE CLASS 2', 'OBESE CLASS 3'];
                                            foreach($bmi_opts as $opt): 
                                                $display = ($opt == 'SEVERELY UNDERWEIGHT') ? 'SUW' : (($opt == 'UNDERWEIGHT') ? 'UW' : (($opt == 'ACCEPTABLE BMI') ? 'ACCEPTABLE' : $opt));
                                            ?>
                                                <option value="<?php echo $opt; ?>" <?php echo $selected_bmi == $opt ? 'selected' : ''; ?>><?php echo $display; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="vr my-1 mx-1 bg-primary opacity-25" style="width: 1.5px; height: 25px;"></div>

                                    <!-- Office, Rank, Sex -->
                                    <div class="d-flex align-items-center px-1">
                                        <i class="bi bi-building text-primary me-2"></i>
                                        <select name="unit" id="unitFilter" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 115px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">ALL OFFICES</option>
                                            <?php foreach($units as $u): ?>
                                                <option value="<?php echo $u; ?>"><?php echo $u; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="vr mx-1 opacity-25" style="height: 15px;"></div>
                                        <i class="bi bi-person text-primary me-2"></i>
                                        <select name="rank_filter" id="rankFilter" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 90px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">ALL RANKS</option>
                                            <?php foreach($rank_order as $r): 
                                                $shortRank = getRankAcronym($r);
                                            ?>
                                                <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $selected_rank_filter == $r ? 'selected' : ''; ?>><?php echo htmlspecialchars($shortRank); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="vr mx-1 opacity-25" style="height: 15px;"></div>
                                        <select name="gender" id="genderFilter" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 80px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">ALL SEX</option>
                                            <option value="Male">MALE</option>
                                            <option value="Female">FEMALE</option>
                                        </select>
                                    </div>
                                    
                                    <div class="vr my-1 mx-1 bg-primary opacity-25" style="width: 1.5px; height: 25px;"></div>

                                    <!-- Client-side Sorting -->
                                    <div class="d-flex align-items-center px-1 pe-2">
                                        <i class="bi bi-sort-alpha-down text-primary me-2"></i>
                                        <select id="alphabeticalSort" class="form-select border-0 px-1 fw-bold bg-transparent" style="width: auto; min-width: 70px; box-shadow: none; cursor: pointer; font-size: 0.8rem;">
                                            <option value="">SORT</option>
                                            <option value="az">A → Z</option>
                                            <option value="za">Z → A</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle table-masterlist">
                                    <thead class="bg-light text-uppercase fw-bold" style="color: #1700ad;">
                                        <tr>
                                            <th class="py-3 border-0 text-center" style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllUsers">
                                            </th>
                                            <th class="py-3 border-0 text-center" style="width: 50px;">Photo</th>
                                            <th class="py-3 border-0">Rank</th>
                                            <th class="py-3 border-0">Name</th>
                                            <th class="py-3 border-0 text-center">Age</th>
                                            <th class="py-3 border-0">Username</th>
                                            <th class="py-3 border-0 text-center">Sex</th>
                                            <th class="py-3 border-0">Office</th>
                                            <th class="py-3 border-0 text-center">BMI Status</th>
                                            <th class="py-3 border-0">Birthday</th>
                                            <th class="py-3 border-0">Registered</th>
                                            <th class="py-3 border-0 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php if (count($users_list) > 0): ?>
                                            <?php 
                                            $bmi_order_map = [
                                                'SEVERELY UNDERWEIGHT' => 1,
                                                'UNDERWEIGHT' => 2,
                                                'NORMAL' => 3,
                                                'ACCEPTABLE BMI' => 4,
                                                'OVERWEIGHT' => 5,
                                                'OBESE CLASS 1' => 6,
                                                'OBESE CLASS 2' => 7,
                                                'OBESE CLASS 3' => 8
                                            ];
                                            ?>
                                            <?php foreach ($users_list as $user): 
                                                $bmi_val = strtoupper(trim($user['bmi_class'] ?? ''));
                                                $bmi_order = $bmi_order_map[$bmi_val] ?? 10;
                                            ?>
                                                <tr data-unit="<?php echo htmlspecialchars($user['unit'] ?? ''); ?>" 
                                                    data-status="<?php echo $user['has_record'] > 0 ? 'Taken' : 'Not Taken'; ?>" 
                                                    data-gender="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>" 
                                                    data-rank="<?php echo htmlspecialchars($user['rank'] ?? ''); ?>" 
                                                    data-bmi-class="<?php echo $bmi_val; ?>" 
                                                    data-name="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                                                    data-bmi-order="<?php echo $bmi_order; ?>">
                                                    <td class="py-2 text-center">
                                                        <input type="checkbox" class="form-check-input user-checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" onchange="updateBulkUI()">
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        <div class="rounded-circle overflow-hidden d-inline-block shadow-sm border" style="width: 38px; height: 38px;">
                                                            <?php if (!empty($user['profile_pic'])): ?>
                                                                <img src="<?php echo $user['profile_pic']; ?>" alt="pfp" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                                            <?php else: ?>
                                                                <img src="images/placeholder.png" alt="pfp" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23f8f9fa\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 fw-bold"><?php echo htmlspecialchars(getRankAcronym($user['rank'])); ?></td>
                                                    <td class="py-3">
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                        <?php if($user['has_record'] > 0): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1" style="font-size: 10px;">OK</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-1" style="font-size: 10px;">MISSING</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 text-secondary text-center"><?php echo isset($user['age']) ? htmlspecialchars($user['age']) : 'N/A'; ?></td>
                                                    <td class="py-3 text-secondary small"><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td class="py-3 text-capitalize text-center"><?php echo strtoupper(substr($user['gender'] ?? '', 0, 1)); ?></td>
                                                    <td class="py-3 text-secondary small"><?php echo htmlspecialchars($user['unit'] ?? 'N/A'); ?></td>
                                                    <td class="py-3 text-center">
                                                        <?php if($user['has_record'] > 0): 
                                                            $bmi_class = strtoupper(trim($user['bmi_class'] ?? ''));
                                                            $badge_style = "background-color: #6c757d; color: white;"; // Default
                                                            
                                                            switch($bmi_class) {
                                                                case 'SEVERELY UNDERWEIGHT': $badge_style = "background-color: #FF00FF; color: white;"; break;
                                                                case 'UNDERWEIGHT': $badge_style = "background-color: #0d6efd; color: white;"; break;
                                                                case 'NORMAL': $badge_style = "background-color: #198754; color: white;"; break;
                                                                case 'ACCEPTABLE BMI': $badge_style = "background-color: #0dcaf0; color: black;"; break;
                                                                case 'OVERWEIGHT': $badge_style = "background-color: #ffc107; color: black;"; break;
                                                                case 'OBESE CLASS 1': $badge_style = "background-color: #FFCC00; color: black;"; break;
                                                                case 'OBESE CLASS 2': $badge_style = "background-color: #fd7e14; color: white;"; break;
                                                                case 'OBESE CLASS 3': $badge_style = "background-color: #dc3545; color: white;"; break;
                                                            }
                                                        ?>
                                                            <span class="badge rounded-pill fw-bold" style="<?php echo $badge_style; ?> font-size: 10px; min-width: 80px;">
                                                                <?php echo htmlspecialchars($bmi_class); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill border border-secondary-subtle" style="font-size: 10px;">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 text-nowrap"><?php echo date('M j, Y', strtotime($user['birthday'])); ?></td>
                                                    <td class="py-3 text-secondary small text-nowrap"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                    <td class="py-3 text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <?php 
                                                                // Strip heavy profile pic from JSON payload to prevent massive DOM size
                                                                $safe_user = $user;
                                                                unset($safe_user['profile_pic']);
                                                            ?>
                                                            <button class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0 fw-bold" style="font-size: 11px;"
                                                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($safe_user)); ?>)">
                                                                Edit
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2 py-0 fw-bold" style="font-size: 11px;"
                                                                    onclick="openBmiModeModal(<?php echo $user['id']; ?>)"
                                                                    title="Manage BMI/Health Records">
                                                                BMI
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-secondary">
                                                    <i class="bi bi-people fs-1 d-block mb-3 opacity-25"></i>
                                                    No registered personnel found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit User Modal -->
                <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom-0 pb-0">
                                <h5 class="modal-title fw-bold text-uppercase" id="editUserModalLabel" style="color: #1700ad;">Edit User Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form id="editUserForm" action="main.php" method="POST">
                                    <input type="hidden" name="action" value="edit_user">
                                    <input type="hidden" name="user_id" id="edit_user_id">
                                    <input type="hidden" name="redirect_params" id="edit_user_form_redirect" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="edit_last_name" class="form-label small fw-bold text-secondary text-uppercase">Last Name</label>
                                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_first_name" class="form-label small fw-bold text-secondary text-uppercase">First Name</label>
                                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_middle_name" class="form-label small fw-bold text-secondary text-uppercase">Middle Name</label>
                                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_suffix" class="form-label small fw-bold text-secondary text-uppercase">Suffix</label>
                                            <input type="text" class="form-control" id="edit_suffix" name="suffix" placeholder="Jr, Sr, III">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_rank" class="form-label small fw-bold text-secondary text-uppercase">Rank</label>
                                            <input type="text" class="form-control" id="edit_rank" name="rank" list="pnp-ranks" required autocomplete="off">
                                            <datalist id="pnp-ranks">
                                                <option value="Police General (PGEN)">
                                                <option value="Police Lieutenant General (PLTGEN)">
                                                <option value="Police Major General (PMGEN)">
                                                <option value="Police Brigadier General (PBGEN)">
                                                <option value="Police Colonel (PCOL)">
                                                <option value="Police Lieutenant Colonel (PLTCOL)">
                                                <option value="Police Major (PMAJ)">
                                                <option value="Police Captain (PCPT)">
                                                <option value="Police Lieutenant (PLT)">
                                                <option value="Police Executive Master Sergeant (PEMS)">
                                                <option value="Police Chief Master Sergeant (PCMS)">
                                                <option value="Police Senior Master Sergeant (PSMS)">
                                                <option value="Police Master Sergeant (PMSg)">
                                                <option value="Police Staff Sergeant (PSSg)">
                                                <option value="Police Corporal (PCpl)">
                                                <option value="Patrolman / Patrolwoman (Pat)">
                                            </datalist>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_birthday" class="form-label small fw-bold text-secondary text-uppercase">Birthday</label>
                                            <input type="date" class="form-control" id="edit_birthday" name="birthday" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_nationality" class="form-label small fw-bold text-secondary text-uppercase">Nationality</label>
                                            <input type="text" class="form-control" id="edit_nationality" name="nationality">
                                        </div>

                                        <div class="col-12">
                                            <label for="edit_address" class="form-label small fw-bold text-secondary text-uppercase">Address</label>
                                            <input type="text" class="form-control" id="edit_address" name="address">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_gender" class="form-label small fw-bold text-secondary text-uppercase">Sex</label>
                                            <select class="form-control" id="edit_gender" name="gender" required>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary text-uppercase">Unit/Office</label>
                                            <div id="edit-unit-container">
                                                <div class="d-flex align-items-center mb-2 edit-unit-row">
                                                    <input type="text" class="form-control" name="unit[]" list="pnp-units-edit" required autocomplete="off">
                                                    <button type="button" class="btn btn-outline-primary ms-2 rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; flex-shrink: 0;" onclick="addEditUnitRow()">
                                                        <i class="bi bi-plus fs-5"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <datalist id="pnp-units-edit">
                                                <option value="CHQ">
                                                <option value="PS1">
                                                <option value="PS2">
                                                <option value="PS3">
                                                <option value="PS4">
                                                <option value="PS5">
                                                <option value="PS6">
                                                <option value="CMFC">
                                                <option value="TPU">
                                                <option value="MPU">
                                                <option value="CARMU">
                                                <option value="CIU">
                                                <option value="AOMU">
                                                <option value="CCADU">
                                                <option value="ARDDO">
                                                <option value="CPPU">
                                                <option value="GSO">
                                                <option value="DEU">
                                                <option value="COMU">
                                                <option value="ODCDO">
                                            </datalist>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_religion" class="form-label small fw-bold text-secondary text-uppercase">Religion</label>
                                            <input type="text" class="form-control" id="edit_religion" name="religion">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_contact" class="form-label small fw-bold text-secondary text-uppercase">Contact</label>
                                            <input type="text" class="form-control" id="edit_contact" name="contact">
                                        </div>
                                        <div class="col-12">
                                            <label for="edit_email" class="form-label small fw-bold text-secondary text-uppercase">Email</label>
                                            <input type="email" class="form-control" id="edit_email" name="email">
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mt-4">
                                        <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color: #1700ad; border: none;">Save Changes</button>
                                    </div>
                                </form>

                                <!-- Danger Zone -->
                                <div class="mt-5 border-top pt-4">
                                    <div class="p-3 rounded-4 border bg-danger-subtle border-danger-subtle d-sm-flex align-items-center justify-content-between">
                                        <div class="mb-3 mb-sm-0">
                                            <h6 class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>Danger Zone</h6>
                                            <p class="small text-danger mb-0 opacity-75">Deleting a user will permanently remove them and all their health records from the database.</p>
                                        </div>
                                        <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" onclick="confirmDeleteUser()">Delete Permanently</button>
                                        
                                        <form id="deleteUserForm" action="main.php" method="POST" class="d-none">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" id="delete_user_id">
                                            <input type="hidden" name="redirect_params" id="delete_user_form_redirect" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>">
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BMI Mode Selection Modal -->
                <div class="modal fade" id="bmiModeModal" tabindex="-1" aria-labelledby="bmiModeModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom-0 pb-0">
                                <h5 class="modal-title fw-bold text-uppercase" id="bmiModeModalLabel" style="color: #1700ad;">Select Entry Mode</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <p class="text-secondary small mb-4">How would you like to enter the BMI and Health Data?</p>
                                
                                <div class="d-grid gap-3">
                                    <a href="#" id="btnQuickMode" class="btn btn-lg btn-primary rounded-pill fw-bold shadow-sm" style="background-color: #1700ad; border-color: #1700ad;">
                                        <i class="bi bi-lightning-charge-fill me-2"></i> Quick Mode (Monthly Batch)
                                    </a>
                                    <a href="#" id="btnFormMode" class="btn btn-lg btn-outline-primary rounded-pill fw-bold shadow-sm">
                                        <i class="bi bi-file-earmark-text me-2"></i> Form Mode (Standard Editor)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export to Excel Modal -->
                <div class="modal fade" id="exportExcelModal" tabindex="-1" aria-labelledby="exportExcelModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom-0 pb-0">
                                <h5 class="modal-title fw-bold text-uppercase" id="exportExcelModalLabel" style="color: #198754;">Extract Health Records to Excel</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form action="export_excel.php" method="GET">
                                    <p class="text-secondary small mb-4">Select the month range for the health records you want to export.</p>
                                    
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary text-uppercase">Start Month</label>
                                            <div class="input-group">
                                                <select name="start_month" class="form-select" required>
                                                    <?php 
                                                    for ($m=1; $m<=12; $m++) {
                                                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                                        $val = str_pad($m, 2, "0", STR_PAD_LEFT);
                                                        $selected = ($val == date('m')) ? 'selected' : '';
                                                        echo "<option value='$val' $selected>$monthName</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <select name="start_year" class="form-select" required>
                                                    <?php 
                                                    $curYear = (int)date('Y');
                                                    for ($y=$curYear; $y>=$curYear-5; $y--) {
                                                        echo "<option value='$y'>$y</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary text-uppercase">End Month</label>
                                            <div class="input-group">
                                                <select name="end_month" class="form-select" required>
                                                    <?php 
                                                    for ($m=1; $m<=12; $m++) {
                                                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                                        $val = str_pad($m, 2, "0", STR_PAD_LEFT);
                                                        $selected = ($val == date('m')) ? 'selected' : '';
                                                        echo "<option value='$val' $selected>$monthName</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <select name="end_year" class="form-select" required>
                                                    <?php 
                                                    $curYear = (int)date('Y');
                                                    for ($y=$curYear; $y>=$curYear-5; $y--) {
                                                        echo "<option value='$y'>$y</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-secondary text-uppercase">Unit/Office</label>
                                            <select name="unit_filter" class="form-select" required>
                                                <option value="ALL">ALL UNITS</option>
                                                <option value="NO_UNIT">No Unit</option>
                                                <option value="CHQ">CHQ</option>
                                                <option value="PS1">PS1</option>
                                                <option value="PS2">PS2</option>
                                                <option value="PS3">PS3</option>
                                                <option value="PS4">PS4</option>
                                                <option value="PS5">PS5</option>
                                                <option value="PS6">PS6</option>
                                                <option value="CMFC">CMFC</option>
                                                <option value="TPU">TPU</option>
                                                <option value="MPU">MPU</option>
                                                <option value="CARMU">CARMU</option>
                                                <option value="CIU">CIU</option>
                                                <option value="AOMU">AOMU</option>
                                                <option value="CCADU">CCADU</option>
                                                <option value="ARDDO">ARDDO</option>
                                                <option value="CPPU">CPPU</option>
                                                <option value="GSO">GSO</option>
                                                <option value="DEU">DEU</option>
                                                <option value="COMU">COMU</option>
                                                <option value="ODCDO">ODCDO</option>
                                            </select>
                                            <div class="form-text small opacity-75 mt-1">Leave as "ALL UNITS" to extract everything.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mt-4">
                                        <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                                            <i class="bi bi-download me-1"></i> DOWNLOAD EXCEL
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>

            <!-- Welcome Section -->
            <div class="welcome-section text-center mb-4 mt-3 no-print">
                <h2 class="fw-bold text-uppercase mb-2" style="color: #1700ad; font-family: 'Agrandir', sans-serif; letter-spacing: 1px;">
                    Welcome Back, <?php echo htmlspecialchars($_SESSION['name']); ?>!
                </h2>
                <p class="text-secondary fs-5 mb-3" id="current-date-display" style="font-weight: 500;"></p>
            </div>

            <!-- BMI Progress Chart Section -->
            <?php if (!empty($bmiData)): ?>
            <div class="row justify-content-center mb-4 no-print">
                <div class="col-md-9 col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bmi-card-adaptive">
                        <div class="card-header bg-lavender border-bottom border-black py-2 px-3">
                            <h6 class="mb-0 fw-bold text-uppercase small" style="color: #1700ad;"><i class="bi bi-graph-up-arrow me-2"></i>BMI Progress Tracking</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="bmiProgressChart" style="max-height: 300px; width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- History / Filter Section -->
            <div class="row justify-content-center mb-4 no-print">
                <div class="col-md-9 col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bmi-card-adaptive">
                        <div class="card-body p-3">
                            <form action="main.php" method="GET" class="row g-3 align-items-center justify-content-center">
                                <div class="col-auto">
                                    <h6 class="mb-0 fw-bold text-uppercase small text-secondary"><i class="bi bi-clock-history me-2"></i>View History</h6>
                                </div>
                                <div class="col-sm-4 col-md-3">
                                    <select name="month" class="form-select border-0 bg-light rounded-pill px-3 shadow-none">
                                        <?php 
                                        for ($m=1; $m<=12; $m++) {
                                            $dateObj = DateTime::createFromFormat('!m', $m);
                                            $monthName = $dateObj->format('F');
                                            $val = str_pad($m, 2, "0", STR_PAD_LEFT);
                                            $selected = ($val == $selected_month) ? 'selected' : '';
                                            echo "<option value='$val' $selected>$monthName</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-sm-4 col-md-3">
                                    <select name="year" class="form-select border-0 bg-light rounded-pill px-3 shadow-none">
                                        <?php 
                                        $curYear = (int)date('Y');
                                        for ($y=$curYear; $y>=$curYear-10; $y--) {
                                            $selected = ($y == $selected_year) ? 'selected' : '';
                                            echo "<option value='$y' $selected>$y</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1700ad 0%, #0d005e 100%); border: none;">
                                        GO
                                    </button>
                                </div>
                                <div class="col-auto ms-lg-3">
                                    <button type="button" class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm" onclick="printRecord('<?php echo htmlspecialchars(addslashes($_SESSION['name'])); ?>')">
                                        <i class="bi bi-printer me-1"></i> PRINT
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
            $has_any_weight = !empty($monthly_weights);
            $target_m_key = "$selected_year-$selected_month";
            if (!$health_data): ?>
                <div class="alert alert-info text-center rounded-4 shadow-sm py-4 mb-4 mx-auto" style="max-width: 800px; border-left: 5px solid #1700ad;">
                    <i class="bi bi-info-circle fs-2 d-block mb-3 opacity-50"></i>
                    <h5 class="fw-bold">No health record found for <?php echo date('F Y', strtotime("$selected_year-$selected_month-01")); ?></h5>
                    <?php if (isset($monthly_weights[$target_m_key])): ?>
                        <p class="mb-3 text-secondary">Weight data exists (<b><?php echo $monthly_weights[$target_m_key]; ?>kg</b>), but other details are missing.</p>
                        <a href="editor.php?date=<?php echo $target_m_key; ?>-01&mode=edit" class="btn btn-sm btn-primary rounded-pill px-4">
                            <i class="bi bi-pencil-square me-1"></i> RECOVER / ADD DETAILS
                        </a>
                    <?php else: ?>
                        <p class="mb-3 text-secondary">No data recorded for this month. You can add a new record in the editor.</p>
                        <a href="editor.php?date=<?php echo $target_m_key; ?>-01&mode=edit" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                            <i class="bi bi-plus-circle me-1"></i> ADD NEW RECORD
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($health_data || $has_any_weight): ?>
            <div class="bmi-container <?php echo !$health_data ? 'mt-0 shadow-none border-0' : ''; ?>" style="<?php echo !$health_data ? 'background: #f8f9fa; border: 1px dashed #dee2e6 !important;' : ''; ?>">
                <?php if ($health_data): ?>
                <div class="top-block">
                    <!-- Left Column -->
                    <div class="left-col border-end border-black">
                                <div class="pics-area bg-lavender">
                                    <?php 
                                    $imgs = ['1'=>'right', '2'=>'front', '3'=>'left'];
                                    foreach($imgs as $num => $type): 
                                        $dbKey = 'img_'.$type;
                                        $hasImg = !empty($user_data[$dbKey]);
                                        $src = $hasImg ? $user_data[$dbKey] : '';
                                        $label = ucfirst($type) . ' View';
                                    ?>
                                    <div class="pic-box border-end border-black border-bottom border-black">
                                        <span id="img-text-<?php echo $num; ?>" class="<?php echo $hasImg ? 'd-none' : ''; ?>"><?php echo $hasImg ? '' : "$label"; ?></span>
                                        <img id="img-preview-<?php echo $num; ?>" src="<?php echo $src; ?>" alt="<?php echo $label; ?>" class="<?php echo $hasImg ? '' : 'd-none'; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex text-center border-bottom border-black fw-bold small-text bg-white">
                                    <div class="view-label border-end border-black">Right View</div>
                                    <div class="view-label border-end border-black">Front View</div>
                                    <div class="view-label">Left View</div>
                                </div>
                        <div class="text-center fw-bold py-1 border-bottom border-black small-text bg-white">BMI CLASSIFICATION</div>
                        <div class="d-flex classification-box">
                            <div class="class-standard border-end border-black bg-lavender small-text">
                                <span class="fw-bold">PNP BMI ACCEPTABLE STANDARD:</span>
                                <div class="m-auto fw-bold fs-6 mt-3">
                                    <?php echo hval($health_data, 'bmi_classification'); ?>
                                </div>
                            </div>
                            <div class="class-standard bg-white small-text">
                                <span class="fw-bold">WHO Standard</span>
                                <span class="m-auto fw-bold fs-6 mt-3"><?php echo hval($health_data, 'bmi_classification'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="right-col d-flex flex-column">
                        <div class="right-header bg-purple border-bottom border-black fw-bold small-text">
                            <?php 
                            $rank = hval_raw($user_data, 'rank');
                            $rank_display = $rank;
                            if (preg_match('/\((.*?)\)/', $rank, $match)) {
                                $rank_display = $match[1];
                            }
                            ?>
                            <div class="w-50">Rank/Name: <span class="text-uppercase"><?php echo htmlspecialchars($rank_display) . ' ' . hval($user_data, 'name'); ?></span></div>
                            <div class="w-50">Unit/Office: <?php echo hval($user_data, 'unit'); ?></div>
                        </div>
                        
                        <?php 
                        $fields = [
                            'Age' => ['val'=>$age], 
                            'Height' => ['db'=>'height'],
                            'Weight' => ['db'=>'weight'],
                            'Waist' => ['db'=>'waist'],
                            'Hip' => ['db'=>'hip'],
                            'Wrist' => ['db'=>'wrist'],
                            // Capitalize gender for display (e.g., male -> Male)
                            'Gender' => [
                                'val'=>htmlspecialchars(ucfirst(strtolower(hval_raw($user_data, 'gender'))))
                            ],
                            'Date Taken' => ['db'=>'date_taken'],
                            'BMI Result' => ['db'=>'bmi_result'],
                            'Normal Weight' => ['db'=>'normal_weight'],
                            'Weight to Lose' => ['db'=>'weight_to_lose'],
                        ];
                        $i = 0;
                        foreach($fields as $label => $conf): 
                            $bg = ($i++ % 2 == 0) ? 'bg-lavender' : 'bg-white';
                            $val = isset($conf['val']) ? $conf['val'] : hval($health_data, $conf['db'] ?? '');
                        ?>
                        <div class="data-row <?php echo $bg; ?> border-bottom border-black fw-bold small-text">
                            <div class="label-col"><?php echo $label; ?>:</div>
                            <div class="val-col"><?php echo $val; ?></div>
                        </div>
                        <?php endforeach; ?>

                        <div class="bottom-right-box bg-white">
                            <div class="sig-box border-end border-black small-text fw-bold">
                                <span>Intervention Package:</span>
                                <div class="m-auto fs-6 pt-3">
                                    <?php echo hval($health_data, 'intervention_package'); ?>
                                </div>
                            </div>
                            <div class="sig-box small-text fw-bold">
                                <span>Certified Correct Signature:</span>
                                <div class="m-auto fs-6 pt-3">
                                    <?php echo hval($health_data, 'certified_correct'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Monthly -->
                <div class="border-top border-black d-flex flex-column border-2">
                    <div class="text-center fw-bold py-1 border-bottom border-black small-text bg-white">MONTHLY WEIGHT MONITORING</div>
                    
                    <!-- YEAR Row -->
                    <div class="d-flex border-bottom border-black fw-bold small-text bg-lavender">
                        <div class="monthly-col-yr border-end border-black">YEAR</div>
                        <div class="monthly-col-2025 border-end border-black justify-content-center bg-lavender">2025</div>
                        <div class="monthly-col-2026 justify-content-center bg-lavender">2026</div>
                    </div>
                    
                    <!-- MONTH Row -->
                    <div class="d-flex border-bottom border-black fw-bold small-text bg-white">
                        <div class="monthly-col-yr border-end border-black">MONTH</div>
                        <div class="monthly-col-2025 border-end border-black p-0 d-flex">
                            <div class="w-50 border-end border-black text-center py-1">NOV</div>
                            <div class="w-50 text-center py-1">DEC</div>
                        </div>
                        <div class="monthly-col-2026 p-0 d-flex">
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">JAN</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">FEB</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">MAR</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">APR</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">MAY</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">JUN</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">JUL</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">AUG</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">SEP</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">OCT</div>
                            <div class="flex-fill border-end border-black text-center py-1" style="width: 8.33%;">NOV</div>
                            <div class="flex-fill text-center py-1" style="width: 8.33%;">DEC</div>
                        </div>
                    </div>
                    
                    <!-- WEIGHT Row -->
                    <?php 
                        $months = ['2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'];
                        $m_vals = [];
                        foreach($months as $m) {
                            $raw = $monthly_weights[$m] ?? '';
                            if (is_numeric($raw)) {
                                $m_vals[$m] = (string)intval(round((float)$raw));
                            } else {
                                $m_vals[$m] = $raw;
                            }
                        }
                    ?>
                     <div class="d-flex fw-bold small-text bg-lavender">
                        <div class="monthly-col-yr border-end border-black py-2">WEIGHT</div>
                        <div class="monthly-col-2025 border-end border-black p-0 d-flex">
                            <?php foreach(['2025-11', '2025-12'] as $m): ?>
                            <div class="w-50 border-end border-black py-2 text-center">
                                <?php echo $m_vals[$m]; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="monthly-col-2026 p-0 d-flex">
                            <?php 
                            $m26 = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'];
                            foreach($m26 as $idx => $m): 
                                $border = ($idx < 11) ? 'border-end border-black' : '';
                            ?>
                            <div class="flex-fill <?php echo $border; ?> py-2 text-center" style="width: 8.33%;">
                                <?php echo $m_vals[$m]; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Print Button (Always visible if we have any data) -->
            <?php if ($health_data || $has_any_weight): ?>
            <div class="d-flex justify-content-center mt-4 mb-5 no-print">
                <button onclick="printRecord('<?php echo htmlspecialchars(addslashes($_SESSION['name'])); ?>')" class="btn btn-primary px-5 py-2 fw-bold d-flex align-items-center gap-2" style="font-size: 1.1rem; border-radius: 50px; background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%); border: none; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3); transition: transform 0.2s, box-shadow 0.2s;">
                    <i class="bi bi-printer-fill fs-5"></i> PRINT RECORD
                </button>
            </div>
            <?php endif; ?>
            
            <?php endif; // End health_data || has_any_weight check ?>
            
            <?php endif; // End else (user role check) ?>
            
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <script>
        function filterByBmiCategory(unitName, bmiCategory) {
            // Set dropdown values for visual consistency
            const unitFilter = document.getElementById('unitFilter');
            const bmiClassFilter = document.getElementById('bmiClassFilter');
            const label = document.getElementById('activeFilterLabel');

            if (unitFilter) unitFilter.value = unitName;
            if (bmiClassFilter) bmiClassFilter.value = bmiCategory;

            // Update active filter label
            if (label) {
                label.textContent = 'Office: ' + unitName + ' | BMI: ' + bmiCategory;
                label.style.display = 'inline-block';
            }

            // Directly filter all table rows (self-contained, no dependency on scoped filterTable)
            const rows = document.querySelectorAll('.table-masterlist tbody tr:not(.no-results)');
            rows.forEach(function(row) {
                const rowUnit = (row.getAttribute('data-unit') || '');
                const rowBmiClass = (row.getAttribute('data-bmi-class') || '').trim();

                // Unit match: check if the selected unit appears in this row's comma-separated units
                const unitParts = rowUnit.split(',').map(function(u) { return u.trim(); });
                const matchesUnit = unitParts.indexOf(unitName) !== -1;
                const matchesBmi = rowBmiClass === bmiCategory.trim();

                row.style.display = (matchesUnit && matchesBmi) ? '' : 'none';
            });

            // Scroll to masterlist
            const masterlistHeader = document.querySelector('h2.fw-bold.text-uppercase.m-0');
            if (masterlistHeader) {
                masterlistHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function filterAndSortByBMI(unitName) {
            // Set dropdown values for visual consistency
            const unitFilter = document.getElementById('unitFilter');
            const bmiClassFilter = document.getElementById('bmiClassFilter');
            const label = document.getElementById('activeFilterLabel');

            if (unitFilter) unitFilter.value = unitName;
            if (bmiClassFilter) bmiClassFilter.value = ''; // Reset BMI filter

            // Update label
            if (label) {
                label.textContent = 'Office: ' + unitName + ' | Sorted by Legend';
                label.style.display = 'inline-block';
            }

            // Directly filter and sort table rows (self-contained)
            const table = document.querySelector('.table-masterlist');
            if (!table) return;
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));

            // Filter: show only rows matching the unit
            rows.forEach(function(row) {
                const rowUnit = (row.getAttribute('data-unit') || '');
                const unitParts = rowUnit.split(',').map(function(u) { return u.trim(); });
                const matchesUnit = unitParts.indexOf(unitName) !== -1;
                row.style.display = matchesUnit ? '' : 'none';
            });

            // Sort visible rows by BMI order
            rows.sort(function(a, b) {
                var orderA = parseInt(a.getAttribute('data-bmi-order')) || 10;
                var orderB = parseInt(b.getAttribute('data-bmi-order')) || 10;
                if (orderA !== orderB) return orderA - orderB;
                var nameA = (a.getAttribute('data-name') || '').toLowerCase();
                var nameB = (b.getAttribute('data-name') || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
            rows.forEach(function(row) { tbody.appendChild(row); });

            // Scroll to table
            var masterlistHeader = document.querySelector('h2.fw-bold.text-uppercase.m-0');
            if (masterlistHeader) {
                masterlistHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function printRecord(name) {
            const originalTitle = document.title;
            document.title = name + "_BMI_RECORD";
            window.print();
            // Restore title after a short delay to ensure print dialog caught it (though some browsers might capture it immediately)
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }

        // Edit Modal Logic for Admin
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('delete_user_id').value = user.id;
            
            // DYNAMIC REDIRECT PARAMS: Capture current UI state (Search, Filters, Sort)
            const params = new URLSearchParams();
            const monthEl = document.querySelector('select[name="month"]');
            const yearEl = document.querySelector('select[name="year"]');
            const unitEl = document.getElementById('unitFilter');
            const statusEl = document.getElementById('statusFilter');
            const bmiEl = document.getElementById('bmiClassFilter');
            const rankEl = document.getElementById('rankFilter');
            const searchEl = document.getElementById('searchInput');
            
            if (monthEl) params.set('month', monthEl.value);
            if (yearEl) params.set('year', yearEl.value);
            if (unitEl) params.set('unit', unitEl.value);
            if (statusEl) params.set('status', statusEl.value);
            if (bmiEl) params.set('bmi_class', bmiEl.value);
            if (rankEl) params.set('rank_filter', rankEl.value);
            if (searchEl) params.set('q', searchEl.value);
            
            const paramString = params.toString();
            document.getElementById('edit_user_form_redirect').value = paramString;
            document.getElementById('delete_user_form_redirect').value = paramString;

            document.getElementById('edit_last_name').value = user.last_name || '';
            document.getElementById('edit_first_name').value = user.first_name || '';
            document.getElementById('edit_middle_name').value = user.middle_name || '';
            document.getElementById('edit_suffix').value = user.suffix || '';
            document.getElementById('edit_rank').value = user.rank;
            document.getElementById('edit_gender').value = user.gender;
            document.getElementById('edit_birthday').value = user.birthday;
            document.getElementById('edit_nationality').value = user.nationality || '';
            document.getElementById('edit_address').value = user.address || '';
            document.getElementById('edit_religion').value = user.religion || '';
            document.getElementById('edit_contact').value = user.contact || '';
            document.getElementById('edit_email').value = user.email || '';
            
            // Handle Multiple Units
            const container = document.getElementById('edit-unit-container');
            if (container) {
                container.innerHTML = '';
                const units = (user.unit || '').split(',').map(u => u.trim()).filter(u => u !== '');
                if (units.length === 0) {
                    addEditUnitRow();
                } else {
                    units.forEach((u, index) => {
                        addEditUnitRow(u, index === 0);
                    });
                }
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }

        function openBmiModeModal(userId) {
            document.getElementById('btnQuickMode').href = 'quick_mode.php?edit_user_id=' + userId;
            document.getElementById('btnFormMode').href = 'editor.php?edit_user_id=' + userId + '&mode=edit';
            var bmiModal = new bootstrap.Modal(document.getElementById('bmiModeModal'));
            bmiModal.show();
        }

        // Bulk Delete Functions
        function updateBulkUI() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const container = document.getElementById('bulkActionContainer');
            const counter = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                container.style.display = 'block';
                counter.textContent = checkboxes.length;
            } else {
                container.style.display = 'none';
            }
        }

        document.getElementById('selectAllUsers').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            // Only check/uncheck visible rows
            checkboxes.forEach(cb => {
                const tr = cb.closest('tr');
                if (tr.style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });
            updateBulkUI();
        });

        function confirmBulkDelete() {
            const selected = document.querySelectorAll('.user-checkbox:checked');
            if (confirm(`CRITICAL ACTION: Are you sure you want to permanently delete all ${selected.length} selected users? This cannot be undone.`)) {
                if (confirm('FINAL WARNING: All associated medical records will be wiped. Proceed with mass deletion?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'main.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_multiple_users';
                    form.appendChild(actionInput);
                    
                    selected.forEach(cb => {
                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'user_ids[]';
                        idInput.value = cb.value;
                        form.appendChild(idInput);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        function confirmDeleteUser() {
            if (confirm('CRITICAL ACTION: Are you sure you want to permanently delete this user? This action will wipe all their records and CANNOT be undone.')) {
                if (confirm('FINAL WARNING: This is your last chance. Proceed with deletion?')) {
                    document.getElementById('deleteUserForm').submit();
                }
            }
        }

        function addEditUnitRow(value = '', isFirst = true) {
            const container = document.getElementById('edit-unit-container');
            const newRow = document.createElement('div');
            newRow.className = 'd-flex align-items-center mb-2 edit-unit-row';
            
            const btnClass = isFirst ? 'btn-outline-primary' : 'btn-outline-danger';
            const iconClass = isFirst ? 'bi-plus' : 'bi-dash';
            const onClick = isFirst ? 'addEditUnitRow("", false)' : 'removeEditUnitRow(this)';
            
            newRow.innerHTML = `
                <input type="text" class="form-control" name="unit[]" list="pnp-units-edit" value="${value}" required autocomplete="off">
                <button type="button" class="btn ${btnClass} ms-2 rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; flex-shrink: 0;" onclick="${onClick}">
                    <i class="bi ${iconClass} fs-5"></i>
                </button>
            `;
            container.appendChild(newRow);
        }

        function removeEditUnitRow(button) {
            button.closest('.edit-unit-row').remove();
        }

        // Multi-Criteria Table Filter
        const searchInput = document.getElementById('searchInput');
        const unitFilter = document.getElementById('unitFilter');
        const statusFilter = document.getElementById('statusFilter');
        const genderFilter = document.getElementById('genderFilter');
        const alphabeticalSort = document.getElementById('alphabeticalSort');

        if (searchInput && unitFilter && statusFilter) {
            function filterTable() {
                const searchVal = searchInput.value.toLowerCase();
                const unitVal = unitFilter.value;
                const statusVal = statusFilter.value;
                const genderVal = genderFilter ? genderFilter.value : '';
                const rankVal = document.getElementById('rankFilter') ? document.getElementById('rankFilter').value : '';
                const bmiClassVal = document.getElementById('bmiClassFilter') ? document.getElementById('bmiClassFilter').value : '';
                const rows = document.querySelectorAll('tbody tr:not(.no-results)');

                rows.forEach(row => {
                    // Extract text for search (ignoring actions and pfp column)
                    const rowText = Array.from(row.cells)
                        .slice(1, 10) // Rank, Name, Age, User, Sex, Unit, BMI, Bday, Created
                        .map(c => c.textContent.toLowerCase())
                        .join(' ');
                    
                    const rowUnit = row.getAttribute('data-unit');
                    const rowStatus = row.getAttribute('data-status');
                    const rowGender = row.getAttribute('data-gender') || '';
                    const rowRank = row.getAttribute('data-rank') || '';
                    const rowBmiClass = row.getAttribute('data-bmi-class') || '';
                    
                    const matchesSearch = rowText.includes(searchVal);
                    // Fixed unit matching for multiple units
                    const matchesUnit = unitVal === "" || rowUnit.split(',').map(u => u.trim()).includes(unitVal);
                    const matchesStatus = statusVal === "" || rowStatus === statusVal;
                    const matchesGender = genderVal === "" || rowGender.toLowerCase() === genderVal.toLowerCase();
                    const matchesRank = rankVal === "" || rowRank === rankVal;
                    // Exact match for BMI Class
                    const matchesBmiClass = bmiClassVal === "" || rowBmiClass.trim() === bmiClassVal.trim();

                    row.style.display = (matchesSearch && matchesUnit && matchesStatus && matchesGender && matchesRank && matchesBmiClass) ? '' : 'none';
                });
            }

            searchInput.addEventListener('keyup', filterTable);
            unitFilter.addEventListener('change', function() {
                filterTable();
                updateActiveFilterLabel();
            });
            statusFilter.addEventListener('change', filterTable);
            if (genderFilter) genderFilter.addEventListener('change', filterTable);
            
            const bmiClassFilter = document.getElementById('bmiClassFilter');
            const rankFilter = document.getElementById('rankFilter');
            if (bmiClassFilter) bmiClassFilter.addEventListener('change', function() {
                filterTable();
                updateActiveFilterLabel();
            });
            if (rankFilter) rankFilter.addEventListener('change', filterTable);
            
            function updateActiveFilterLabel() {
                const label = document.getElementById('activeFilterLabel');
                if (!label) return;
                
                const uVal = unitFilter.value;
                const bVal = bmiClassFilter.value;
                
                if (uVal && bVal) {
                    label.textContent = `Office: ${uVal} | BMI: ${bVal}`;
                    label.style.display = 'inline-block';
                } else if (uVal) {
                    label.textContent = `Office: ${uVal}`;
                    label.style.display = 'inline-block';
                } else if (bVal) {
                    label.textContent = `BMI: ${bVal}`;
                    label.style.display = 'inline-block';
                } else {
                    label.style.display = 'none';
                }
            }

            // Alphabetical Sort (A-Z / Z-A)
            if (alphabeticalSort) {
                alphabeticalSort.addEventListener('change', function() {
                    const sortVal = this.value;
                    if (!sortVal) return;
                    
                    const table = document.querySelector('.table-masterlist');
                    if (!table) return;
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));
                    
                    rows.sort((a, b) => {
                        const nameA = (a.getAttribute('data-name') || '').toLowerCase();
                        const nameB = (b.getAttribute('data-name') || '').toLowerCase();
                        if (sortVal === 'az') {
                            return nameA.localeCompare(nameB);
                        } else {
                            return nameB.localeCompare(nameA);
                        }
                    });
                    
                    rows.forEach(row => tbody.appendChild(row));
                    
                    // Clear header sort indicators since we're using custom sort
                    const headers = table.querySelectorAll('thead th');
                    headers.forEach(h => h.classList.remove('asc', 'desc'));
                });
            }

            // Run initial filter if values exist in URL
            document.addEventListener('DOMContentLoaded', filterTable);
        }

        // Clickable Sorting
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('table');
            const headers = table.querySelectorAll('thead th');
            
            headers.forEach((header, index) => {
                if (index > 0 && index < 9) { // Don't sort PFP(0) or Action(9+) columns
                    header.style.cursor = 'pointer';
                    header.title = "Click to sort";
                    header.addEventListener('click', () => {
                        const tbody = table.querySelector('tbody');
                        const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));
                        const isAsc = header.classList.contains('asc');
                        
                        // Clear other headers
                        headers.forEach(h => h.classList.remove('asc', 'desc'));
                        
                        rows.sort((a, b) => {
                            const aVal = a.cells[index].textContent.trim();
                            const bVal = b.cells[index].textContent.trim();
                            
                            // Check if numeric
                            if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
                                return (parseFloat(aVal) - parseFloat(bVal)) * (isAsc ? -1 : 1);
                            }
                            return aVal.localeCompare(bVal) * (isAsc ? -1 : 1);
                        });
                        
                        header.classList.toggle('asc', !isAsc);
                        header.classList.toggle('desc', isAsc);
                        
                        rows.forEach(row => tbody.appendChild(row));
                    });
                }
            });
        });

        // Highlighting current active page logic (if ever needed on main.php)
        document.addEventListener("DOMContentLoaded", function() {
            // Display current date
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date();
            const dateDisplay = document.getElementById('current-date-display');
            if (dateDisplay) {
                dateDisplay.textContent = today.toLocaleDateString('en-US', dateOptions);
            }
            
            // Edit & Confirm Logic - only if elements exist
            const editBtn = document.getElementById('editBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            
            if (editBtn && confirmBtn) {
                const editableTexts = document.querySelectorAll('.editable-text');
                const editableInputs = document.querySelectorAll('.editable-input');

                editBtn.addEventListener('click', function() {
                    // Hide texts, show inputs
                    editableTexts.forEach(span => span.classList.add('d-none'));
                    editableInputs.forEach(input => {
                        input.classList.remove('d-none');
                        // Sync value from span to input
                        const spanId = input.id.replace('input-', 'text-');
                        const spanEl = document.getElementById(spanId);
                        if(spanEl) input.value = spanEl.innerText;
                    });
                    
                    // Toggle buttons
                    editBtn.classList.add('d-none');
                    confirmBtn.classList.remove('d-none');
                });

                confirmBtn.addEventListener('click', function() {
                    // Hide inputs, show texts
                    editableInputs.forEach(input => {
                        input.classList.add('d-none');
                        // Sync value from input to span
                        const spanId = input.id.replace('input-', 'text-');
                        const spanEl = document.getElementById(spanId);
                        if(spanEl) spanEl.innerText = input.value;
                    });
                    editableTexts.forEach(span => span.classList.remove('d-none'));
                    
                    // Toggle buttons
                    confirmBtn.classList.add('d-none');
                    editBtn.classList.remove('d-none');
                });
            }
        });

        <?php if (!empty($bmiData)): ?>
        // BMI Chart Instance
        let bmiChartObj = null;

        function initBMIChart() {
            const htmlElement = document.documentElement;
            const isDarkMode = htmlElement.getAttribute('data-bs-theme') === 'dark';
            const primaryColor = isDarkMode ? '#5eace0' : '#1700ad';
            const primaryBg = isDarkMode ? 'rgba(94, 172, 224, 0.1)' : 'rgba(23, 0, 173, 0.1)';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
            const legendTextColor = isDarkMode ? '#aab1c7' : '#000';

            const canvas = document.getElementById('bmiProgressChart');
            if (!canvas) return;

            if (bmiChartObj) {
                bmiChartObj.destroy();
            }

            const ctx1 = canvas.getContext('2d');
            bmiChartObj = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [
                        {
                            label: 'Your BMI Progress',
                            data: <?php echo json_encode($bmiData); ?>,
                            borderColor: primaryColor,
                            backgroundColor: primaryBg,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointBackgroundColor: primaryColor,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Acceptable/Normal Limit (Age-based)',
                            data: <?php echo json_encode($thresholdData); ?>,
                            borderColor: isDarkMode ? 'rgba(235, 69, 95, 0.7)' : '#dc3545',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Standard Normal (24.9)',
                            data: <?php echo json_encode($normalLine); ?>,
                            borderColor: '#198754',
                            borderWidth: 1.5,
                            borderDash: [2, 2],
                            pointRadius: 0,
                            fill: false,
                            hidden: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: gridColor },
                            ticks: { 
                                display: true,
                                color: legendTextColor
                            },
                            border: { display: false },
                            title: { display: false },
                            grace: '15%'
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: legendTextColor }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 20, padding: 15, color: legendTextColor, font: { size: 11, weight: 'bold' } }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: isDarkMode ? 'rgba(30, 30, 30, 0.95)' : 'rgba(23, 0, 173, 0.9)',
                            padding: 12,
                            callbacks: {
                                footer: function(tooltipItems) {
                                    const bmi = tooltipItems[0].parsed.y;
                                    const threshold = <?php echo $thresholdVal; ?>;
                                    if (bmi < 25) {
                                        return "Status: Meeting Normal BMI (Ideal).";
                                    } else if (bmi <= threshold) {
                                        return "Status: Meeting Age-Adjusted Acceptable BMI.";
                                    } else {
                                        return "Status: Above recommended BMI (Target: " + threshold + ").";
                                    }
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'valueLabels',
                    afterDatasetsDraw(chart) {
                        const {ctx} = chart;
                        ctx.save();
                        ctx.font = 'bold 12px sans-serif';
                        ctx.fillStyle = primaryColor;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        chart.data.datasets.forEach((dataset, i) => {
                            if (i !== 0) return; // Only for first dataset
                            const meta = chart.getDatasetMeta(i);
                            meta.data.forEach((element, index) => {
                                const data = dataset.data[index];
                                ctx.fillText(data, element.x, element.y - 10);
                            });
                        });
                        ctx.restore();
                    }
                }]
            });
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initBMIChart === 'function') initBMIChart();
        });
        <?php endif; ?>
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeSwitch = document.getElementById('theme-switch-checkbox');
            const htmlElement = document.documentElement;

            if (htmlElement.getAttribute('data-bs-theme') === 'dark') {
                themeSwitch.checked = true;
            }

            themeSwitch.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                
                // Add animation class
                document.body.classList.add('theme-transition-active');
                
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                if (typeof initBMIChart === 'function') initBMIChart();
                
                // Remove animation class after it finishes
                setTimeout(() => {
                    document.body.classList.remove('theme-transition-active');
                }, 800);
            });
        });

        // Global PFP Change Logic
        document.addEventListener('DOMContentLoaded', function() {
            const pfpInput = document.createElement('input');
            pfpInput.type = 'file';
            pfpInput.id = 'pfpInputGlobal';
            pfpInput.name = 'profile_pic';
            pfpInput.accept = 'image/*';
            pfpInput.className = 'd-none';
            document.body.appendChild(pfpInput);

            pfpInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const formData = new FormData();
                    formData.append('profile_pic', this.files[0]);
                    
                    // Simple fetch upload
                    fetch('settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error uploading profile picture:', error);
                        alert('Failed to upload profile picture.');
                    });
                }
            });
        });
    </script>
</body>
</html>
