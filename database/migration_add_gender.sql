-- ================================================================================
-- GENDER COLUMN MIGRATION
-- ================================================================================
-- Add gender column to students table for SF2 compliance
-- This migration adds gender field to existing students table
--
-- Date: August 28, 2025
-- Purpose: Support DepEd SF2 reporting requirements
-- ================================================================================

USE attendance_system;

-- Add gender column to students table
ALTER TABLE students 
ADD COLUMN gender ENUM('Male', 'Female', 'M', 'F') NOT NULL DEFAULT 'Male' 
COMMENT 'Student gender for reporting' 
AFTER class;

-- Add middle_name column for complete name formatting (SF2 requirement)
ALTER TABLE students 
ADD COLUMN middle_name VARCHAR(50) DEFAULT NULL 
COMMENT 'Student middle name for official forms'
AFTER first_name;

-- Update existing records with default gender (you can manually update these later)
UPDATE students SET gender = 'Male' WHERE gender IS NULL;

-- Add index for gender-based reporting
ALTER TABLE students ADD INDEX idx_gender (gender);

-- Show current table structure
DESCRIBE students;

-- Sample query to verify the changes
SELECT 'Migration completed successfully. Students table now includes gender and middle_name columns.' as status;
