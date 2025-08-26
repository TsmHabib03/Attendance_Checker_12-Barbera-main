<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Checker System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 Attendance Checker System</h1>
            <p>QR Code Based Attendance Management</p>
        </header>

        <?php include 'includes/navigation.php'; ?>

        <main class="main-content">
            <div class="welcome-section">
                <h2>Welcome to the Attendance System</h2>
                <p>This system allows you to manage student attendance using QR codes. Here's what you can do:</p>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3>Student Registration</h3>
                        <p>New students can register themselves in the system with their LRN and basic information.</p>
                        <a href="register_student.php" class="btn btn-primary">Register Now</a>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h3>QR Code Scanner</h3>
                        <p>Students can quickly mark their attendance by scanning their QR codes using any device with a camera.</p>
                        <a href="scan_attendance.php" class="btn btn-primary">Start Scanning</a>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Admin Management</h3>
                        <p>Teachers and administrators can manage students, schedules, and generate detailed attendance reports.</p>
                        <a href="admin/login.php" class="btn btn-secondary">Admin Login</a>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h3>Today's Quick Stats</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h4 id="totalStudents">--</h4>
                            <p>Registered Students</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4 id="presentToday">--</h4>
                            <p>Present Today</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h4 id="attendanceRate">--</h4>
                            <p>Attendance Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h3>How It Works</h3>
                <div class="steps-container">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h4>Register</h4>
                        <p>New students register with their LRN and class information</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h4>Get QR Code</h4>
                        <p>Students receive their unique QR code for attendance</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h4>Scan Daily</h4>
                        <p>Use the scanner to mark attendance quickly and accurately</p>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2025 Attendance Checker System - Built with HTML, CSS, JavaScript, PHP & MySQL</p>
        </footer>
    </div>

    <script src="js/main.js"></script>
    <script>
        // Load dashboard statistics
        loadDashboardStats();
    </script>
</body>
</html>
