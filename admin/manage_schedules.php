<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manage Schedules';
$pageIcon = 'calendar-alt';
$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Add new schedule
                $class = trim($_POST['class'] ?? '');
                $day_of_week = trim($_POST['day_of_week'] ?? '');
                $period_number = intval($_POST['period_number'] ?? 0);
                $start_time = trim($_POST['start_time'] ?? '');
                $end_time = trim($_POST['end_time'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $is_break = isset($_POST['is_break']) ? 1 : 0;
                
                if (empty($class) || empty($day_of_week) || empty($start_time) || empty($end_time) || empty($subject)) {
                    $message = 'All fields except "Is Break" are required.';
                    $messageType = 'error';
                } elseif (strtotime($start_time) >= strtotime($end_time)) {
                    $message = 'End time must be after start time.';
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO schedule (class, day_of_week, period_number, start_time, end_time, subject, is_break) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$class, $day_of_week, $period_number, $start_time, $end_time, $subject, $is_break]);
                        
                        $message = "Schedule entry added successfully.";
                        $messageType = 'success';
                        logAdminActivity('ADD_SCHEDULE', "Added schedule: $class - $day_of_week - $subject");
                    } catch (Exception $e) {
                        $message = 'Error adding schedule: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'edit':
                // Edit existing schedule
                $id = intval($_POST['id'] ?? 0);
                $class = trim($_POST['class'] ?? '');
                $day_of_week = trim($_POST['day_of_week'] ?? '');
                $period_number = intval($_POST['period_number'] ?? 0);
                $start_time = trim($_POST['start_time'] ?? '');
                $end_time = trim($_POST['end_time'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $is_break = isset($_POST['is_break']) ? 1 : 0;
                
                if ($id <= 0 || empty($class) || empty($day_of_week) || empty($start_time) || empty($end_time) || empty($subject)) {
                    $message = 'All fields except "Is Break" are required.';
                    $messageType = 'error';
                } elseif (strtotime($start_time) >= strtotime($end_time)) {
                    $message = 'End time must be after start time.';
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE schedule 
                            SET class = ?, day_of_week = ?, period_number = ?, start_time = ?, end_time = ?, subject = ?, is_break = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$class, $day_of_week, $period_number, $start_time, $end_time, $subject, $is_break, $id]);
                        
                        $message = "Schedule entry updated successfully.";
                        $messageType = 'success';
                        logAdminActivity('EDIT_SCHEDULE', "Updated schedule ID: $id");
                    } catch (Exception $e) {
                        $message = 'Error updating schedule: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                // Delete schedule
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $message = 'Invalid schedule ID.';
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $message = "Schedule entry deleted successfully.";
                        $messageType = 'success';
                        logAdminActivity('DELETE_SCHEDULE', "Deleted schedule ID: $id");
                    } catch (Exception $e) {
                        $message = 'Error deleting schedule: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get schedules with filters
$classFilter = trim($_GET['class_filter'] ?? '');
$dayFilter = trim($_GET['day_filter'] ?? '');

$whereClause = '';
$params = [];

if ($classFilter) {
    $whereClause .= " AND class = ?";
    $params[] = $classFilter;
}

if ($dayFilter) {
    $whereClause .= " AND day_of_week = ?";
    $params[] = $dayFilter;
}

try {
    // Get schedules
    $query = "
        SELECT * 
        FROM schedule 
        WHERE 1=1 $whereClause
        ORDER BY class, 
                 FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                 start_time
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available classes and days
    $stmt = $pdo->query("SELECT DISTINCT class FROM schedule ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
} catch (Exception $e) {
    error_log("Schedules query error: " . $e->getMessage());
    $schedules = [];
    $classes = [];
    $days = [];
}

// Group schedules by class and day for better display
$groupedSchedules = [];
foreach ($schedules as $schedule) {
    $groupedSchedules[$schedule['class']][$schedule['day_of_week']][] = $schedule;
}

// Include the admin header
include 'includes/header.php';
?>

<!-- Main Content Area -->
<div class="main-content-header">
    <h1><i class="fas fa-calendar-alt"></i> <?php echo $pageTitle; ?></h1>
    <button onclick="openAddModal()" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Schedule Entry
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo sanitizeOutput($message); ?>
    </div>
<?php endif; ?>

<!-- Schedules Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Class Schedules</h3>
        <span class="student-count">
            Total: <?php echo count($schedules); ?> schedule entries
        </span>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group no-margin">
                        <select name="class_filter">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $className): ?>
                                <option value="<?php echo sanitizeOutput($className); ?>" 
                                        <?php echo $className === $classFilter ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($className); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group no-margin">
                        <select name="day_filter">
                            <option value="">All Days</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?php echo $day; ?>" 
                                        <?php echo $day === $dayFilter ? 'selected' : ''; ?>>
                                    <?php echo $day; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Schedule Display -->
        <?php if (!empty($groupedSchedules)): ?>
            <?php foreach ($groupedSchedules as $className => $classDays): ?>
                <?php if (!$classFilter || $classFilter === $className): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <h3><i class="fas fa-graduation-cap"></i> <?php echo sanitizeOutput($className); ?></h3>
                        </div>
                        
                        <div class="days-container">
                            <?php foreach ($classDays as $dayName => $daySchedules): ?>
                                <?php if (!$dayFilter || $dayFilter === $dayName): ?>
                                    <div class="day-section">
                                        <div class="day-header">
                                            <i class="fas fa-calendar-day"></i> <?php echo $dayName; ?>
                                        </div>
                                        
                                        <?php foreach ($daySchedules as $schedule): ?>
                                            <div class="schedule-item">
                                                <div class="schedule-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                </div>
                                                <div class="schedule-subject">
                                                    <?php if ($schedule['is_break']): ?>
                                                        <span class="break-indicator">
                                                            <i class="fas fa-coffee"></i> <?php echo sanitizeOutput($schedule['subject']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo sanitizeOutput($schedule['subject']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="schedule-meta">
                                                    <span>Period <?php echo $schedule['period_number']; ?></span>
                                                    <div class="schedule-actions">
                                                        <button onclick="editSchedule(<?php echo $schedule['id']; ?>, '<?php echo sanitizeOutput($schedule['class']); ?>', '<?php echo $schedule['day_of_week']; ?>', <?php echo $schedule['period_number']; ?>, '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>', '<?php echo sanitizeOutput($schedule['subject']); ?>', <?php echo $schedule['is_break']; ?>)" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-schedules">
                <i class="fas fa-calendar-times"></i>
                <h3>No schedules found</h3>
                <p>Click "Add Schedule Entry" to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Schedule Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Schedule Entry</h3>
            <span class="close" onclick="closeScheduleModal()">&times;</span>
        </div>
        <form id="scheduleForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="id" value="" id="scheduleId">
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="class">Class</label>
                        <input type="text" id="class" name="class" required>
                    </div>
                    <div class="form-group">
                        <label for="day_of_week">Day of Week</label>
                        <select id="day_of_week" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="period_number">Period Number</label>
                        <input type="number" id="period_number" name="period_number" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                </div>
                
                <div class="time-inputs">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                
                <div class="checkbox-container">
                    <input type="checkbox" id="is_break" name="is_break">
                    <label for="is_break">This is a break period</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Deletion</h3>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="delete-modal-content">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Are you sure?</h3>
                <p>This will permanently delete this schedule entry. This action cannot be undone.</p>
                
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteScheduleId">
                    
                    <div class="delete-modal-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Schedule Management Functions
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Schedule Entry';
        document.getElementById('formAction').value = 'add';
        document.getElementById('scheduleId').value = '';
        document.getElementById('saveBtn').innerHTML = '<i class="fas fa-save"></i> Save Schedule';
        
        // Clear form
        document.getElementById('scheduleForm').reset();
        document.getElementById('scheduleModal').style.display = 'block';
    }

    function editSchedule(id, className, dayOfWeek, periodNumber, startTime, endTime, subject, isBreak) {
        document.getElementById('modalTitle').textContent = 'Edit Schedule Entry';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('scheduleId').value = id;
        document.getElementById('saveBtn').innerHTML = '<i class="fas fa-save"></i> Update Schedule';
        
        // Populate form
        document.getElementById('class').value = className;
        document.getElementById('day_of_week').value = dayOfWeek;
        document.getElementById('period_number').value = periodNumber;
        document.getElementById('start_time').value = startTime;
        document.getElementById('end_time').value = endTime;
        document.getElementById('subject').value = subject;
        document.getElementById('is_break').checked = isBreak == 1;
        
        document.getElementById('scheduleModal').style.display = 'block';
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').style.display = 'none';
    }

    function deleteSchedule(id) {
        document.getElementById('deleteScheduleId').value = id;
        document.getElementById('deleteModal').style.display = 'block';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const scheduleModal = document.getElementById('scheduleModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target == scheduleModal) {
            closeScheduleModal();
        } else if (event.target == deleteModal) {
            closeDeleteModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
