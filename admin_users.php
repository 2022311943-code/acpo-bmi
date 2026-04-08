<?php
// admin_users.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Access Control

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "Not so fast";
    header("Location: index.php");
    exit;
}

// Handle Account Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'logger.php';
    
    // Handle Approve All Action
    if (isset($_POST['action']) && $_POST['action'] === 'approve_all') {
        $update_all_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE status = 'pending'");
        if ($update_all_stmt->execute()) {
            $count = $update_all_stmt->rowCount();
            if ($count > 0) {
                logAction($_SESSION['user_id'], 0, 'Approve All', "Admin mass-approved $count pending users");
                $_SESSION['flash_success'] = "Successfully approved $count pending accounts.";
            } else {
                $_SESSION['flash_error'] = "No pending accounts found to approve.";
            }
        }
        header("Location: admin_users.php");
        exit;
    }

    if (isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Prevent actions on own account unless specified
        if ($user_id !== $_SESSION['user_id']) {
            
            // Handle Status Change
            if (isset($_POST['new_status'])) {
                $new_status = $_POST['new_status'];
                $stmt_old = $pdo->prepare("SELECT status FROM users WHERE id = ?");
                $stmt_old->execute([$user_id]);
                $old_status = $stmt_old->fetchColumn();

                $update_stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($update_stmt->execute([$new_status, $user_id])) {
                    $action = ($new_status === 'active') ? 'Approve/Enable User' : 'Disable User';
                    logAction($_SESSION['user_id'], $user_id, $action, "Changed status from $old_status to $new_status");
                }
            }

            // Handle Password Reset
            if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
                $default_pw = password_hash('Password@1234', PASSWORD_DEFAULT);
                $reset_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($reset_stmt->execute([$default_pw, $user_id])) {
                    logAction($_SESSION['user_id'], $user_id, 'Reset Password', "Admin reset user password to default: Password@1234");
                    $_SESSION['flash_success'] = "Password reset successfully to: Password@1234";
                }
            }
        }
        header("Location: admin_users.php");
        exit;
    }
}

// Search and Filter Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$query = "SELECT id, username, name, rank, birthday, age, gender, nationality, address, religion, contact, email, role, status, unit, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR username LIKE ? OR rank LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Manage Accounts</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }
        body { background-color: #f8f9fa; }
        .acpo-blue { background-color: #1700ad !important; }
        .acpo-nav {
            position: relative;
            z-index: 1030; border-bottom: 2px solid #e9ecef; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .acpo-nav .navbar-brand { display: flex; align-items: center; }
        .acpo-header-text {
            font-weight: 700; font-size: 1.2rem; color: #212529;
            font-family: inherit; line-height: 1.2; letter-spacing: 0.02em;
        }
        .acpo-nav .acpo-header-text { color: #ffffff !important; }
        .nav-link.acpo-header-text { padding: 0.5rem 0; color: #ffffff !important; transition: color 0.3s ease; }
        .acpo-nav .nav-link.acpo-header-text:hover { color: #e0e0e0 !important; }

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
        
        /* Ensure hamburger menu text is visible and consistent */
        .sidebar-links-container .acpo-header-text {
            color: #212529 !important;
        }
        .sidebar-links-container .nav-link.acpo-header-text:hover {
            color: #000000 !important;
        }

        .nav-link-underline { position: relative; display: inline-block; }
        .nav-link-underline::after {
            content: ''; position: absolute; left: 0; bottom: -2px;
            height: 3px; width: 0; background: currentColor; transition: width 0.35s ease;
        }
        .nav-link-underline:hover::after { width: 100%; }
        
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
        
        /* Table Styles */
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            border-radius: 50rem;
            text-transform: uppercase;
            font-weight: 700;
        }
        .status-active { background-color: #d1e7dd; color: #0f5132; }
        .status-pending { background-color: #fff3cd; color: #664d03; }
        .status-disabled { background-color: #f8d7da; color: #842029; }
        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-white {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .table {
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .table-striped>tbody>tr:nth-of-type(odd)>* {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.05) !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .text-black, [data-bs-theme="dark"] .text-dark {
            color: #ffffff !important;
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
        [data-bs-theme="dark"] .input-group-text {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #e0e0e0 !important;
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
    <script>
        // Apply theme immediately to prevent flicker
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Header -->
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

    <!-- Sidebar -->
    <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar">
        <div class="offcanvas-header pb-0 border-0">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas"></button>
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
    <main class="flex-grow-1 p-4">
        <div class="container" style="max-width: 1200px;">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <h2 class="fw-bold text-uppercase m-0" style="color: #1700ad; font-family: 'Agrandir', sans-serif;">Account Approval & Management</h2>
                
                <!-- Mass Approve Feature -->
                <form method="post" action="admin_users.php" class="m-0" onsubmit="return confirm('Are you sure you want to approve all currently pending accounts? This action cannot be undone.');">
                    <input type="hidden" name="action" value="approve_all">
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-flex align-items-center">
                        <i class="bi bi-check2-all me-2" style="font-size: 1.2rem;"></i> APPROVE ALL
                    </button>
                </form>
            </div>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Form -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-secondary"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, username, or rank..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="status_filter" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="disabled" <?php echo $status_filter === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold acpo-blue border-0">
                                FILTER
                            </button>
                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                <a href="admin_users.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light text-uppercase small fw-bold" style="color: #1700ad;">

                                <tr>
                                    <th class="px-4 py-3">Name / Rank</th>
                                    <th class="px-4 py-3">Username</th>
                                    <th class="px-4 py-3">Role</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <small class="text-secondary"><?php echo htmlspecialchars($user['rank']); ?></small>
                                    </td>
                                    <td class="px-4 py-3 text-secondary"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-3 text-capitalize"><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <?php
                                        $statusClass = match($user['status']) {
                                            'active' => 'status-active',
                                            'pending' => 'status-pending',
                                            'disabled' => 'status-disabled',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <?php if ($user['role'] !== 'admin' || $user['id'] !== $_SESSION['user_id']): ?>
                                            <form method="post" class="d-inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <?php if ($user['status'] === 'pending' || $user['status'] === 'disabled'): ?>
                                                    <button type="submit" name="new_status" value="active" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                                                        <i class="bi bi-check-circle me-1"></i> Approve/Enable
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['status'] === 'active' || $user['status'] === 'pending'): ?>
                                                    <button type="submit" name="new_status" value="disabled" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold ms-1">
                                                        <i class="bi bi-ban me-1"></i> Disable
                                                    </button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="reset_password" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold ms-1" onclick="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($user['name']); ?> to the default Password@1234?')">
                                                    <i class="bi bi-shield-lock me-1"></i> Reset PW
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Super Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

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