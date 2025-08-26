<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $today = date('Y-m-d');
    
    // Get total students
    $total_students_query = "SELECT COUNT(*) as total FROM students";
    $total_students_stmt = $db->prepare($total_students_query);
    $total_students_stmt->execute();
    $total_students = $total_students_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get present today
    $present_today_query = "SELECT COUNT(*) as present FROM attendance WHERE date = :today";
    $present_today_stmt = $db->prepare($present_today_query);
    $present_today_stmt->bindParam(':today', $today);
    $present_today_stmt->execute();
    $present_today = $present_today_stmt->fetch(PDO::FETCH_ASSOC)['present'];
    
    // Calculate attendance rate
    $attendance_rate = $total_students > 0 ? round(($present_today / $total_students) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $total_students,
            'present_today' => $present_today,
            'attendance_rate' => $attendance_rate
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
