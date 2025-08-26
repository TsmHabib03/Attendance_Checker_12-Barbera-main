# Attendance Checker System

A comprehensive web-based attendance management system using QR code scanning technology and LRN (Learner Reference Number) identification. Built with HTML, CSS, JavaScript, PHP, and MySQL.

## 🌟 Features

### Core Functionality
- **LRN-based Student Management**: Uses official Learner Reference Numbers as unique identifiers
- **QR Code Scanning**: Real-time camera-based QR code scanning for attendance
- **Smart Attendance Logic**: Automatically determines status based on timing and schedule
- **Schedule Integration**: Matches scans to class periods and subjects automatically
- **Philippine Timezone Support**: Proper timezone handling for accurate time recording

### Attendance Status Detection
- **Present**: Student scans within the first 15 minutes of class
- **Late**: Student scans after 15 minutes but before class ends
- **Absent**: Student scans after class has ended (recorded for tracking)
- **No Class**: Break time, vacant periods, or outside class hours

### Management & Reporting
- **Student Registration**: Add students using their official LRN
- **Manual Entry**: Backup option for manual attendance marking
- **Attendance Reports**: Generate detailed reports with status breakdowns
- **Real-time Schedule Display**: Shows current period and subject information
- **Export/Print**: Export data to CSV and print reports/QR codes
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## 📋 Requirements

- Web server with PHP support (Apache/Nginx)
- MySQL database
- Modern web browser with camera support
- PHP 7.4 or higher
- MySQL 5.7 or higher

## 🚀 Installation

### 1. Database Setup

1. Create a MySQL database named `attendance_system`
2. Run the master setup script:
   ```bash
   mysql -u your_username -p < database/master_attendance_system.sql
   ```
   This will create all necessary tables, sample data, and default admin account.

### 2. Configuration

1. Edit `includes/database.php` and update your database credentials:
   ```php
   private $host = 'localhost';
   private $db_name = 'attendance_system';
   private $username = 'your_mysql_username';
   private $password = 'your_mysql_password';
   ```

### 3. Web Server Setup

1. Copy all files to your web server directory (e.g., `htdocs`, `www`, or `public_html`)
2. Ensure the web server has read/write permissions
3. Access the application through your web browser

### 4. Default Admin Account

After installation, you can access the system with:
- **Username**: admin
- **Password**: admin123

⚠️ **IMPORTANT**: Change this password immediately after first login!

## 🏫 System Logic

### LRN (Learner Reference Number)
- **Primary Identifier**: All students are identified by their official 11-13 digit LRN
- **Format**: Numeric only (e.g., 123456789012)
- **Unique**: Each LRN must be unique in the system
- **QR Codes**: Generated using the student's LRN

### Schedule-Based Attendance
The system uses a schedule table to automatically detect:
- Current subject being attended
- Whether it's class time or break time
- Appropriate attendance status based on timing

### Time-Based Status Logic
1. **Early Arrival**: Up to 30 minutes before class starts (allowed)
2. **Present**: Scanned within first 15 minutes of class period
3. **Late**: Scanned after 15 minutes but before class ends
4. **Absent**: Scanned after class has ended
5. **No Class**: Break periods or vacant time slots

### Break Time Handling
- **Morning Break**: 9:00-9:20 AM - No attendance allowed
- **Lunch Break**: 11:20-11:40 AM - No attendance allowed
- **Outside Hours**: Shows "No class scheduled" message

## 📁 File Structure

```
attendance_checker_clean/
├── index.php                 # Main dashboard
├── register_student.php      # Student registration form
├── scan_attendance.php       # QR code scanning interface
├── view_students.php         # Student management
├── attendance_report.php     # Reports and analytics
├── includes/
│   └── database.php          # Database connection with timezone handling
├── api/
│   ├── register_student.php  # Student registration API
│   ├── mark_attendance.php   # Attendance marking API (with smart logic)
│   ├── get_students.php      # Student data API
│   ├── get_attendance_report.php # Report generation API
│   ├── get_current_schedule.php  # Schedule detection API
│   └── test_timezone.php     # Timezone verification API
├── css/
│   └── style.css             # Application styling
├── js/
│   └── main.js               # Frontend JavaScript
└── database/
    └── master_attendance_system.sql  # Complete database setup
```

