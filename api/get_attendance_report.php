<?php
header('Content-Type: application/json');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $class_filter = $_GET['class'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    if (empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
        exit;
    }
    
    // Build query with optional filters
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
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = :status";
        $params['status'] = $status_filter;
    }
    
    $query .= " ORDER BY a.date DESC, a.time ASC";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics (always from unfiltered data for status counts)
    $summary_query = "SELECT a.*, s.first_name, s.last_name, s.class, s.lrn 
                      FROM attendance a 
                      JOIN students s ON a.lrn = s.lrn 
                      WHERE a.date BETWEEN :start_date AND :end_date";
    
    $summary_params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if (!empty($class_filter)) {
        $summary_query .= " AND s.class = :class";
        $summary_params['class'] = $class_filter;
    }
    
    $summary_stmt = $db->prepare($summary_query);
    foreach ($summary_params as $key => $value) {
        $summary_stmt->bindValue(':' . $key, $value);
    }
    $summary_stmt->execute();
    $all_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_records = count($all_data);
    $present_count = count(array_filter($all_data, function($record) {
        return $record['status'] === 'present';
    }));
    $late_count = count(array_filter($all_data, function($record) {
        return $record['status'] === 'late';
    }));
    $absent_count = count(array_filter($all_data, function($record) {
        return $record['status'] === 'absent';
    }));
    
    $summary = [
        'total' => $total_records,
        'present' => $present_count,
        'late' => $late_count,
        'absent' => $absent_count
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => $summary
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
