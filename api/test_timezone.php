<?php
// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get PHP time
    $php_time = date('Y-m-d H:i:s');
    $php_timezone = date_default_timezone_get();
    
    // Get MySQL time
    $mysql_stmt = $db->query("SELECT NOW() as mysql_time, @@session.time_zone as mysql_timezone");
    $mysql_result = $mysql_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test current schedule detection
    $current_day = date('l');
    $current_time = date('H:i:s');
    
    $schedule_query = "SELECT * FROM schedule 
                       WHERE class = '12-BARBERRA' 
                       AND day_of_week = :day 
                       AND :current_time BETWEEN start_time AND end_time 
                       LIMIT 1";
    
    $schedule_stmt = $db->prepare($schedule_query);
    $schedule_stmt->bindParam(':day', $current_day);
    $schedule_stmt->bindParam(':current_time', $current_time);
    $schedule_stmt->execute();
    
    $current_schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'php_info' => [
            'time' => $php_time,
            'timezone' => $php_timezone,
            'current_day' => $current_day,
            'current_time' => $current_time,
            'formatted_time' => date('h:i:s A')
        ],
        'mysql_info' => [
            'time' => $mysql_result['mysql_time'],
            'timezone' => $mysql_result['mysql_timezone']
        ],
        'schedule_info' => [
            'current_period' => $current_schedule,
            'is_class_time' => !empty($current_schedule)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
