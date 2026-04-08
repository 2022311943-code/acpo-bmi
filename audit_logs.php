<?php
// audit_logs.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Admin Only Access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "Unauthorized access.";
    header("Location: index.php");
    exit;
}

// Filtering Parameters
$search = $_GET['search'] ?? '';
$rank_filter = $_GET['rank'] ?? '';

// Fetch Logs with User Names
try {
    $where_clauses = [];
    $params = [];
    
    if ($search) {
        $where_clauses[] = "target.name LIKE ?";
        $params[] = "%$search%";
    }
    
    if ($rank_filter) {
        $where_clauses[] = "target.rank = ?";
        $params[] = $rank_filter;
    }
    
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $sql = "
        SELECT al.*, 
               admin.name AS admin_name, admin.rank AS admin_rank,
               target.name AS target_name, target.rank AS target_rank
        FROM audit_logs al
        LEFT JOIN users admin ON al.admin_id = admin.id
        LEFT JOIN users target ON al.target_user_id = target.id
        $where_sql
        ORDER BY al.created_at DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching logs: " . $e->getMessage());
}

function getRankAcronym($rank) {
    $ranks = [
        'Police General (PGEN)' => 'PGEN',
        'Police Lieutenant General (PLTGEN)' => 'PLTGEN',
        'Police Major General (PMGEN)' => 'PMGEN',
        'Police Brigadier General (PBGEN)' => 'PBGEN',
        'Police Colonel (PCOL)' => 'PCOL',
        'Police Lieutenant Colonel (PLTCOL)' => 'PLTCOL',
        'Police Major (PMAJ)' => 'PMAJ',
        'Police Captain (PCPT)' => 'PCPT',
        'Police Lieutenant (PLT)' => 'PLT',
        'Police Executive Master Sergeant (PEMS)' => 'PEMS',
        'Police Chief Master Sergeant (PCMS)' => 'PCMS',
        'Police Senior Master Sergeant (PSMS)' => 'PSMS',
        'Police Master Sergeant (PMSg)' => 'PMSg',
        'Police Staff Sergeant (PSSg)' => 'PSSg',
        'Police Corporal (PCpl)' => 'PCpl',
        'Patrolman / Patrolwoman (Pat)' => 'Pat'
    ];
    return $ranks[$rank] ?? $rank;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | ACPO</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }

        :root {
            --acpo-blue: #1700ad;
            --acpo-dark: #000851;
            --acpo-accent: #33AFFF;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
        }

        .acpo-blue { background-color: #1700ad !important; }
        .acpo-nav .navbar-brand { display: flex; align-items: center; }
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #212529;
            font-family: inherit;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .acpo-nav .acpo-header-text { color: #ffffff !important; }
        .hamburger-icon {
            font-size: 2.8rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .hamburger-icon:hover {
            color: #e0e0e0 !important;
        }

        /* Audit Log Specific Styles */
        .card-custom {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            background-color: #fff;
        }
        .table-custom thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #eee;
        }
        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #6c757d;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f8f9fa;
        }
        .table-scroll-container {
            max-height: 600px;
            overflow-y: auto;
            position: relative;
        }
        /* Fix for sticky-top background in dark mode */
        [data-bs-theme="dark"] .table-custom th {
            background-color: #2d2d2d !important;
            color: #ffffff !important;
        }
        .badge-add {
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
        }
        .badge-update {
            background-color: #fff3cd !important;
            color: #664d03 !important;
        }
        .badge-delete {
            background-color: #f8d7da !important;
            color: #842029 !important;
        }
        .log-details {
            background-color: #fdfdfd;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            font-size: 0.85rem;
            color: #444;
            max-width: 450px;
        }
        .btn-back {
            background: #fff;
            border: 1.5px solid #eee;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            color: #444;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }
        .btn-back:hover {
            background: #f8f9fa;
            border-color: #ddd;
            transform: translateX(-5px);
            color: #000;
        }

        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-white {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-light {
            background-color: #2d2d2d !important;
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
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
        [data-bs-theme="dark"] .log-details {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .card-custom {
            background-color: #1e1e1e !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        [data-bs-theme="dark"] .btn-back {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .btn-back:hover {
            background-color: #333;
            color: #fff;
        }
        [data-bs-theme="dark"] .badge-add { background-color: #0f5132 !important; color: #d1e7dd !important; }
        [data-bs-theme="dark"] .badge-update { background-color: #664d03 !important; color: #fff3cd !important; }
        [data-bs-theme="dark"] .badge-delete { background-color: #842029 !important; color: #f8d7da !important; }

                
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
        .user-sidebar .btn-close:hover { opacity: 1; }
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
        .profile-placeholder:hover { transform: scale(1.05); }
        .profile-placeholder img { border: 3px solid #ffffff !important; }
        .sidebar-name {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            text-transform: uppercase;
        }
        .sidebar-links-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
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
        .logout-btn-container {
            margin-top: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff !important;
            padding: 12px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 65, 108, 0.3);
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 65, 108, 0.4);
            color: #fff !important;
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
            transform: scale(1.05);
        }
        [data-bs-theme="dark"] .theme-switch:hover .theme-switch-knob {
            transform: translateX(36px) scale(1.05);
        }
        .theme-switch:active .theme-switch-knob {
            transform: scale(0.95);
        }
        [data-bs-theme="dark"] .theme-switch:active .theme-switch-knob {
            transform: translateX(36px) scale(0.95);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 overflow-x-hidden">

    <!-- Header -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-2 px-lg-4">
            <a href="main.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5 acpo-brand-container">
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
            <!-- Profile Placeholder Image -->
            <div class="profile-placeholder d-flex justify-content-center">
                <img src="images/placeholder.png" alt="Profile Background" class="img-fluid rounded-circle shadow-sm" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23e9ecef\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
            </div>
            
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

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold text-uppercase mb-1" style="color: var(--acpo-blue);">Administrative Audit Logs</h1>
            <p class="text-secondary mb-0">Tracking all administrative actions for transparency and accountability.</p>
        </div>
        <a href="main.php" class="btn btn-back">
            <i class="bi bi-arrow-left me-2"></i> BACK TO MASTERLIST
        </a>
    </div>

    <!-- Filters and Search -->
    <div class="card card-custom mb-4 p-4 bg-white shadow-sm border-0" style="border-radius: 20px;">
        <form action="audit_logs.php" method="GET" class="row align-items-end g-3">
            <div class="col-md-5">
                <label for="search" class="form-label small fw-bold text-secondary">SEARCH TARGET PERSONNEL</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0" style="border-radius: 12px 0 0 12px;"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="search" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type Name..." style="border-radius: 0 12px 12px 0; height: 45px;">
                </div>
            </div>
            <div class="col-md-4">
                <label for="rank" class="form-label small fw-bold text-secondary">FILTER BY RANK</label>
                <select name="rank" id="rank" class="form-select bg-light border-0" style="border-radius: 12px; height: 45px;">
                    <option value="">ALL RANKS</option>
                    <?php 
                        $rank_list = [
                            'Police General (PGEN)', 'Police Lieutenant General (PLTGEN)', 'Police Major General (PMGEN)', 
                            'Police Brigadier General (PBGEN)', 'Police Colonel (PCOL)', 'Police Lieutenant Colonel (PLTCOL)', 
                            'Police Major (PMAJ)', 'Police Captain (PCPT)', 'Police Lieutenant (PLT)', 
                            'Police Executive Master Sergeant (PEMS)', 'Police Chief Master Sergeant (PCMS)', 
                            'Police Senior Master Sergeant (PSMS)', 'Police Master Sergeant (PMSg)', 
                            'Police Staff Sergeant (PSSg)', 'Police Corporal (PCpl)', 'Patrolman / Patrolwoman (Pat)'
                        ];
                        foreach($rank_list as $rl): 
                    ?>
                        <option value="<?php echo $rl; ?>" <?php echo ($rank_filter === $rl) ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill fw-bold rounded-pill shadow-sm" style="height: 45px; background: linear-gradient(135deg, #1700ad 0%, #33AFFF 100%); border:none;">APPLY FILTER</button>
                <a href="audit_logs.php" class="btn btn-outline-secondary flex-fill fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center" style="height: 45px; border-width: 2px;">RESET</a>
            </div>
        </form>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <div class="table-responsive table-scroll-container">
                <table class="table table-custom mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3">Timestamp</th>
                            <th class="py-3">Admin</th>
                            <th class="py-3">Target Personnel</th>
                            <th class="py-3">Action</th>
                            <th class="py-3 pe-4">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="opacity-50">
                                        <i class="bi bi-journal-x display-4 mb-3 d-block"></i>
                                        NO LOGS FOUND.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4 py-3 small text-secondary">
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                        <span class="fw-bold text-dark"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['admin_name']); ?></div>
                                        <div class="small text-secondary"><?php echo getRankAcronym($log['admin_rank']); ?></div>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($log['target_name']): ?>
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($log['target_name']); ?></div>
                                            <div class="small text-secondary"><?php echo getRankAcronym($log['target_rank']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted italic">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php 
                                            $badgeClass = 'badge-update';
                                            if (stripos($log['action_type'], 'Add') !== false) $badgeClass = 'badge-add';
                                            if (stripos($log['action_type'], 'Delete') !== false) $badgeClass = 'badge-delete';
                                        ?>
                                        <span class="badge rounded-pill px-3 py-2 <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($log['action_type']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pe-4">
                                        <div class="log-details shadow-sm">
                                            <?php echo htmlspecialchars($log['action_details']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeSwitch = document.getElementById('theme-switch-checkbox');
            const htmlElement = document.documentElement;

            if (htmlElement.getAttribute('data-bs-theme') === 'dark') {
                themeSwitch.checked = true;
            }

            themeSwitch.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        });
    </script>
</body>
</html>
