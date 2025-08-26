-- ================================================================================
-- ATTENDANCE SYSTEM - MASTER SQL SCRIPT
-- ================================================================================
-- Complete database setup script for the Attendance Checker System
-- This script creates the database schema, tables, and initial data
--
-- Author: Student Developer
-- Created: August 2025
-- System: Web-based Attendance System with LRN Support
-- 
-- IMPORTANT: This script is for fresh installations only
-- It will create a complete attendance system database from scratch
-- ================================================================================

-- ================================================================================
-- SECTION 1: DATABASE SETUP
-- ================================================================================
-- Complete database installation for the Attendance System
-- WARNING: This will DELETE all existing data in the attendance_system database

-- 1.1 DATABASE SETUP
-- Drop existing database if it exists (USE WITH CAUTION!)
DROP DATABASE IF EXISTS attendance_system;

-- Create new database with proper charset
CREATE DATABASE attendance_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE attendance_system;

-- 1.2 CORE TABLES CREATION
-- ----------------------------------------

-- Students table with LRN (Learner Reference Number) support
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) UNIQUE NOT NULL COMMENT 'Learner Reference Number - unique identifier',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL COMMENT 'Student class/section (e.g., 12-BARBERRA)',
    qr_code VARCHAR(255) COMMENT 'QR code data for attendance scanning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_lrn (lrn),
    INDEX idx_class (class),
    INDEX idx_email (email)
) ENGINE=InnoDB COMMENT='Student information with LRN support';

-- Class schedule table for time-based attendance logic
CREATE TABLE schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(50) NOT NULL COMMENT 'Class section (e.g., 12-BARBERRA)',
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    period_number INT NOT NULL COMMENT 'Period number (0 for breaks)',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject VARCHAR(100) NOT NULL,
    is_break BOOLEAN DEFAULT FALSE COMMENT 'TRUE if this is a break period',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Composite index for schedule lookups
    INDEX idx_class_day_time (class, day_of_week, start_time, end_time),
    INDEX idx_schedule_lookup (class, day_of_week, period_number)
) ENGINE=InnoDB COMMENT='Class schedules for attendance timing logic';

-- Attendance records with enhanced status tracking
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(13) NOT NULL COMMENT 'Student LRN reference',
    date DATE NOT NULL COMMENT 'Attendance date',
    time TIME NOT NULL COMMENT 'Scan time in Philippine timezone',
    subject VARCHAR(100) NOT NULL COMMENT 'Subject being attended',
    period_number INT COMMENT 'Period number from schedule',
    status ENUM('present', 'late', 'absent', 'no_class') DEFAULT 'present' COMMENT 'Attendance status based on timing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    FOREIGN KEY (lrn) REFERENCES students(lrn) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique constraint to prevent duplicate attendance per period
    UNIQUE KEY unique_attendance_per_period (lrn, date, period_number),
    
    -- Indexes for reporting and queries
    INDEX idx_date_status (date, status),
    INDEX idx_lrn_date (lrn, date),
    INDEX idx_subject_date (subject, date)
) ENGINE=InnoDB COMMENT='Attendance records with smart status detection';

-- Admin users table for system administration
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'MD5 hashed password (upgrade to bcrypt in production)',
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'teacher', 'staff') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_active_users (is_active, role)
) ENGINE=InnoDB COMMENT='Administrative users for system access';

-- ================================================================================
-- SECTION 2: SYSTEM DATA SETUP
-- ================================================================================

-- 3.1 DEFAULT ADMIN USER
-- Create default admin account (username: admin, password: admin123)
-- Note: Change this password immediately in production!
INSERT INTO admin_users (username, password, email, role) VALUES 
('admin', MD5('admin123'), 'admin@school.com', 'admin');

-- 3.2 CLASS SCHEDULE DATA
-- Insert complete schedule for 12-BARBERRA class
-- This schedule supports the smart attendance logic with break periods

