<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Manual Attendance';
$pageIcon = 'clipboard-check';
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
            case 'mark_attendance':
                $lrn = trim($_POST['lrn'] ?? '');
                $date = trim($_POST['date'] ?? '');
                $time = trim($_POST['time'] ?? '');
                
                if (empty($lrn) || empty($date) || empty($time)) {
                    $message = 'All fields are required.';
                    $messageType = 'error';
                } elseif (!preg_match('/^\d{11,13}$/', $lrn)) {
                    $message = 'Invalid LRN format. Must be 11-13 digits.';
                    $messageType = 'error';
                } else {
                    try {
                        // Use the MarkAttendance stored procedure
                        $stmt = $pdo->prepare("CALL MarkAttendance(?, ?, ?)");
                        $stmt->execute([$lrn, $date, $time]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result) {
                            $message = "Attendance marked successfully for {$result['student_name']} - Status: {$result['attendance_status']} for {$result['subject']}";
                            $messageType = 'success';
                            logAdminActivity('MANUAL_ATTENDANCE', "Marked attendance for LRN: $lrn on $date at $time");
                        } else {
                            $message = 'No class scheduled at this time or student not found.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Student not found') !== false) {
                            $message = 'Student with this LRN was not found.';
                        } elseif (strpos($e->getMessage(), 'No class scheduled') !== false) {
                            $message = 'No class is scheduled at the specified time.';
                        } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $message = 'Attendance has already been marked for this student and period.';
                        } else {
                            $message = 'Error marking attendance: ' . $e->getMessage();
                        }
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'bulk_mark':
                $lrns = trim($_POST['bulk_lrns'] ?? '');
                $date = trim($_POST['bulk_date'] ?? '');
                $time = trim($_POST['bulk_time'] ?? '');
                
                if (empty($lrns) || empty($date) || empty($time)) {
                    $message = 'All fields are required for bulk attendance.';
                    $messageType = 'error';
                } else {
                    $lrnList = array_filter(array_map('trim', explode("\n", $lrns)));
                    $successCount = 0;
                    $errorCount = 0;
                    $errors = [];
                    
                    foreach ($lrnList as $lrn) {
                        if (!preg_match('/^\d{11,13}$/', $lrn)) {
                            $errors[] = "Invalid LRN format: $lrn";
                            $errorCount++;
                            continue;
                        }
                        
                        try {
                            $stmt = $pdo->prepare("CALL MarkAttendance(?, ?, ?)");
                            $stmt->execute([$lrn, $date, $time]);
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($result) {
                                $successCount++;
                            } else {
                                $errors[] = "No class scheduled or student not found: $lrn";
                                $errorCount++;
                            }
                        } catch (Exception $e) {
                            $errors[] = "Error for LRN $lrn: " . $e->getMessage();
                            $errorCount++;
                        }
                    }
                    
                    if ($successCount > 0) {
                        $message = "Bulk attendance completed: $successCount successful, $errorCount errors.";
                        $messageType = $errorCount > 0 ? 'warning' : 'success';
                        if (!empty($errors)) {
                            $message .= " Errors: " . implode("; ", array_slice($errors, 0, 3));
                            if (count($errors) > 3) {
                                $message .= " and " . (count($errors) - 3) . " more...";
                            }
                        }
                        logAdminActivity('BULK_ATTENDANCE', "Bulk marked attendance: $successCount successful, $errorCount errors");
                    } else {
                        $message = "No attendance records were created. Errors: " . implode("; ", array_slice($errors, 0, 3));
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get today's date and current time for defaults
$today = date('Y-m-d');
$currentTime = date('H:i');

// Get students for quick selection
try {
    $stmt = $pdo->prepare("
        SELECT lrn, first_name, last_name, class 
        FROM students 
        ORDER BY class, last_name, first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current day's schedule for time suggestions
    $currentDay = date('l'); // Full day name (Monday, Tuesday, etc.)
    $stmt = $pdo->prepare("
        SELECT DISTINCT start_time, end_time, subject, class, period_number
        FROM schedule 
        WHERE day_of_week = ? 
        ORDER BY start_time
    ");
    $stmt->execute([$currentDay]);
    $todaysSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent attendance for reference
    $stmt = $pdo->prepare("
        SELECT a.lrn, s.first_name, s.last_name, s.class, a.subject, a.status, a.time, a.date,
               a.created_at
        FROM attendance a 
        JOIN students s ON a.lrn = s.lrn 
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        ORDER BY a.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Manual attendance query error: " . $e->getMessage());
    $students = [];
    $todaysSchedule = [];
    $recentAttendance = [];
}

// Include the admin header
include 'includes/header.php';
?>

<!-- Page Content -->
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <?php echo sanitizeOutput($message); ?>
    </div>
<?php endif; ?>
<div class="help-text">
    <i class="fas fa-info-circle"></i>
    <strong>Manual Attendance:</strong> Use this feature to mark attendance for students who may have forgotten their QR codes, 
    had technical issues, or need retroactive attendance entries. The system will automatically determine the correct status 
    (Present/Late) based on the class schedule.
</div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab('scanner')">
                        <i class="fas fa-qrcode"></i> QR Scanner
                    </button>
                    <button class="tab" onclick="showTab('single')">
                        <i class="fas fa-user"></i> Single Entry
                    </button>
                    <button class="tab" onclick="showTab('bulk')">
                        <i class="fas fa-users"></i> Bulk Entry
                    </button>
                </div>
                
                <!-- QR Scanner Tab -->
                <div id="scanner-tab" class="tab-content active">
                    <div class="content-grid">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">QR Code Scanner</h3>
                                <div class="scanner-controls">
                                    <button id="start-scan-btn" class="btn btn-primary">
                                        <i class="fas fa-camera"></i> Start Scanner
                                    </button>
                                    <button id="stop-scan-btn" class="btn btn-danger" style="display: none;">
                                        <i class="fas fa-stop"></i> Stop Scanner
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="scanner-container">
                                    <div id="qr-reader-container">
                                        <div id="qr-reader"></div>
                                        <div class="scanner-overlay">
                                            <div class="scan-line"></div>
                                            <div class="scan-corners"></div>
                                        </div>
                                    </div>
                                    <div id="scanner-status" class="scanner-status">
                                        <p><i class="fas fa-qrcode"></i> Click "Start Scanner" to begin scanning QR codes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Scan Results & Today's Attendance</h3>
                            </div>
                            <div class="card-body">
                                <div id="scan-result-container" class="scan-result-container" style="display: none;">
                                    <div id="scan-result"></div>
                                </div>
                                
                                <div class="today-attendance-section">
                                    <h4><i class="fas fa-list-check"></i> Today's Scanned Attendance</h4>
                                    <div id="today-attendance-list" class="attendance-list">
                                        <div class="loading-state">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <p>Loading today's attendance...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Single Entry Tab -->
                <div id="single-tab" class="tab-content">
                    <div class="content-grid">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Mark Individual Attendance</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="mark_attendance">
                                    
                                    <div class="form-group">
                                        <label for="lrn">Student LRN *</label>
                                        <input type="text" id="lrn" name="lrn" required 
                                               pattern="[0-9]{11,13}" maxlength="13" minlength="11"
                                               placeholder="Enter 11-13 digit LRN"
                                               title="LRN must be 11-13 digits">
                                    </div>
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="date">Date *</label>
                                            <input type="date" id="date" name="date" required value="<?php echo $today; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="time">Time *</label>
                                            <input type="time" id="time" name="time" required value="<?php echo $currentTime; ?>">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Mark Attendance
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Quick Student Selection</h3>
                            </div>
                            <div class="card-body">
                                <div class="students-list">
                                    <?php foreach ($students as $student): ?>
                                        <div class="student-item" onclick="selectStudent('<?php echo $student['lrn']; ?>')">
                                            <div class="student-info">
                                                <h4><?php echo sanitizeOutput($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                                <p><?php echo sanitizeOutput($student['class']); ?></p>
                                            </div>
                                            <div class="lrn-badge"><?php echo sanitizeOutput($student['lrn']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Entry Tab -->
                <div id="bulk-tab" class="tab-content">
                    <div class="content-grid">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Bulk Attendance Entry</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="bulk_mark">
                                    
                                    <div class="form-group">
                                        <label for="bulk_lrns">Student LRNs (one per line) *</label>
                                        <textarea id="bulk_lrns" name="bulk_lrns" required 
                                                  placeholder="Enter one LRN per line:&#10;123456789012&#10;123456789013&#10;123456789014"></textarea>
                                    </div>
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="bulk_date">Date *</label>
                                            <input type="date" id="bulk_date" name="bulk_date" required value="<?php echo $today; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="bulk_time">Time *</label>
                                            <input type="time" id="bulk_time" name="bulk_time" required value="<?php echo $currentTime; ?>">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-users"></i> Mark Bulk Attendance
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Copy LRNs by Class</h3>
                            </div>
                            <div class="card-body">
                                <?php
                                $studentsByClass = [];
                                foreach ($students as $student) {
                                    $studentsByClass[$student['class']][] = $student;
                                }
                                ?>
                                
                                <?php foreach ($studentsByClass as $className => $classStudents): ?>
                                    <div class="export-section">
                                        <h4 class="export-title">
                                            <?php echo sanitizeOutput($className); ?> (<?php echo count($classStudents); ?> students)
                                        </h4>
                                        <button type="button" class="btn btn-info" onclick="copyClassLRNs('<?php echo $className; ?>')">
                                            <i class="fas fa-copy"></i> Copy All LRNs
                                        </button>
                                        <textarea id="class-<?php echo $className; ?>" class="export-textarea">
<?php foreach ($classStudents as $student): ?>
<?php echo $student['lrn'] . "\n"; ?>
<?php endforeach; ?>
                                        </textarea>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="content-grid spaced">
                    <?php if (!empty($todaysSchedule)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Today's Schedule (<?php echo $currentDay; ?>)</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($todaysSchedule as $schedule): ?>
                                    <div class="schedule-item" onclick="setTime('<?php echo $schedule['start_time']; ?>')">
                                        <div class="student-info">
                                            <h4>
                                                <?php echo sanitizeOutput($schedule['subject']); ?>
                                            </h4>
                                            <p>
                                                <?php echo sanitizeOutput($schedule['class']); ?> • Period <?php echo $schedule['period_number']; ?>
                                            </p>
                                        </div>
                                        <div class="time-badge">
                                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <p class="attendance-footer">
                                    <i class="fas fa-mouse-pointer"></i> Click a schedule item to set the time
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Manual Entries</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentAttendance)): ?>
                                <?php foreach (array_slice($recentAttendance, 0, 10) as $record): ?>
                                    <div class="recent-item">
                                        <div class="schedule-info">
                                            <h4>
                                                <?php echo sanitizeOutput($record['first_name'] . ' ' . $record['last_name']); ?>
                                            </h4>
                                            <p>
                                                <?php echo sanitizeOutput($record['subject']); ?> • 
                                                <?php echo date('M d, Y g:i A', strtotime($record['date'] . ' ' . $record['time'])); ?>
                                            </p>
                                        </div>
                                        <span class="status-badge <?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-attendance-text">
                                    No recent attendance records found.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include ZXing library for QR code scanning -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <script>
        // QR Scanner variables
        let codeReader = null;
        let selectedDeviceId = null;
        let isScanning = false;
        let lastScanTime = 0;
        let scanCooldown = 1000; // 1 second cooldown between scans (faster like public scanner)

        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        // QR Scanner initialization
        async function initializeQRScanner() {
            try {
                // Use the same scanner as the public page for faster performance
                codeReader = new ZXing.BrowserQRCodeReader();
                const videoInputDevices = await codeReader.listVideoInputDevices();
                
                if (videoInputDevices && videoInputDevices.length > 0) {
                    selectedDeviceId = videoInputDevices[0].deviceId;
                    console.log(`Found ${videoInputDevices.length} camera(s)`);
                    updateScannerStatus('Camera initialized successfully. Ready to scan!', 'success');
                } else {
                    throw new Error('No camera devices found');
                }
            } catch (error) {
                console.error('Error initializing scanner:', error);
                if (error.name === 'NotAllowedError') {
                    updateScannerStatus('Error: Camera access denied. Please allow camera permissions and refresh the page.', 'error');
                } else if (error.name === 'NotFoundError') {
                    updateScannerStatus('Error: No camera found on this device.', 'error');
                } else {
                    updateScannerStatus('Error: Could not access camera. ' + error.message, 'error');
                }
            }
        }

        // Start QR scanning
        async function startQRScanning() {
            if (!codeReader) {
                await initializeQRScanner();
                if (!codeReader) return;
            }

            try {
                isScanning = true;
                document.getElementById('start-scan-btn').style.display = 'none';
                document.getElementById('stop-scan-btn').style.display = 'inline-block';
                document.querySelector('.scanner-overlay').style.display = 'block';
                
                updateScannerStatus('Starting camera...', 'scanning');

                // Create or get video element exactly like public scanner
                let video = document.querySelector('#qr-reader video');
                if (!video) {
                    video = document.createElement('video');
                    video.id = 'scanner-video';
                    video.style.width = '100%';
                    video.style.maxWidth = '400px';
                    video.style.borderRadius = '12px';
                    video.style.border = '4px solid #e9ecef';
                    document.getElementById('qr-reader').innerHTML = '';
                    document.getElementById('qr-reader').appendChild(video);
                }

                video.style.display = 'block';

                // Use the exact same fast scanning method as public scanner
                await codeReader.decodeFromVideoDevice(selectedDeviceId, video, (result, err) => {
                    if (result && isScanning) {
                        handleQRCodeScan(result.text);
                    }
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error('Scanning error:', err);
                    }
                });

                updateScannerStatus('Camera active. Point at a QR code to scan attendance.', 'scanning');

            } catch (error) {
                console.error('Error starting scanner:', error);
                updateScannerStatus('Error starting camera: ' + error.message, 'error');
                stopQRScanning();
            }
        }

        // Stop QR scanning
        function stopQRScanning() {
            try {
                if (codeReader) {
                    codeReader.reset();
                }
            } catch (error) {
                console.error('Error stopping scanner:', error);
            }
            
            isScanning = false;
            document.getElementById('start-scan-btn').style.display = 'inline-block';
            document.getElementById('stop-scan-btn').style.display = 'none';
            document.querySelector('.scanner-overlay').style.display = 'none';
            
            updateScannerStatus('Scanner stopped. Click "Start Scanner" to begin scanning.', '');
        }

        // Handle QR code scan
        async function handleQRCodeScan(qrData) {
            const currentTime = Date.now();
            
            // Prevent duplicate scans too quickly
            if (currentTime - lastScanTime < scanCooldown) {
                console.log('Scan ignored - too soon after last scan');
                return;
            }
            
            lastScanTime = currentTime;
            console.log('QR Code scanned:', qrData);
            
            // Don't stop scanning, just process in background for speed
            updateScannerStatus('Processing QR code...', 'scanning');

            try {
                // Extract LRN from QR data (assuming format: LRN|timestamp or just LRN)
                const lrn = qrData.split('|')[0];
                
                if (!/^\d{11,13}$/.test(lrn)) {
                    throw new Error('Invalid QR Code format. Expected valid LRN.');
                }

                // Mark attendance using the same API as manual entry
                const response = await fetch('../api/mark_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `lrn=${encodeURIComponent(lrn)}&admin_scan=true`
                });

                const result = await response.json();

                if (result.success) {
                    showScanResult(true, result);
                    updateScannerStatus('Scan successful! Scanner still active.', 'success');
                    loadTodayAttendance(); // Refresh the attendance list
                    
                    // Keep scanning active - don't stop
                } else {
                    showScanResult(false, result);
                    updateScannerStatus('Scan failed: ' + result.message + ' - Scanner still active.', 'error');
                }

            } catch (error) {
                console.error('Error processing QR code:', error);
                showScanResult(false, { message: error.message });
                updateScannerStatus('Error processing QR code: ' + error.message + ' - Scanner still active.', 'error');
            }
        }

        // Update scanner status
        function updateScannerStatus(message, type = '') {
            const statusElement = document.getElementById('scanner-status');
            statusElement.innerHTML = `<p><i class="fas fa-${getStatusIcon(type)}"></i> ${message}</p>`;
            statusElement.className = `scanner-status ${type}`;
        }

        // Get status icon
        function getStatusIcon(type) {
            switch (type) {
                case 'scanning': return 'spinner fa-spin';
                case 'success': return 'check-circle';
                case 'error': return 'exclamation-triangle';
                default: return 'qrcode';
            }
        }

        // Show scan result
        function showScanResult(success, result) {
            const container = document.getElementById('scan-result-container');
            const resultDiv = document.getElementById('scan-result');
            
            container.style.display = 'block';
            
            if (success) {
                resultDiv.className = 'scan-result scan-result-success';
                resultDiv.innerHTML = `
                    <h4><i class="fas fa-check-circle"></i> Attendance Marked Successfully!</h4>
                    <div class="student-details">
                        <p><strong>Student:</strong> ${result.student_name || 'N/A'}</p>
                        <p><strong>LRN:</strong> ${result.lrn}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${(result.status || 'present').toLowerCase()}">${result.status || 'Present'}</span></p>
                        <p><strong>Time:</strong> ${result.scan_time || new Date().toLocaleTimeString()}</p>
                        ${result.subject ? `<p><strong>Subject:</strong> ${result.subject}</p>` : ''}
                    </div>
                `;
            } else {
                resultDiv.className = 'scan-result scan-result-error';
                resultDiv.innerHTML = `
                    <h4><i class="fas fa-exclamation-triangle"></i> Scan Failed</h4>
                    <p>${result.message}</p>
                `;
            }

            // Hide result after 5 seconds
            setTimeout(() => {
                container.style.display = 'none';
            }, 5000);
        }

        // Load today's attendance
        async function loadTodayAttendance() {
            try {
                const response = await fetch('../api/get_today_attendance.php');
                const result = await response.json();
                
                const listContainer = document.getElementById('today-attendance-list');
                
                if (result.success && result.attendance && result.attendance.length > 0) {
                    let html = '';
                    result.attendance.forEach(record => {
                        html += `
                            <div class="attendance-list-item">
                                <div class="attendance-student-info">
                                    <div class="attendance-student-name">${record.first_name} ${record.last_name}</div>
                                    <div class="attendance-student-details">
                                        <span class="lrn-badge">${record.lrn}</span>
                                        <span>${record.class || 'N/A'}</span>
                                        ${record.subject ? `<span>${record.subject}</span>` : ''}
                                    </div>
                                </div>
                                <div class="attendance-time-status">
                                    <span class="status-badge ${record.status.toLowerCase()}">${record.status}</span>
                                    <span class="attendance-time">${record.time}</span>
                                </div>
                            </div>
                        `;
                    });
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = `
                        <div class="empty-attendance">
                            <i class="fas fa-calendar-day empty-icon"></i>
                            <p>No attendance records for today</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading today\'s attendance:', error);
                document.getElementById('today-attendance-list').innerHTML = `
                    <div class="empty-attendance">
                        <i class="fas fa-exclamation-triangle empty-icon"></i>
                        <p>Failed to load attendance data</p>
                    </div>
                `;
            }
        }

        // Manual entry functions (existing)
        function selectStudent(lrn) {
            document.getElementById('lrn').value = lrn;
            document.getElementById('lrn').focus();
        }
        
        function setTime(time) {
            // Set time for both single and bulk forms
            document.getElementById('time').value = time;
            document.getElementById('bulk_time').value = time;
        }
        
        function copyClassLRNs(className) {
            const textarea = document.getElementById('class-' + className);
            const tempTextArea = document.createElement('textarea');
            tempTextArea.value = textarea.value.trim();
            document.body.appendChild(tempTextArea);
            tempTextArea.select();
            document.execCommand('copy');
            document.body.removeChild(tempTextArea);
            
            // Paste into bulk textarea
            document.getElementById('bulk_lrns').value = textarea.value.trim();
            
            // Show success message
            alert('LRNs copied to bulk entry form!');
            
            // Switch to bulk tab
            showTab('bulk');
            document.querySelector('[onclick="showTab(\'bulk\')"]').classList.add('active');
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize QR scanner
            initializeQRScanner();
            
            // Load today's attendance
            loadTodayAttendance();
            
            // Set up event listeners
            document.getElementById('start-scan-btn').addEventListener('click', startQRScanning);
            document.getElementById('stop-scan-btn').addEventListener('click', stopQRScanning);
            
            // Auto-focus on LRN field when single tab is active
            if (document.getElementById('lrn')) {
                document.getElementById('lrn').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && this.value.length >= 11) {
                        // Auto-submit if LRN is complete
                        const form = this.closest('form');
                        if (form.checkValidity()) {
                            form.submit();
                        }
                    }
                });
            }
        });
    </script>

<?php include 'includes/footer.php'; ?>