## 🗓️ Class Schedule

The system comes pre-configured with the 12-BARBERRA class schedule:

### Monday - Friday Schedule
- **Period 1**: 6:00-7:00 AM
- **Period 2**: 7:00-8:00 AM  
- **Period 3**: 8:00-9:00 AM
- **Break**: 9:00-9:20 AM
- **Period 4**: 9:20-10:20 AM
- **Period 5**: 10:20-11:20 AM
- **Break**: 11:20-11:40 AM
- **Period 6**: 11:40-12:40 PM
- **Period 7**: 12:40-1:40 PM

Subjects include Programming, Physical Science, EAP, MIL, PE2, FIL2, 21st Century Literature, and more.

## 🔧 Usage Instructions

### Registering Students
1. Go to "Register Student" page
2. Enter the student's official LRN (11-13 digits)
3. Fill in personal information
4. Select class (12-BARBERRA)
5. QR code will be automatically generated

### Taking Attendance
1. Students scan their QR codes using the "Scan Attendance" page
2. System automatically determines:
   - Current subject (based on schedule)
   - Attendance status (present/late/absent/no_class)
   - Period information
3. Duplicate scans per period are prevented
4. Real-time feedback is provided

### Viewing Reports
1. Access "Attendance Report" page
2. Filter by date range, student, or status
3. View detailed statistics and charts
4. Export to CSV for external analysis

## 🛠️ Customization

### Adding New Classes
```sql
INSERT INTO schedule (class, day_of_week, period_number, start_time, end_time, subject, is_break) 
VALUES ('NEW-CLASS', 'Monday', 1, '06:00:00', '07:00:00', 'SUBJECT_NAME', FALSE);
```

### Modifying Schedule
```sql
UPDATE schedule 
SET subject = 'NEW_SUBJECT' 
WHERE class = '12-BARBERRA' AND day_of_week = 'Monday' AND period_number = 1;
```

### Changing Time Rules
Edit the attendance logic in `api/mark_attendance.php`:
- Change the 15-minute "present" window
- Modify early arrival allowance (currently 30 minutes)
- Adjust break time handling

## 🔍 Testing & Verification

### Test the System
1. **Register a test student** with a valid LRN format
2. **Test QR scanning** during different time periods:
   - During class time (should mark Present/Late)
   - During break time (should show "No attendance required")
   - Outside class hours (should show "No class scheduled")
3. **Verify timezone** by visiting `api/test_timezone.php`
4. **Check reports** to ensure data is recorded correctly

### Timezone Verification
- All times are displayed in Philippine Time (Asia/Manila)
- Database stores times in local timezone
- Real-time clock on scan page updates every second

## 🔒 Security Features

- **Input validation** on all forms (LRN format, email validation)
- **SQL injection protection** using prepared statements
- **Duplicate prevention** via database constraints
- **Admin authentication** (basic - enhance as needed)
- **Stored procedures** for secure data operations

## 🐛 Troubleshooting

### Common Issues

1. **Camera not working**
   - Ensure HTTPS connection (required for camera access)
   - Check browser permissions
   - Try different browsers

2. **Timezone issues**
   - Verify `date_default_timezone_set('Asia/Manila')` in PHP files
   - Check MySQL timezone settings
   - Visit `api/test_timezone.php` for debugging

3. **QR codes not scanning**
   - Ensure good lighting
   - Hold code steady and at proper distance
   - Check if QR code is properly generated

4. **Database connection errors**
   - Verify credentials in `includes/database.php`
   - Check MySQL service is running
   - Ensure database exists and is accessible

## 🚀 Future Enhancements

- **Mobile app** conversion using WebView or React Native
- **Push notifications** for attendance reminders
- **Biometric integration** (fingerprint, face recognition)
- **Parent portal** for attendance monitoring
- **Advanced analytics** and dashboards
- **Multi-class support** with role-based access

## 📞 Support

For issues, questions, or contributions:
1. Check the troubleshooting section above
2. Review the file comments for technical details
3. Test with the provided sample data
4. Verify timezone settings using the test endpoints

## 📄 License

This project is developed for educational purposes. Feel free to modify and use according to your institution's needs.

---

**Note**: This system uses LRN (Learner Reference Number) as the primary student identifier, replacing any previous student ID systems. The schedule-based attendance logic automatically determines attendance status based on timing and class periods.