-- Monday Schedule
INSERT INTO schedule (class, day_of_week, period_number, start_time, end_time, subject, is_break) VALUES
('12-BARBERRA', 'Monday', 1, '06:00:00', '07:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Monday', 2, '07:00:00', '08:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Monday', 3, '08:00:00', '09:00:00', 'EAP', FALSE),
('12-BARBERRA', 'Monday', 0, '09:00:00', '09:20:00', 'Break Time', TRUE),
('12-BARBERRA', 'Monday', 4, '09:20:00', '10:20:00', 'MIL', FALSE),
('12-BARBERRA', 'Monday', 5, '10:20:00', '11:20:00', 'RDL/VACANT', FALSE),
('12-BARBERRA', 'Monday', 0, '11:20:00', '11:40:00', 'Break Time', TRUE),
('12-BARBERRA', 'Monday', 6, '11:40:00', '12:40:00', 'FIL2', FALSE),
('12-BARBERRA', 'Monday', 7, '12:40:00', '13:40:00', '21 CENTURY', FALSE),

-- Tuesday Schedule
('12-BARBERRA', 'Tuesday', 1, '06:00:00', '07:00:00', 'PHYSICAL SCIENCE', FALSE),
('12-BARBERRA', 'Tuesday', 2, '07:00:00', '08:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Tuesday', 3, '08:00:00', '09:00:00', 'EAP', FALSE),
('12-BARBERRA', 'Tuesday', 0, '09:00:00', '09:20:00', 'Break Time', TRUE),
('12-BARBERRA', 'Tuesday', 4, '09:20:00', '10:20:00', 'PHYSICAL SCIENCE', FALSE),
('12-BARBERRA', 'Tuesday', 5, '10:20:00', '11:20:00', 'RDL/VACANT', FALSE),
('12-BARBERRA', 'Tuesday', 0, '11:20:00', '11:40:00', 'Break Time', TRUE),
('12-BARBERRA', 'Tuesday', 6, '11:40:00', '12:40:00', 'FIL2', FALSE),
('12-BARBERRA', 'Tuesday', 7, '12:40:00', '13:40:00', '21 CENTURY', FALSE),

-- Wednesday Schedule
('12-BARBERRA', 'Wednesday', 1, '06:00:00', '07:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Wednesday', 2, '07:00:00', '08:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Wednesday', 3, '08:00:00', '09:00:00', 'EAP', FALSE),
('12-BARBERRA', 'Wednesday', 0, '09:00:00', '09:20:00', 'Break Time', TRUE),
('12-BARBERRA', 'Wednesday', 4, '09:20:00', '10:20:00', 'VACANT', FALSE),
('12-BARBERRA', 'Wednesday', 5, '10:20:00', '11:20:00', 'PE2', FALSE),
('12-BARBERRA', 'Wednesday', 0, '11:20:00', '11:40:00', 'Break Time', TRUE),
('12-BARBERRA', 'Wednesday', 6, '11:40:00', '12:40:00', 'FIL2', FALSE),
('12-BARBERRA', 'Wednesday', 7, '12:40:00', '13:40:00', '21 CENTURY', FALSE),

-- Thursday Schedule
('12-BARBERRA', 'Thursday', 1, '06:00:00', '07:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Thursday', 2, '07:00:00', '08:00:00', 'MIL', FALSE),
('12-BARBERRA', 'Thursday', 3, '08:00:00', '09:00:00', 'EAP', FALSE),
('12-BARBERRA', 'Thursday', 0, '09:00:00', '09:20:00', 'Break Time', TRUE),
('12-BARBERRA', 'Thursday', 4, '09:20:00', '10:20:00', 'MIL', FALSE),
('12-BARBERRA', 'Thursday', 5, '10:20:00', '11:20:00', 'RDL/VACANT', FALSE),
('12-BARBERRA', 'Thursday', 0, '11:20:00', '11:40:00', 'Break Time', TRUE),
('12-BARBERRA', 'Thursday', 6, '11:40:00', '12:40:00', 'PHYSICAL SCIENCE', FALSE),
('12-BARBERRA', 'Thursday', 7, '12:40:00', '13:40:00', '21 CENTURY', FALSE),

-- Friday Schedule
('12-BARBERRA', 'Friday', 1, '06:00:00', '07:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Friday', 2, '07:00:00', '08:00:00', 'PROGRAMMING', FALSE),
('12-BARBERRA', 'Friday', 3, '08:00:00', '09:00:00', 'HOMEROOM', FALSE),
('12-BARBERRA', 'Friday', 0, '09:00:00', '09:20:00', 'Break Time', TRUE),
('12-BARBERRA', 'Friday', 4, '09:20:00', '10:20:00', 'MIL', FALSE),
('12-BARBERRA', 'Friday', 5, '10:20:00', '11:20:00', 'RDL/VACANT', FALSE),
('12-BARBERRA', 'Friday', 0, '11:20:00', '11:40:00', 'Break Time', TRUE),
('12-BARBERRA', 'Friday', 6, '11:40:00', '12:40:00', 'FIL2', FALSE),
('12-BARBERRA', 'Friday', 7, '12:40:00', '13:40:00', 'PHYSICAL SCIENCE', FALSE);

