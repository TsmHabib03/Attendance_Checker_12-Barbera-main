<?php
require_once '../../includes/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

header('Content-Type: application/json');

try {
    // Get admin user info if logged in
    session_start();
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    // Get basic dashboard statistics
    $stats = [
        'total_students' => 0,
        'present_today' => 0,
        'attendance_rate' => 0,
        'total_records' => 0,
        'active_classes' => 0,
        'late_today' => 0
    ];
    
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $stats['total_students'] = (int) $stmt->fetch()['total'];
    
    // Today's attendance
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT lrn) as present FROM attendance WHERE date = CURDATE()");
    $stmt->execute();
    $stats['present_today'] = (int) $stmt->fetch()['present'];
    
    // Today's attendance rate
    $stats['attendance_rate'] = $stats['total_students'] > 0 ? 
        round(($stats['present_today'] / $stats['total_students']) * 100, 1) : 0;
    
    // Total attendance records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance");
    $stats['total_records'] = (int) $stmt->fetch()['total'];
    
    // Active classes
    $stmt = $pdo->query("SELECT COUNT(DISTINCT class) as total FROM students");
    $stats['active_classes'] = (int) $stmt->fetch()['total'];
    
    // Late students today
    $stmt = $pdo->prepare("SELECT COUNT(*) as late_count FROM attendance WHERE date = CURDATE() AND status = 'late'");
    $stmt->execute();
    $stats['late_today'] = (int) $stmt->fetch()['late_count'];
    
    // Recent attendance if admin
    $recent_attendance = [];
    if ($isAdmin) {
        $stmt = $pdo->prepare("
            SELECT a.lrn, s.first_name, s.last_name, a.subject, a.status, a.time, a.date
            FROM attendance a 
            JOIN students s ON a.lrn = s.lrn 
            ORDER BY a.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_attendance' => $recent_attendance,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
