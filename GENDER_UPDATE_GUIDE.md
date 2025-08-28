# GENDER FIELD UPDATE INSTRUCTIONS

## Overview
This update adds **Gender** and **Middle Name** fields to the attendance system to support DepEd SF2 reporting requirements.

## STEP 1: Database Migration

### Option A: Using phpMyAdmin or MySQL Workbench
1. Open your database management tool
2. Navigate to the `attendance_system` database
3. Run this SQL command:

```sql
USE attendance_system;

-- Add middle_name column
ALTER TABLE students 
ADD COLUMN middle_name VARCHAR(50) DEFAULT NULL 
COMMENT 'Student middle name for official forms'
AFTER first_name;

-- Add gender column
ALTER TABLE students 
ADD COLUMN gender ENUM('Male', 'Female', 'M', 'F') NOT NULL DEFAULT 'Male' 
COMMENT 'Student gender for reporting' 
AFTER middle_name;

-- Add index for gender-based reporting
ALTER TABLE students ADD INDEX idx_gender (gender);

-- Verify the changes
DESCRIBE students;
```

### Option B: Using Command Line
1. Open command prompt/terminal
2. Connect to MySQL:
```bash
mysql -u your_username -p
```
3. Run the migration file:
```bash
source "C:\xampp\htdocs\Attendance_Checker_12-Barbera-main\database\migration_add_gender.sql"
```

## STEP 2: Update Existing Student Records

After adding the columns, you may want to update existing students with proper gender values:

```sql
-- Example: Update specific students
UPDATE students SET gender = 'Female', middle_name = 'Santos' WHERE lrn = '123456789012';
UPDATE students SET gender = 'Male' WHERE lrn = '123456789013';

-- Or update all at once (you'll need to manually set correct values later)
UPDATE students SET gender = 'Male' WHERE gender IS NULL;
```

## STEP 3: Test the System

1. **Test Student Registration**:
   - Go to `register_student.php`
   - Try adding a new student with gender and middle name
   - Verify the student appears correctly in the admin panel

2. **Test Admin Management**:
   - Go to Admin → Manage Students
   - Try editing an existing student to add gender/middle name
   - Check that the View Students page shows the gender column

3. **Test SF2 Export**:
   - Go to Admin → Attendance Reports
   - Generate an SF2 export
   - Verify gender is properly reflected in the CSV

## Files Updated

### Frontend Forms:
- ✅ `register_student.php` - Added gender and middle name fields
- ✅ `admin/manage_students.php` - Added fields to admin form
- ✅ `admin/view_students.php` - Display gender in table

### Backend APIs:
- ✅ `api/register_student.php` - Handle new fields in registration
- ✅ `api/export_sf2.php` - Use gender for SF2 reporting

### Styling:
- ✅ `css/admin.css` - Added gender badge styling

### Database:
- ✅ `database/migration_add_gender.sql` - Database migration script

## Troubleshooting

### Error: "Column not found: gender"
- **Cause**: Database migration not run yet
- **Solution**: Run the SQL migration commands above

### Error: "Data too long for column"
- **Cause**: Invalid gender value
- **Solution**: Ensure gender values are only 'Male', 'Female', 'M', or 'F'

### Missing Gender in SF2 Export
- **Cause**: Existing students don't have gender values
- **Solution**: Update existing student records with proper gender values

## Success Verification

After completion, verify:
1. ✅ New students can be registered with gender/middle name
2. ✅ Admin can edit student gender/middle name
3. ✅ Gender column appears in student list
4. ✅ SF2 export includes gender-based totals
5. ✅ No database errors in browser console

## Support

If you encounter issues:
1. Check the browser console for JavaScript errors
2. Check PHP error logs for backend issues
3. Verify database connection is working
4. Ensure all files are saved and uploaded correctly

---
**Created**: August 28, 2025
**Purpose**: Add gender support for DepEd SF2 compliance