-- 3.3 SAMPLE STUDENT DATA
-- Test students for system validation
INSERT INTO students (lrn, first_name, last_name, email, class) VALUES
('123456789012', 'John', 'Doe', 'john.doe@school.com', '12-BARBERRA'),
('123456789013', 'Jane', 'Smith', 'jane.smith@school.com', '12-BARBERRA'),
('123456789014', 'Mike', 'Johnson', 'mike.johnson@school.com', '12-BARBERRA'),
('123456789015', 'Sarah', 'Williams', 'sarah.williams@school.com', '12-BARBERRA'),
('123456789016', 'David', 'Brown', 'david.brown@school.com', '12-BARBERRA');

-- ================================================================================
-- SECTION 3: SYSTEM MAINTENANCE QUERIES
-- ================================================================================

-- 4.1 DATA VALIDATION QUERIES
-- Use these queries to verify system integrity

-- Check for students without valid LRN
-- SELECT * FROM students WHERE lrn IS NULL OR lrn = '' OR LENGTH(lrn) != 12;

-- Check for attendance records without corresponding students
-- SELECT a.* FROM attendance a LEFT JOIN students s ON a.lrn = s.lrn WHERE s.lrn IS NULL;

-- Check for duplicate attendance records (should return empty if constraints work)
-- SELECT lrn, date, period_number, COUNT(*) as duplicates 
-- FROM attendance 
-- GROUP BY lrn, date, period_number 
-- HAVING COUNT(*) > 1;

-- 4.2 REPORTING QUERIES
-- Common queries for generating attendance reports

-- Daily attendance summary
-- SELECT 
--     DATE(created_at) as attendance_date,
--     status,
--     COUNT(*) as count
-- FROM attendance 
-- WHERE DATE(created_at) = CURDATE()
-- GROUP BY DATE(created_at), status;

