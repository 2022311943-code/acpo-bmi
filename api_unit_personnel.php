<?php
// api_unit_personnel.php
// Returns JSON list of personnel for a given unit (used by BMI form extraction feature)
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$unit = $_GET['unit'] ?? '';
if (empty($unit)) {
    echo json_encode(['error' => 'No unit specified']);
    exit;
}

$chq_sub_units = ['CHQ', 'ACDEU', 'CIU', 'COMU', 'CIDMU', 'CARMU', 'CPPU', 'CCADU', 'GSO', 'LSO', 'HRAO', 'CPSMU', 'DCBA', 'ODCDO', 'PIO', 'BFO', 'CPHAU', 'OCD', 'OCESPO', 'WCPD', 'HRDD', 'TEU', 'CMFC'];

try {
    if ($unit === 'ALL') {
        $sql = "SELECT id, name, rank, unit FROM users WHERE role = 'user' ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } elseif ($unit === 'CHQ') {
        $placeholders = implode(',', array_fill(0, count($chq_sub_units), '?'));
        $sql = "SELECT id, name, rank, unit FROM users WHERE role = 'user' AND unit IN ($placeholders) ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($chq_sub_units);
    } else {
        $sql = "SELECT id, name, rank, unit FROM users WHERE role = 'user' AND unit = ? ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unit]);
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'personnel' => $users, 'count' => count($users)]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