```
attendance-checker/
├── api/                          # PHP API endpoints
│   ├── get_attendance_report.php # Generate attendance reports
│   ├── get_dashboard_stats.php   # Dashboard statistics
│   ├── get_students.php          # Fetch all students
│   ├── get_student_details.php   # Individual student details
│   ├── get_today_attendance.php  # Today's attendance
│   ├── mark_attendance.php       # Mark student attendance
│   └── register_student.php      # Register new student
├── css/
│   └── style.css                 # Main stylesheet
├── database/
│   └── setup.sql                 # Database structure
├── includes/
│   └── database.php              # Database connection
├── js/
│   └── main.js                   # JavaScript utilities
├── attendance_report.php         # Reports page
├── index.php                     # Homepage/Dashboard
├── register_student.php          # Student registration
├── scan_attendance.php           # QR code scanner
├── view_students.php             # Student management
└── README.md                     # This file
```

## 🎯 Usage

### For Teachers/Administrators:

1. **Register Students**:
   - Go to "Register Student"
   - Fill in student details
   - System generates unique QR code
   - Print QR code for student

2. **Take Attendance**:
   - Go to "Scan Attendance"
   - Allow camera access
   - Point camera at student QR codes
   - Attendance is automatically recorded

3. **View Reports**:
   - Go to "Attendance Report"
   - Select date range and class
   - View detailed statistics and charts
   - Export to CSV or print reports

### For Students:

1. Get your QR code from the teacher
2. Present QR code when attendance is being taken
3. Keep QR code safe and readable

## 🔧 Configuration Options

### Time Settings
- Modify late threshold in `api/mark_attendance.php` (default: 9:00 AM)
- Adjust timezone in PHP configuration if needed

### QR Code Settings
- QR codes are generated using Google QR Code API
- Format: `STUDENT_ID|TIMESTAMP`
- Can be customized in `api/register_student.php`

### Classes
- Modify available classes in registration and filter forms
- Default classes: 10-A through 12-C

## 🛡️ Security Considerations

1. **Database Security**:
   - Use strong database passwords
   - Limit database user permissions
   - Consider using environment variables for credentials

2. **Input Validation**:
   - All inputs are validated and sanitized
   - Prepared statements prevent SQL injection

3. **Camera Access**:
   - Camera access requires user permission
   - No images are stored or transmitted

## 🌐 Browser Compatibility

- ✅ Chrome (recommended)
- ✅ Firefox
- ✅ Safari
- ✅ Edge
- ⚠️ Internet Explorer (limited support)

## 📱 Mobile Support

The application is fully responsive and works on:
- Smartphones (iOS/Android)
- Tablets
- Desktop computers

## 🎨 Customization

### Styling
- Modify `css/style.css` for custom colors and layout
- Uses CSS Grid and Flexbox for responsive design
- Gradient backgrounds and modern UI elements

### Functionality
- Add new fields to student registration
- Modify attendance rules and late policies
- Extend reporting features

## 🐛 Troubleshooting

### Common Issues:

1. **Camera not working**:
   - Ensure HTTPS connection (required for camera access)
   - Check browser permissions
   - Try different browser

2. **Database connection errors**:
   - Verify database credentials
   - Check database server status
   - Ensure database exists

3. **QR codes not scanning**:
   - Ensure good lighting
   - Clean camera lens
   - Try manual entry as backup

## 📖 Learning Objectives

This project helps beginners learn:

- **Frontend Development**: HTML5, CSS3, JavaScript (ES6+)
- **Backend Development**: PHP, MySQL
- **Web APIs**: Camera access, QR code generation
- **Database Design**: Relational database structure
- **Responsive Design**: Mobile-first approach
- **AJAX**: Asynchronous communication
- **Data Visualization**: Charts and reports

## 🤝 Contributing

Feel free to:
- Report bugs
- Suggest new features
- Submit pull requests
- Improve documentation

## 📄 License

This project is open-source and available for educational purposes.

## 🙏 Acknowledgments

- QR code generation: Google QR Code API
- Charts: Chart.js library
- QR code scanning: ZXing library
- Icons: Unicode emojis

---

**Happy Learning! 🎓**

For questions or support, please check the code comments or create an issue.