-- Student attendance rate calculation
-- SELECT 
--     s.lrn,
--     CONCAT(s.first_name, ' ', s.last_name) as student_name,
--     COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
--     COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
--     COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
--     COUNT(*) as total_records,
--     ROUND((COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
-- FROM students s
-- LEFT JOIN attendance a ON s.lrn = a.lrn
-- WHERE s.class = '12-BARBERRA'
-- GROUP BY s.lrn, s.first_name, s.last_name;

-- ================================================================================
-- SECTION 4: PERFORMANCE OPTIMIZATION
-- ================================================================================

-- 5.1 ADDITIONAL INDEXES
-- Add these indexes if you experience performance issues with large datasets

-- For faster attendance lookups by date range
-- CREATE INDEX idx_attendance_date_range ON attendance (date, created_at);

-- For faster subject-based reporting
-- CREATE INDEX idx_attendance_subject_status ON attendance (subject, status, date);

-- For faster class-based schedule lookups
-- CREATE INDEX idx_schedule_class_day ON schedule (class, day_of_week);

-- 5.2 DATABASE MAINTENANCE
-- Regular maintenance commands (run periodically)

-- Analyze tables for query optimization
-- ANALYZE TABLE students, attendance, schedule, admin_users;

-- Optimize tables to reclaim space
-- OPTIMIZE TABLE students, attendance, schedule, admin_users;

-- ================================================================================
-- SECTION 5: SECURITY ENHANCEMENTS
-- ================================================================================

-- 6.1 STORED PROCEDURES
-- Create stored procedures for secure data access

DELIMITER //

-- Secure student registration procedure
CREATE PROCEDURE RegisterStudent(
    IN p_lrn VARCHAR(13),
    IN p_first_name VARCHAR(50),
    IN p_last_name VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_class VARCHAR(50)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Validate LRN format (12 digits)
    IF p_lrn NOT REGEXP '^[0-9]{12}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid LRN format. Must be 12 digits.';
    END IF;
    
    -- Insert student record
    INSERT INTO students (lrn, first_name, last_name, email, class)
    VALUES (p_lrn, p_first_name, p_last_name, p_email, p_class);
    
    COMMIT;
END //

-- Secure attendance marking procedure
CREATE PROCEDURE MarkAttendance(
    IN p_lrn VARCHAR(13),
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
    DECLARE v_subject VARCHAR(100);
    DECLARE v_period_number INT;
    DECLARE v_status VARCHAR(20);
    DECLARE v_class VARCHAR(50);
    DECLARE v_student_name VARCHAR(101);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get student class and name
    SELECT class, CONCAT(first_name, ' ', last_name) INTO v_class, v_student_name 
    FROM students WHERE lrn = p_lrn;
    
    IF v_class IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student not found';
    END IF;
    
    -- Find current period and subject
    SELECT subject, period_number
    INTO v_subject, v_period_number
    FROM schedule 
    WHERE class = v_class 
      AND day_of_week = DAYNAME(p_date)
      AND p_time BETWEEN start_time AND end_time
      AND is_break = FALSE
    LIMIT 1;
    
    -- Determine status based on timing logic
    IF v_subject IS NULL THEN
        -- Try to find closest class period
        SELECT subject, period_number
        INTO v_subject, v_period_number
        FROM schedule 
        WHERE class = v_class 
          AND day_of_week = DAYNAME(p_date)
          AND is_break = FALSE
        ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(p_time, start_time)))
        LIMIT 1;
        
        IF v_subject IS NULL THEN
            SET v_status = 'no_class';
            SET v_subject = 'No Class';
            SET v_period_number = 0;
        ELSE
            SET v_status = 'late';
        END IF;
    ELSE
        -- Check if late (more than 15 minutes after start)
        SELECT 
            CASE 
                WHEN TIMEDIFF(p_time, start_time) <= '00:15:00' THEN 'present'
                ELSE 'late'
            END
        INTO v_status
        FROM schedule 
        WHERE class = v_class 
          AND day_of_week = DAYNAME(p_date)
          AND period_number = v_period_number
        LIMIT 1;
    END IF;
    
    -- Insert attendance record
    INSERT INTO attendance (lrn, date, time, subject, period_number, status)
    VALUES (p_lrn, p_date, p_time, v_subject, v_period_number, v_status)
    ON DUPLICATE KEY UPDATE
        time = p_time,
        status = v_status;
    
    COMMIT;
    
    -- Return status information
    SELECT 
        v_status as attendance_status, 
        v_subject as subject, 
        v_period_number as period_number,
        v_student_name as student_name;
END //

DELIMITER ;

-- ================================================================================
-- SECTION 6: SYSTEM VALIDATION
-- ================================================================================

-- 7.1 FINAL SYSTEM CHECK
-- Run these queries to verify the system is properly set up

-- Check all tables exist
SHOW TABLES;

-- Verify table structures
DESCRIBE students;
DESCRIBE schedule;
DESCRIBE attendance;
DESCRIBE admin_users;

-- Verify sample data was inserted
SELECT COUNT(*) as student_count FROM students;
SELECT COUNT(*) as schedule_count FROM schedule;
SELECT COUNT(*) as admin_count FROM admin_users;

-- Test schedule lookup for current day/time
SELECT 
    subject,
    period_number,
    start_time,
    end_time,
    is_break
FROM schedule 
WHERE class = '12-BARBERRA' 
  AND day_of_week = DAYNAME(CURDATE())
ORDER BY start_time;

-- ================================================================================
-- END OF MASTER SQL SCRIPT
-- ================================================================================

-- Installation Instructions:
-- 1. Create a backup of any existing database before running this script
-- 2. Run the complete script in your MySQL environment
-- 3. The script will create the 'attendance_system' database with all required tables
-- 4. Sample data and admin account will be automatically created
-- 5. Change the default admin password (admin123) immediately after setup
-- 6. Test the system thoroughly after installation
--
-- System Features:
-- - LRN-based student identification (Learner Reference Number)
-- - Smart attendance status detection (present/late/absent/no_class)
-- - Philippine timezone support (configure in PHP application)
-- - Schedule-aware attendance marking
-- - Comprehensive reporting capabilities
-- - Security enhancements with stored procedures
-- - Performance optimizations with proper indexing
--
-- Default Admin Account:
-- Username: admin
-- Password: admin123 (CHANGE THIS IMMEDIATELY!)
--
-- For support and documentation, refer to the main README.md file
