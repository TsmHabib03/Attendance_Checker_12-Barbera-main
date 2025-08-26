<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Attendance - Attendance Checker</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-qrcode"></i> Scan QR Code for Attendance</h1>
            <p>Point your camera at a student's QR code to mark attendance</p>
        </header>

        <?php include 'includes/navigation.php'; ?>

        <main class="main-content">
            <div id="message"></div>
            
            <div class="time-display">
                <h4>Current Time (Philippines): <span id="current-time"></span></h4>
                <p id="current-schedule-info">Loading schedule info...</p>
            </div>
            
            <div class="scanner-card">
                <div id="scanner-container">
                    <div id="qr-reader"></div>
                    <div class="scanner-overlay">
                        <div class="scanner-line"></div>
                    </div>
                </div>
                <div id="scanner-controls">
                    <button id="start-scan-btn" class="btn btn-primary"><i class="fas fa-camera"></i> Start Scan</button>
                    <button id="stop-scan-btn" class="btn btn-danger" style="display: none;"><i class="fas fa-stop-circle"></i> Stop Scan</button>
                </div>
                <div id="scan-result-container" style="display: none;">
                    <div id="scan-result-animation"></div>
                    <div id="scan-result-box"></div>
                </div>
            </div>

            <div class="manual-entry">
                <h3><i class="fas fa-keyboard"></i> Manual Entry</h3>
                <p>If camera scanning doesn't work, you can manually enter the LRN:</p>
                <form id="manual-form">
                    <div class="form-group">
                        <label for="manual-lrn">LRN (Learner Reference Number):</label>
                        <input type="text" id="manual-lrn" name="lrn" class="form-control" 
                               placeholder="e.g., 123456789012" pattern="[0-9]{11,13}" 
                               title="LRN must be 11-13 digits" maxlength="13" minlength="11">
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Mark Attendance</button>
                </form>
            </div>

            <div class="today-attendance">
                <h3><i class="fas fa-list-check"></i> Today's Attendance</h3>
                <div id="today-list">
                    <p>Loading today's attendance...</p>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2025 Attendance Checker System</p>
        </footer>
    </div>

    <audio id="success-sound" preload="none"></audio>
    <audio id="error-sound" preload="none"></audio>

    <!-- Include ZXing library for QR code scanning -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        let codeReader = null;
        let selectedDeviceId = null;

        // --- ADD THIS FUNCTION ---
        function showMessage(message, type = 'info') {
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            // Automatically clear the message after 5 seconds
            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }
        // -------------------------

        // Initialize QR code scanner
        async function initializeScanner() {
            try {
                codeReader = new ZXing.BrowserQRCodeReader();
                console.log('QR Code scanner initialized');
                
                // Get available video devices
                const videoInputDevices = await codeReader.listVideoInputDevices();
                if (videoInputDevices.length > 0) {
                    selectedDeviceId = videoInputDevices[0].deviceId;
                    console.log('Found camera devices:', videoInputDevices.length);
                    showMessage('Camera initialized successfully. Click "Start Scan" to begin.', 'success');
                } else {
                    throw new Error('No camera devices found');
                }
            } catch (error) {
                console.error('Error initializing scanner:', error);
                showMessage('Error: Could not access camera. Please ensure camera permissions are granted and refresh the page.', 'error');
            }
        }

        // Handle QR code scan result
        async function handleQRCodeResult(qrData) {
            console.log('Processing QR data:', qrData);
            
            // Stop scanning temporarily
            stopScanning();
            showProcessingAnimation();
            
            try {
                // Extract LRN from QR data (format: 123456789012|timestamp)
                const lrn = qrData.split('|')[0];
                if (!/^\d{11,13}$/.test(lrn)) {
                    throw new Error('Invalid QR Code format. Expected LRN format.');
                }
                
                // Mark attendance
                const response = await fetch('api/mark_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `lrn=${encodeURIComponent(lrn)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showScanResult(result);
                    const successSound = document.getElementById('success-sound');
                    if (successSound) {
                        successSound.play().catch(e => console.log('Sound play failed:', e));
                    }
                    
                    // Refresh today's attendance list
                    loadTodayAttendance();
                } else {
                    showErrorResult(result.message);
                    const errorSound = document.getElementById('error-sound');
                    if (errorSound) {
                        errorSound.play().catch(e => console.log('Sound play failed:', e));
                    }
                }
                
            } catch (error) {
                console.error('Error marking attendance:', error);
                showErrorResult(error.message);
                const errorSound = document.getElementById('error-sound');
                if (errorSound) {
                    errorSound.play().catch(e => console.log('Sound play failed:', e));
                }
            }
        }

        // Start scanning
        async function startScanning() {
            if (!codeReader) {
                await initializeScanner();
                if (!codeReader) {
                    showMessage('Scanner initialization failed. Please refresh the page and try again.', 'error');
                    return;
                }
            }
            
            try {
                let video = document.getElementById('video');
                if (!video) {
                    // Create video element if it doesn't exist
                    video = document.createElement('video');
                    video.id = 'video';
                    video.style.width = '100%';
                    video.style.maxWidth = '400px';
                    video.style.borderRadius = '12px';
                    video.style.border = '4px solid #e9ecef';
                    document.getElementById('qr-reader').appendChild(video);
                }
                
                video.style.display = 'block';
                
                document.getElementById('start-scan-btn').style.display = 'none';
                document.getElementById('stop-scan-btn').style.display = 'inline-block';
                document.querySelector('.scanner-overlay').style.display = 'block';
                
                showMessage('Starting camera...', 'info');
                
                // Start scanning with ZXing
                await codeReader.decodeFromVideoDevice(selectedDeviceId, 'video', (result, err) => {
                    if (result) {
                        console.log('QR Code detected:', result.text);
                        handleQRCodeResult(result.text);
                    }
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error('Scanning error:', err);
                    }
                });
                
                showMessage('Camera started successfully. Point at a QR code to scan.', 'success');
                
            } catch (error) {
                console.error('Error starting scanner:', error);
                showMessage('Error starting camera: ' + error.message, 'error');
                resetScanner();
            }
        }

        // Stop scanning
        function stopScanning() {
            if (codeReader) {
                codeReader.reset();
            }
            
            const video = document.getElementById('video');
            if (video) {
                video.style.display = 'none';
            }
            
            document.getElementById('start-scan-btn').style.display = 'inline-block';
            document.getElementById('stop-scan-btn').style.display = 'none';
            document.querySelector('.scanner-overlay').style.display = 'none';
        }

        function resetScanner() {
            if (codeReader) {
                codeReader.reset();
            }
            
            const video = document.getElementById('video');
            if (video) {
                video.style.display = 'none';
            }
            
            document.getElementById('start-scan-btn').style.display = 'inline-block';
            document.getElementById('stop-scan-btn').style.display = 'none';
            document.querySelector('.scanner-overlay').style.display = 'none';
        }

        function showProcessingAnimation() {
            const resultContainer = document.getElementById('scan-result-container');
            const animationDiv = document.getElementById('scan-result-animation');
            const resultBox = document.getElementById('scan-result-box');
            
            resultContainer.style.display = 'block';
            resultBox.style.display = 'none';
            animationDiv.innerHTML = '<div class="loading-spinner"></div><p>Processing...</p>';
            animationDiv.style.display = 'flex';
        }

        function showScanResult(result) {
            const animationDiv = document.getElementById('scan-result-animation');
            const resultBox = document.getElementById('scan-result-box');
            
            animationDiv.innerHTML = '<div class="success-checkmark">✓</div>';
            
            setTimeout(() => {
                animationDiv.style.display = 'none';
                resultBox.style.display = 'block';
                resultBox.className = 'alert alert-success';
                resultBox.innerHTML = `
                    <h4><i class="fas fa-user-check"></i> Attendance Recorded!</h4>
                    <p><strong>Student:</strong> ${result.student_name}</p>
                    <p><strong>LRN:</strong> ${result.lrn}</p>
                    <p><strong>Status:</strong> <span class="badge badge-${result.status.toLowerCase()}">${result.status}</span></p>
                    <p><strong>Time:</strong> ${result.scan_time}</p>
                `;
            }, 1000);
        }

        function showErrorResult(message) {
            const animationDiv = document.getElementById('scan-result-animation');
            const resultBox = document.getElementById('scan-result-box');

            animationDiv.innerHTML = '<div class="error-cross">✗</div>';

            setTimeout(() => {
                animationDiv.style.display = 'none';
                resultBox.style.display = 'block';
                resultBox.className = 'alert alert-error';
                resultBox.innerHTML = `<h4><i class="fas fa-exclamation-triangle"></i> Scan Failed</h4><p>${message}</p>`;
            }, 1000);
        }

        document.getElementById('start-scan-btn').addEventListener('click', startScanning);
        document.getElementById('stop-scan-btn').addEventListener('click', stopScanning);

        // Manual entry form submission
        document.getElementById('manual-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const lrn = document.getElementById('manual-lrn').value;
            if (!lrn) {
                showMessage('Please enter an LRN', 'error');
                return;
            }
            // Similar logic to onScanSuccess
            showProcessingAnimation();
            try {
                const response = await fetch('api/mark_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `lrn=${encodeURIComponent(lrn)}`
                });
                const result = await response.json();
                if (result.success) {
                    showScanResult(result);
                    document.getElementById('success-sound').play();
                    loadTodayAttendance();
                } else {
                    showErrorResult(result.message);
                    document.getElementById('error-sound').play();
                }
            } catch (error) {
                showErrorResult(error.message);
                document.getElementById('error-sound').play();
            }
            document.getElementById('manual-lrn').value = '';
        });

        // Load today's attendance
        async function loadTodayAttendance() {
            try {
                const response = await fetch('api/get_today_attendance.php');
                const result = await response.json();
                
                if (result.success) {
                    const attendanceList = document.getElementById('today-list');
                    if (result.attendance.length > 0) {
                        let html = '<table class="table"><thead><tr><th>LRN</th><th>Name</th><th>Subject</th><th>Time</th><th>Status</th></tr></thead><tbody>';
                        result.attendance.forEach(record => {
                            html += `<tr>
                                <td>${record.lrn}</td>
                                <td>${record.first_name} ${record.last_name}</td>
                                <td>${record.subject}</td>
                                <td>${record.time}</td>
                                <td><span class="badge badge-${record.status.toLowerCase()}">${record.status.toUpperCase()}</span></td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        attendanceList.innerHTML = html;
                    } else {
                        attendanceList.innerHTML = '<p>No attendance records for today.</p>';
                    }
                }
            } catch (error) {
                console.error('Error loading today\'s attendance:', error);
            }
        }

        // Update time and schedule info
        function updateTimeAndSchedule() {
            const now = new Date();
            const timeString = now.toLocaleString('en-PH', { timeZone: 'Asia/Manila', hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('current-time').textContent = timeString;
            
            fetch('api/get_current_schedule.php?class=12-BARBERRA')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.current_period) {
                        const period = data.current_period;
                        document.getElementById('current-schedule-info').innerHTML = `<strong>Current:</strong> ${period.subject} (${period.start_time_formatted} - ${period.end_time_formatted})`;
                    } else {
                        document.getElementById('current-schedule-info').textContent = 'No class scheduled now.';
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', async function() {
            // Wait for the Html5Qrcode library to load
            try {
                await waitForLibrary();
                html5QrCode = new Html5Qrcode("qr-reader");
                console.log('QR Scanner initialized successfully');
            } catch (error) {
                console.error('Failed to initialize QR scanner:', error);
                showMessage('Failed to initialize camera scanner. Please refresh the page and ensure you have internet connection.', 'error');
            }

            // Add event listeners after DOM is ready
            document.getElementById('start-scan-btn').addEventListener('click', startScanning);
            document.getElementById('stop-scan-btn').addEventListener('click', stopScanning);
            document.getElementById('manual-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const lrn = document.getElementById('manual-lrn').value;
                if (!lrn) {
                    showMessage('Please enter an LRN', 'error');
                    return;
                }
                showProcessingAnimation();
                try {
                    const response = await fetch('api/mark_attendance.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `lrn=${encodeURIComponent(lrn)}`
                    });
                    const result = await response.json();
                    if (result.success) {
                        showScanResult(result);
                        const successSound = document.getElementById('success-sound');
                        if (successSound) {
                            successSound.play().catch(e => console.log('Sound play failed:', e));
                        }
                        loadTodayAttendance();
                    } else {
                        showErrorResult(result.message);
                        const errorSound = document.getElementById('error-sound');
                        if (errorSound) {
                            errorSound.play().catch(e => console.log('Sound play failed:', e));
                        }
                    }
                } catch (error) {
                    showErrorResult(error.message);
                    const errorSound = document.getElementById('error-sound');
                    if (errorSound) {
                        errorSound.play().catch(e => console.log('Sound play failed:', e));
                    }
                }
                document.getElementById('manual-lrn').value = '';
            });

            // Initialize the scanner when page loads
            initializeScanner();
            loadTodayAttendance();
            updateTimeAndSchedule();
            setInterval(updateTimeAndSchedule, 1000);
        });
    </script>
</body>
</html>
