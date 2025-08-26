<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Dashboard';

// Get dashboard statistics
try {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $totalStudents = $stmt->fetch()['total'];
    
    // Today's attendance
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT lrn) as present FROM attendance WHERE date = CURDATE()");
    $stmt->execute();
    $presentToday = $stmt->fetch()['present'];
    
    // Today's attendance rate
    $attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
    
    // Total attendance records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance");
    $totalRecords = $stmt->fetch()['total'];
    
    // Active classes
    $stmt = $pdo->query("SELECT COUNT(DISTINCT class) as total FROM students");
    $activeClasses = $stmt->fetch()['total'];
    
    // Recent attendance (last 10 records)
    $stmt = $pdo->prepare("
        SELECT a.lrn, s.first_name, s.last_name, a.subject, a.status, a.time, a.date
        FROM attendance a 
        JOIN students s ON a.lrn = s.lrn 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Late students today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as late_count 
        FROM attendance 
        WHERE date = CURDATE() AND status = 'late'
    ");
    $stmt->execute();
    $lateToday = $stmt->fetch()['late_count'];
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $totalStudents = $presentToday = $totalRecords = $activeClasses = $lateToday = 0;
    $attendanceRate = 0;
    $recentAttendance = [];
}

// Include the admin header
include 'includes/header.php';
?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Students</span>
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <h2 class="stat-number"><?php echo number_format($totalStudents); ?></h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Present Today</span>
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <h2 class="stat-number"><?php echo number_format($presentToday); ?></h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Late Today</span>
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <h2 class="stat-number"><?php echo number_format($lateToday); ?></h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Attendance Rate</span>
                            <div class="stat-icon info">
                                <i class="fas fa-percentage"></i>
                            </div>
                        </div>
                        <h2 class="stat-number"><?php echo $attendanceRate; ?>%</h2>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Recent Attendance -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clock"></i> Recent Attendance</h3>
                            <a href="attendance_reports.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-chart-bar"></i> View Full Report
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentAttendance)): ?>
                                <?php foreach ($recentAttendance as $record): ?>
                                    <div class="recent-item">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($record['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="student-details">
                                                <h4><?php echo sanitizeOutput($record['first_name'] . ' ' . $record['last_name']); ?></h4>
                                                <p><?php echo sanitizeOutput($record['subject']); ?> • <?php echo date('M d, Y g:i A', strtotime($record['date'] . ' ' . $record['time'])); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge <?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-attendance-text">No recent attendance records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions-list">
                                <a href="manage_students.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Add New Student
                                </a>
                                <a href="manual_attendance.php" class="btn btn-primary">
                                    <i class="fas fa-clipboard-check"></i> Manual Attendance
                                </a>
                                <a href="manage_schedules.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Manage Schedules
                                </a>
                                <a href="../scan_attendance.php" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-qrcode"></i> QR Scanner
                                </a>
                            </div>
                            
                            <div class="system-info">
                                <h4>System Info</h4>
                                <p>
                                    <strong>Total Records:</strong> <?php echo number_format($totalRecords); ?>
                                </p>
                                <p>
                                    <strong>Active Classes:</strong> <?php echo $activeClasses; ?>
                                </p>
                                <p>
                                    <strong>Last Updated:</strong> <?php echo date('M d, Y g:i A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

<script>
    // Auto-refresh dashboard every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
</script>

<?php 
$additionalScripts = ['js/dashboard.js'];
include 'includes/footer.php'; 
?>
