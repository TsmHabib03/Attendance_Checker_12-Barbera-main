<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'View Students';
$pageIcon = 'users';

// Get students with pagination and search
$search = trim($_GET['search'] ?? '');
$class_filter = trim($_GET['class'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$whereClause = [];
$params = [];

if ($search) {
    $whereClause[] = "(first_name LIKE ? OR last_name LIKE ? OR lrn LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($class_filter) {
    $whereClause[] = "class = ?";
    $params[] = $class_filter;
}

$whereSQL = $whereClause ? 'WHERE ' . implode(' AND ', $whereClause) : '';

try {
    // Get total count for pagination
    $countSQL = "SELECT COUNT(*) as total FROM students $whereSQL";
    $stmt = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $totalStudents = $stmt->fetch()['total'];
    
    // Get students with optimized query
    $studentsSQL = "
        SELECT id, lrn, first_name, last_name, middle_name, gender, email, class, created_at,
               CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) as full_name
        FROM students 
        $whereSQL 
        ORDER BY class ASC, last_name ASC, first_name ASC 
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($studentsSQL);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique classes for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalPages = ceil($totalStudents / $perPage);
} catch (Exception $e) {
    error_log("View students query error: " . $e->getMessage());
    $students = [];
    $classes = [];
    $totalStudents = 0;
    $totalPages = 0;
}

// Include the admin header
include 'includes/header.php';
?>

<!-- Search and Filter Controls -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-search"></i> Search & Filter
        </h3>
    </div>
    <div class="card-body">
        <form method="GET" action="view_students.php" class="search-form">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label for="search">Search Students:</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search by name, LRN, or email..." 
                           value="<?php echo sanitizeOutput($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="class">Filter by Class:</label>
                    <select id="class" name="class" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo sanitizeOutput($class); ?>" 
                                    <?php echo ($class_filter === $class) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($class); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($search || $class_filter): ?>
                            <a href="view_students.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Students List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users"></i> Students List
        </h3>
        <div class="card-actions">
            <span class="badge">Total: <?php echo number_format($totalStudents); ?> students</span>
            <a href="manage_students.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Student
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($students)): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Student</th>
                            <th>Gender</th>
                            <th>Email</th>
                            <th>Class</th>
                            <th>QR Code</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr data-student-id="<?php echo $student['id']; ?>">
                                <td>
                                    <span class="lrn-badge"><?php echo sanitizeOutput($student['lrn']); ?></span>
                                </td>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-details">
                                            <strong><?php echo sanitizeOutput($student['full_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="gender-badge <?php echo strtolower($student['gender'] ?? 'unknown'); ?>">
                                        <?php echo sanitizeOutput($student['gender'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="email-text"><?php echo sanitizeOutput($student['email']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo sanitizeOutput($student['class']); ?></span>
                                </td>
                                <td>
                                    <button class="btn-qr" 
                                            onclick="generateQR('<?php echo sanitizeOutput($student['lrn']); ?>', '<?php echo sanitizeOutput($student['full_name']); ?>')"
                                            title="Generate QR Code">
                                        <i class="fas fa-qrcode"></i> QR
                                    </button>
                                </td>
                                <td>
                                    <span class="date-text"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="manage_students.php?id=<?php echo $student['id']; ?>" 
                                           class="btn-edit" 
                                           title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="viewAttendance('<?php echo sanitizeOutput($student['lrn']); ?>', '<?php echo sanitizeOutput($student['full_name']); ?>')" 
                                                class="btn-view" 
                                                title="View Attendance">
                                            <i class="fas fa-chart-line"></i>
                                        </button>
                                        <button onclick="confirmDeleteStudent(<?php echo $student['id']; ?>, '<?php echo sanitizeOutput($student['full_name']); ?>', '<?php echo sanitizeOutput($student['lrn']); ?>')" 
                                                class="btn-delete" 
                                                title="Delete Student">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        <?php if ($startPage > 2): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-users"></i>
                </div>
                <?php if ($search || $class_filter): ?>
                    <h3>No students found</h3>
                    <p>No students match your search criteria.</p>
                    <a href="view_students.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> Show All Students
                    </a>
                <?php else: ?>
                    <h3>No students registered</h3>
                    <p>There are no students in the system yet.</p>
                    <a href="manage_students.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Student
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qr-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-qrcode"></i> Student QR Code
            </h3>
            <button class="close" onclick="closeQRModal()" title="Close">&times;</button>
        </div>
        <div class="modal-body centered-modal-body">
            <h4 id="qr-student-name"></h4>
            <div id="qr-code-container" class="qr-container"></div>
            <p>Students can scan this QR code to mark their attendance.</p>
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
<div id="delete-confirmation-modal" class="modal">
    <div class="modal-content">
        <div class="modal-body delete-modal-content">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Delete Student</h3>
            <p>
                Are you sure you want to delete <strong id="delete-student-name"></strong> (LRN: <span id="delete-student-lrn"></span>)?
                <br><br>
                <span style="color: #dc3545; font-weight: 500;">
                    This action cannot be undone and will also delete all attendance records for this student.
                </span>
            </p>
            <div class="delete-modal-buttons">
                <button id="confirm-delete-btn" onclick="deleteStudent()" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> Yes, Delete Student
                </button>
                <button onclick="closeDeleteModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library (davidshimjs) -->
<script src="../js/qrcode.min.js"></script>
<!-- Include View Students JavaScript -->
<script src="../js/view_students.js"></script>

<?php include 'includes/footer.php'; ?>
