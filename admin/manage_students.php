<?php
// Manage Students - Add/Edit Form Only
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = isset($_GET['id']) ? 'Edit Student' : 'Add Student';
$pageIcon = isset($_GET['id']) ? 'edit' : 'plus';

// Initialize variables
$message = '';
$messageType = 'info';
$editMode = false;
$editStudent = null;

// Check if editing
if (isset($_GET['id'])) {
    $editMode = true;
    $editId = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$editId]);
        $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editStudent) {
            $message = "Student not found.";
            $messageType = "error";
        }
    } catch (Exception $e) {
        $message = "Error retrieving student information.";
        $messageType = "error";
        error_log("Edit student error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {
        $action = $_POST['action'] ?? '';
        $lrn = trim($_POST['lrn'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class = trim($_POST['class'] ?? '');
        
        // Validation
        if (empty($lrn) || empty($firstName) || empty($lastName) || empty($gender) || empty($email) || empty($class)) {
            $message = "All required fields must be filled.";
            $messageType = "error";
        } elseif (!in_array($gender, ['Male', 'Female'])) {
            $message = "Please select a valid gender.";
            $messageType = "error";
        } elseif (!preg_match('/^\d{11,13}$/', $lrn)) {
            $message = "LRN must be 11-13 digits only.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } else {
            try {
                if ($action === 'add') {
                    // Check if LRN already exists
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE lrn = ?");
                    $stmt->execute([$lrn]);
                    if ($stmt->fetch()) {
                        $message = "A student with this LRN already exists.";
                        $messageType = "error";
                    } else {
                        // Insert new student
                        $stmt = $pdo->prepare("
                            INSERT INTO students (lrn, first_name, last_name, middle_name, gender, email, class, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$lrn, $firstName, $lastName, $middleName, $gender, $email, $class]);
                        
                        $message = "Student added successfully!";
                        $messageType = "success";
                        
                        // Clear form data
                        $_POST = [];
                    }
                } elseif ($action === 'edit' && $editStudent) {
                    // Check if LRN exists for other students
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE lrn = ? AND id != ?");
                    $stmt->execute([$lrn, $editStudent['id']]);
                    if ($stmt->fetch()) {
                        $message = "Another student with this LRN already exists.";
                        $messageType = "error";
                    } else {
                        // Update student
                        $stmt = $pdo->prepare("
                            UPDATE students 
                            SET lrn = ?, first_name = ?, last_name = ?, middle_name = ?, gender = ?, email = ?, class = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$lrn, $firstName, $lastName, $middleName, $gender, $email, $class, $editStudent['id']]);
                        
                        $message = "Student updated successfully!";
                        $messageType = "success";
                        
                        // Refresh edit student data
                        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                        $stmt->execute([$editStudent['id']]);
                        $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) {
                $message = "Database error occurred. Please try again.";
                $messageType = "error";
                error_log("Student form error: " . $e->getMessage());
            }
        }
    }
}

// Get available classes for suggestions
$availableClasses = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class");
    $availableClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Ignore error, just won't have suggestions
}

include 'includes/header.php';
?>

<!-- Page Content -->
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo sanitizeOutput($message); ?>
    </div>
<?php endif; ?>

<!-- Navigation Breadcrumb -->
<div class="breadcrumb">
    <a href="view_students.php">
        <i class="fas fa-users"></i> Students List
    </a>
    <span class="separator">/</span>
    <span class="current">
        <i class="fas fa-<?php echo $editMode ? 'edit' : 'plus'; ?>"></i>
        <?php echo $editMode ? 'Edit Student' : 'Add Student'; ?>
    </span>
</div>

<!-- Student Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-<?php echo $editMode ? 'edit' : 'plus'; ?>"></i>
            <?php echo $editMode ? 'Edit Student' : 'Add New Student'; ?>
        </h3>
        <div class="card-actions">
            <a href="view_students.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($editMode && !$editStudent): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Student not found. <a href="view_students.php">Return to student list</a>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="student-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'edit' : 'add'; ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?php echo $editStudent['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="lrn">LRN (Learner Reference Number) *</label>
                        <input type="text" 
                               id="lrn" 
                               name="lrn" 
                               class="form-control" 
                               required 
                               pattern="[0-9]{11,13}" 
                               maxlength="13" 
                               minlength="11"
                               placeholder="Enter 11-13 digit LRN"
                               value="<?php echo sanitizeOutput($editStudent['lrn'] ?? ''); ?>">
                        <small class="form-help">Must be 11-13 digits (e.g., 123456789012)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="class">Class *</label>
                        <input type="text" 
                               id="class" 
                               name="class" 
                               class="form-control" 
                               required 
                               maxlength="50"
                               placeholder="Enter class (e.g., Grade 11-A, STEM-1)"
                               value="<?php echo sanitizeOutput($editStudent['class'] ?? ''); ?>"
                               list="class-suggestions">
                        <datalist id="class-suggestions">
                            <?php foreach ($availableClasses as $className): ?>
                                <option value="<?php echo sanitizeOutput($className); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-help">Student's class or section</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-control" 
                               required 
                               maxlength="50"
                               placeholder="Enter first name"
                               value="<?php echo sanitizeOutput($editStudent['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" 
                               id="middle_name" 
                               name="middle_name" 
                               class="form-control" 
                               maxlength="50"
                               placeholder="Enter middle name (optional)"
                               value="<?php echo sanitizeOutput($editStudent['middle_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="form-control" 
                               required 
                               maxlength="50"
                               placeholder="Enter last name"
                               value="<?php echo sanitizeOutput($editStudent['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" 
                                name="gender" 
                                class="form-control" 
                                required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (($editStudent['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($editStudent['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <small class="form-help">Required for SF2 reporting</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               required 
                               maxlength="100"
                               placeholder="Enter email address"
                               value="<?php echo sanitizeOutput($editStudent['email'] ?? ''); ?>">
                        <small class="form-help">Used for communication and notifications</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-<?php echo $editMode ? 'save' : 'plus'; ?>"></i>
                        <?php echo $editMode ? 'Update Student' : 'Add Student'; ?>
                    </button>
                    <a href="view_students.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php if ($editMode): ?>
                        <a href="manage_students.php" class="btn btn-info">
                            <i class="fas fa-plus"></i> Add New Student
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($editMode && $editStudent): ?>
    <!-- Additional Information -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle"></i> Student Information
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Registration Date:</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($editStudent['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Last Updated:</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($editStudent['updated_at'] ?? $editStudent['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Student ID:</label>
                    <span>#<?php echo $editStudent['id']; ?></span>
                </div>
            </div>
            
            <div class="quick-actions">
                <h4>Quick Actions</h4>
                <div class="action-buttons">
                    <button onclick="generateQR('<?php echo sanitizeOutput($editStudent['lrn']); ?>', '<?php echo sanitizeOutput($editStudent['first_name'] . ' ' . $editStudent['last_name']); ?>')" 
                            class="btn btn-info btn-sm">
                        <i class="fas fa-qrcode"></i> Generate QR Code
                    </button>
                    <a href="attendance_reports.php?lrn=<?php echo urlencode($editStudent['lrn']); ?>" 
                       class="btn btn-success btn-sm" target="_blank">
                        <i class="fas fa-chart-line"></i> View Attendance
                    </a>
                    <button onclick="confirmDelete()" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Delete Student
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qr-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-qrcode"></i> Student QR Code
                </h3>
                <button class="close" onclick="closeQRModal()">&times;</button>
            </div>
            <div class="modal-body centered-modal-body">
                <h4 id="qr-student-name"></h4>
                <div id="qr-code-container" class="qr-container"></div>
                <p>Student can scan this QR code to mark attendance.</p>
                <div class="modal-actions">
                    <button onclick="printQR()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print QR Code
                    </button>
                    <button onclick="closeQRModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <div class="modal-body delete-modal-content">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Delete Student</h3>
                <p>
                    Are you sure you want to delete <strong><?php echo sanitizeOutput($editStudent['first_name'] . ' ' . $editStudent['last_name']); ?></strong>?
                    <br><br>
                    <span style="color: #dc3545; font-weight: 500;">
                        This action cannot be undone and will also delete all attendance records for this student.
                    </span>
                </p>
                <div class="delete-modal-buttons">
                    <form method="POST" action="../api/delete_student.php" style="display: inline;">
                        <input type="hidden" name="student_id" value="<?php echo $editStudent['id']; ?>">
                        <button type="submit" class="btn-confirm-delete">
                            <i class="fas fa-trash"></i> Yes, Delete Student
                        </button>
                    </form>
                    <button onclick="closeDeleteModal()" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Include QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<!-- Include View Students JavaScript -->
<script src="../js/view_students.js"></script>

<script>
// Additional JavaScript for manage students page
function confirmDelete() {
    document.getElementById('delete-modal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('delete-modal').style.display = 'none';
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.student-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const lrn = document.getElementById('lrn').value;
            if (!/^\d{11,13}$/.test(lrn)) {
                e.preventDefault();
                alert('LRN must be 11-13 digits only.');
                document.getElementById('lrn').focus();
                return false;
            }
        });
    }
    
    // Auto-format LRN input
    const lrnInput = document.getElementById('lrn');
    if (lrnInput) {
        lrnInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, ''); // Remove non-digits
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
