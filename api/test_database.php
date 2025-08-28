<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Test database connection
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Test if tables exist
    $tables = ['attendance', 'students'];
    $existing_tables = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT 1 FROM `$table` LIMIT 1");
            $existing_tables[] = $table;
        } catch (Exception $e) {
            $existing_tables[] = "$table (ERROR: " . $e->getMessage() . ")";
        }
    }
    
    // Get sample data counts
    $attendance_count = 0;
    $students_count = 0;
    
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM attendance");
        $attendance_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $attendance_count = "Error: " . $e->getMessage();
    }
    
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM students");
        $students_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $students_count = "Error: " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'tables' => $existing_tables,
        'data_counts' => [
            'attendance' => $attendance_count,
            'students' => $students_count
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database test failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
