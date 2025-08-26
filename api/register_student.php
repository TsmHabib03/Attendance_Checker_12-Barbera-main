<?php
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
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $class = $_POST['class'] ?? '';
    
    // Validate required fields
    if (empty($lrn) || empty($first_name) || empty($last_name) || empty($email) || empty($class)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate LRN format (11-13 digits, numeric only)
    if (!preg_match('/^[0-9]{11,13}$/', $lrn)) {
        echo json_encode(['success' => false, 'message' => 'LRN must be 11-13 digits (numeric only)']);
        exit;
    }
    
    // Check if LRN already exists
    $check_query = "SELECT id FROM students WHERE lrn = :lrn OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':lrn', $lrn);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'LRN or email already exists']);
        exit;
    }
    
    // Generate QR code data (LRN + timestamp for uniqueness)
    $qr_data = $lrn . '|' . time();
    
    // Insert new student
    $query = "INSERT INTO students (lrn, first_name, last_name, email, class, qr_code) 
              VALUES (:lrn, :first_name, :last_name, :email, :class, :qr_code)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lrn', $lrn);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':class', $class);
    $stmt->bindParam(':qr_code', $qr_data);
    
    if ($stmt->execute()) {
        // Generate QR code image using Google Charts API
        $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
        $qr_code_html = '<img src="' . $qr_code_url . '" alt="QR Code for LRN ' . htmlspecialchars($lrn) . '">';
        
        echo json_encode([
            'success' => true, 
            'message' => 'Student registered successfully!',
            'qr_code' => $qr_code_html,
            'lrn' => $lrn
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to register student']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
