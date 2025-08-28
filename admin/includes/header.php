<?php
// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin - Attendance System</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Admin CSS with cache busting -->
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
                <p>Attendance System</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'class="active"' : ''; ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="view_students.php" <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['view_students.php', 'manage_students.php'])) ? 'class="active"' : ''; ?>><i class="fas fa-users"></i> Student List</a></li>
                    <li><a href="manage_schedules.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_schedules.php') ? 'class="active"' : ''; ?>><i class="fas fa-calendar-alt"></i> Manage Schedules</a></li>
                    <li><a href="manual_attendance.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'manual_attendance.php') ? 'class="active"' : ''; ?>><i class="fas fa-clipboard-check"></i> Manual Attendance</a></li>
                    <li><a href="attendance_reports.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_reports.php') ? 'class="active"' : ''; ?>><i class="fas fa-chart-bar"></i> Attendance Reports</a></li>
                    <li><a href="../index.php" target="_blank"><i class="fas fa-globe"></i> View Site</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1><i class="fas fa-<?php echo isset($pageIcon) ? $pageIcon : 'tachometer-alt'; ?>"></i> <?php echo isset($pageTitle) ? $pageTitle : 'Admin Panel'; ?></h1>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?php echo isset($currentAdmin) ? strtoupper(substr($currentAdmin['username'], 0, 1)) : 'A'; ?>
                    </div>
                    <div class="admin-details">
                        <span class="admin-name"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['username']) : 'Admin'; ?></span>
                        <span class="admin-role"><?php echo isset($currentAdmin) ? sanitizeOutput($currentAdmin['role']) : 'Administrator'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
