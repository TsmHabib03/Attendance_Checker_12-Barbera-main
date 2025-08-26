<?php
// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $lrn = $_POST['lrn'] ?? '';
    
    if (empty($lrn)) {
        echo json_encode(['success' => false, 'message' => 'LRN is required']);
        exit;
    }
    
    // Check if student exists
    $student_query = "SELECT * FROM students WHERE lrn = :lrn";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':lrn', $lrn);
    $student_stmt->execute();
    
    if ($student_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current day and time in Philippine timezone
    $current_day = date('l'); // Monday, Tuesday, etc.
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    // Debug info
    error_log("Current time (PHP): " . $current_datetime);
    error_log("Current day: " . $current_day);
    error_log("Current time: " . $current_time);
    
    // Find the appropriate class period based on current time
    $schedule_query = "SELECT *, 
                       TIME(:current_time) as current_time_formatted,
                       CASE 
                           WHEN :current_time < start_time THEN 'before'
                           WHEN :current_time BETWEEN start_time AND end_time THEN 'during'
                           WHEN :current_time > end_time THEN 'after'
                       END as time_status
                       FROM schedule 
                       WHERE class = :class 
                       AND day_of_week = :day 
                       ORDER BY 
                           CASE 
                               WHEN :current_time BETWEEN start_time AND end_time THEN 1
                               WHEN :current_time < start_time THEN 2
                               WHEN :current_time > end_time THEN 3
                           END,
                           ABS(TIME_TO_SEC(TIMEDIFF(:current_time, start_time)))
                       LIMIT 1";
    
    $schedule_stmt = $db->prepare($schedule_query);
    $schedule_stmt->bindParam(':class', $student['class']);
    $schedule_stmt->bindParam(':day', $current_day);
    $schedule_stmt->bindParam(':current_time', $current_time);
    $schedule_stmt->execute();
    
    $relevant_schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$relevant_schedule) {
        echo json_encode([
            'success' => false, 
            'message' => 'No class scheduled today for your section',
            'current_time' => date('h:i A'),
            'current_day' => $current_day,
            'debug_time' => $current_datetime
        ]);
        exit;
    }
    
    $subject = $relevant_schedule['subject'];
    $period_number = $relevant_schedule['period_number'];
    $is_break = $relevant_schedule['is_break'];
    $time_status = $relevant_schedule['time_status'];
    $class_start = $relevant_schedule['start_time'];
    $class_end = $relevant_schedule['end_time'];
    
    // Determine attendance status based on time and schedule
    $attendance_status = '';
    $message = '';
    
    if ($is_break) {
        $attendance_status = 'no_class';
        $message = 'Break time - No attendance required';
    } else if ($subject === 'VACANT' || $subject === 'RDL/VACANT') {
        $attendance_status = 'no_class';
        $message = 'Vacant period - No attendance required';
    } else {
        switch ($time_status) {
            case 'during':
                // Check if it's within first 15 minutes (present) or later (late)
                $late_threshold = date('H:i:s', strtotime($class_start . ' +15 minutes'));
                $attendance_status = ($current_time <= $late_threshold) ? 'present' : 'late';
                $message = ($attendance_status === 'present') ? 
                    'Attendance marked as Present' : 'Attendance marked as Late';
                break;
                
            case 'after':
                $attendance_status = 'absent';
                $message = 'Class has ended - Marked as Absent';
                break;
                
            case 'before':
                // If it's more than 30 minutes before class, don't allow
                $early_threshold = date('H:i:s', strtotime($class_start . ' -30 minutes'));
                if ($current_time < $early_threshold) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Too early to mark attendance. Class starts at ' . date('h:i A', strtotime($class_start)),
                        'next_class' => $subject,
                        'class_time' => date('h:i A', strtotime($class_start)) . ' - ' . date('h:i A', strtotime($class_end))
                    ]);
                    exit;
                } else {
                    // Allow early attendance (up to 30 minutes before)
                    $attendance_status = 'present';
                    $message = 'Early attendance marked as Present';
                }
                break;
        }
    }
    
    // Check if attendance already marked for this period today
    $check_query = "SELECT * FROM attendance WHERE lrn = :lrn AND date = :today AND period_number = :period";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':lrn', $lrn);
    $check_stmt->bindParam(':today', $today);
    $check_stmt->bindParam(':period', $period_number);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => false, 
            'message' => 'Attendance already marked for ' . $existing['subject'] . ' (' . $existing['status'] . ') at ' . date('h:i A', strtotime($existing['time'])),
            'existing_record' => [
                'subject' => $existing['subject'],
                'status' => $existing['status'],
                'time' => date('h:i A', strtotime($existing['time']))
            ]
        ]);
        exit;
    }
    
    // Insert attendance record
    $insert_query = "INSERT INTO attendance (lrn, date, time, subject, period_number, status) 
                     VALUES (:lrn, :date, :time, :subject, :period_number, :status)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':lrn', $lrn);
    $insert_stmt->bindParam(':date', $today);
    $insert_stmt->bindParam(':time', $current_time);
    $insert_stmt->bindParam(':subject', $subject);
    $insert_stmt->bindParam(':period_number', $period_number);
    $insert_stmt->bindParam(':status', $attendance_status);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'lrn' => $lrn,
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'time' => date('h:i:s A'),
            'subject' => $subject,
            'period' => $period_number,
            'status' => ucfirst($attendance_status),
            'class_time' => date('h:i A', strtotime($class_start)) . ' - ' . date('h:i A', strtotime($class_end)),
            'scan_time' => date('h:i:s A'),
            'debug_info' => [
                'php_time' => $current_datetime,
                'time_status' => $time_status,
                'is_break' => $is_break
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
