<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $class_filter = $_GET['class'] ?? '';
    
    if (empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
        exit;
    }
    
    // Build query with optional class filter
    $query = "SELECT a.*, s.first_name, s.last_name, s.class, s.lrn 
              FROM attendance a 
              JOIN students s ON a.lrn = s.lrn 
              WHERE a.date BETWEEN :start_date AND :end_date";
    
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if (!empty($class_filter)) {
        $query .= " AND s.class = :class";
        $params['class'] = $class_filter;
    }
    
    $query .= " ORDER BY a.date DESC, a.time ASC";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $total_records = count($data);
    $present_count = count(array_filter($data, function($record) {
        return $record['status'] === 'present';
    }));
    $late_count = count(array_filter($data, function($record) {
        return $record['status'] === 'late';
    }));
    
    // Get total students for attendance rate calculation
    $total_students_query = "SELECT COUNT(*) as total FROM students";
    if (!empty($class_filter)) {
        $total_students_query .= " WHERE class = :class";
    }
    $total_students_stmt = $db->prepare($total_students_query);
    if (!empty($class_filter)) {
        $total_students_stmt->bindParam(':class', $class_filter);
    }
    $total_students_stmt->execute();
    $total_students = $total_students_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate date range for attendance rate
    $date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
    $expected_total = $total_students * $date_diff;
    $attendance_rate = $expected_total > 0 ? round(($total_records / $expected_total) * 100, 1) : 0;
    
    // Prepare chart data (daily attendance counts)
    $chart_data = [
        'dates' => [],
        'present' => [],
        'late' => []
    ];
    
    // Group data by date
    $daily_data = [];
    foreach ($data as $record) {
        $date = $record['date'];
        if (!isset($daily_data[$date])) {
            $daily_data[$date] = ['present' => 0, 'late' => 0];
        }
        $daily_data[$date][$record['status']]++;
    }
    
    // Sort dates and prepare chart data
    ksort($daily_data);
    foreach ($daily_data as $date => $counts) {
        $chart_data['dates'][] = date('M d', strtotime($date));
        $chart_data['present'][] = $counts['present'];
        $chart_data['late'][] = $counts['late'];
    }
    
    $summary = [
        'total_records' => $total_records,
        'present_count' => $present_count,
        'late_count' => $late_count,
        'attendance_rate' => $attendance_rate,
        'date_range' => $date_diff,
        'total_students' => $total_students
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => $summary,
        'chart_data' => $chart_data
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
