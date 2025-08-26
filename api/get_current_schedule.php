<?php
// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $current_day = date('l'); // Monday, Tuesday, etc.
    $current_time = date('H:i:s');
    $class = $_GET['class'] ?? '12-BARBERRA';
    
    // Get current schedule
    $current_query = "SELECT * FROM schedule 
                     WHERE class = :class 
                     AND day_of_week = :day 
                     AND :current_time BETWEEN start_time AND end_time 
                     LIMIT 1";
    
    $current_stmt = $db->prepare($current_query);
    $current_stmt->bindParam(':class', $class);
    $current_stmt->bindParam(':day', $current_day);
    $current_stmt->bindParam(':current_time', $current_time);
    $current_stmt->execute();
    
    $current_schedule = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's full schedule
    $today_query = "SELECT * FROM schedule 
                    WHERE class = :class 
                    AND day_of_week = :day 
                    ORDER BY start_time ASC";
    
    $today_stmt = $db->prepare($today_query);
    $today_stmt->bindParam(':class', $class);
    $today_stmt->bindParam(':day', $current_day);
    $today_stmt->execute();
    
    $today_schedule = $today_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format times for display
    foreach ($today_schedule as &$period) {
        $period['start_time_formatted'] = date('h:i A', strtotime($period['start_time']));
        $period['end_time_formatted'] = date('h:i A', strtotime($period['end_time']));
    }
    
    echo json_encode([
        'success' => true,
        'current_day' => $current_day,
        'current_time' => date('h:i A'),
        'current_time_24h' => $current_time,
        'current_period' => $current_schedule,
        'today_schedule' => $today_schedule,
        'class' => $class
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
