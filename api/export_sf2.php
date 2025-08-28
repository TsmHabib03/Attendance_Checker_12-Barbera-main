<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="SF2_Attendance_Report.csv"');
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Load SF2 configuration
    $sf2_config = require_once '../config/sf2_config.php';
    
    // Get parameters
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $class_filter = $_GET['class'] ?? '';
    $grade_level = $_GET['grade'] ?? $sf2_config['default_grade_level'];
    $section = $_GET['section'] ?? $sf2_config['default_section'];
    
    if (empty($start_date) || empty($end_date)) {
        die('Error: Start date and end date are required');
    }
    
    // Calculate month and year for the report
    $start_month = date('F Y', strtotime($start_date));
    $report_month = date('F', strtotime($start_date));
    $report_year = date('Y', strtotime($start_date));
    
    // Generate dynamic filename
    $filename = str_replace(
        ['{month}', '{year}', '{grade}', '{section}'],
        [$report_month, $report_year, $grade_level, $section],
        $sf2_config['filename_format']
    ) . '.csv';
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    // School configuration from config file
    $school_config = [
        'school_id' => $sf2_config['school_id'],
        'school_year' => $sf2_config['school_year'],
        'school_name' => $sf2_config['school_name'],
        'grade_level' => $grade_level,
        'section' => $section
    ];
    
    // Calculate school days (Monday-Friday) for the date range
    function getSchoolDays($start_date, $end_date) {
        $school_days = [];
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            $day_of_week = date('N', $current); // 1=Monday, 7=Sunday
            if ($day_of_week <= 5) { // Monday to Friday
                $school_days[] = [
                    'date' => date('Y-m-d', $current),
                    'day_letter' => ['M', 'T', 'W', 'TH', 'F'][$day_of_week - 1],
                    'day_num' => date('j', $current)
                ];
            }
            $current = strtotime('+1 day', $current);
        }
        return $school_days;
    }
    
    $school_days = getSchoolDays($start_date, $end_date);
    
    // Get students from the selected class
    $student_query = "SELECT s.*, 
                             CASE WHEN s.gender = 'M' OR s.gender = 'Male' THEN 'M' ELSE 'F' END as gender_code
                      FROM students s";
    
    $params = [];
    if (!empty($class_filter)) {
        $student_query .= " WHERE s.class = :class";
        $params['class'] = $class_filter;
    }
    
    $student_query .= " ORDER BY s.last_name, s.first_name";
    
    $stmt = $db->prepare($student_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance data for the date range
    $attendance_query = "SELECT a.lrn, a.date, a.status 
                        FROM attendance a
                        JOIN students s ON a.lrn = s.lrn
                        WHERE a.date BETWEEN :start_date AND :end_date";
    
    $att_params = ['start_date' => $start_date, 'end_date' => $end_date];
    if (!empty($class_filter)) {
        $attendance_query .= " AND s.class = :class";
        $att_params['class'] = $class_filter;
    }
    
    $att_stmt = $db->prepare($attendance_query);
    foreach ($att_params as $key => $value) {
        $att_stmt->bindValue(':' . $key, $value);
    }
    $att_stmt->execute();
    $attendance_data = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize attendance data by student and date
    $attendance_map = [];
    foreach ($attendance_data as $record) {
        $attendance_map[$record['lrn']][$record['date']] = $record['status'];
    }
    
    // Start CSV output
    $output = fopen('php://output', 'w');
    
    // Header Section
    fputcsv($output, [$sf2_config['form_title']]);
    fputcsv($output, [$sf2_config['form_subtitle']]);
    fputcsv($output, []); // Empty row
    
    // School information row 1
    fputcsv($output, [
        '', '', 'School ID', $school_config['school_id'],
        'School Year', $school_config['school_year'],
        'Report for the Month of', $start_month
    ]);
    
    // School information row 2
    fputcsv($output, [
        '', '', 'Name of School', $school_config['school_name'],
        'Grade Level', $school_config['grade_level'],
        'Section', $school_config['section']
    ]);
    
    fputcsv($output, []); // Empty row
    
    // Column headers setup
    fputcsv($output, ['LEARNER\'S NAME', '', '(1st row for dates)']);
    
    // Generate date header row
    $date_header = ['(Last Name, First Name, Middle Name)', ''];
    foreach ($school_days as $day) {
        $date_header[] = $day['day_letter'];
    }
    $date_header = array_merge($date_header, ['Total for the Month', 'ABSENT', 'TARDY', 'REMARKS']);
    fputcsv($output, $date_header);
    
    // Student data rows
    $male_students = [];
    $female_students = [];
    $daily_totals = array_fill(0, count($school_days), 0);
    $male_daily_totals = array_fill(0, count($school_days), 0);
    $female_daily_totals = array_fill(0, count($school_days), 0);
    
    foreach ($students as $student) {
        $student_row = [];
        
        // Format student name: "LASTNAME, Firstname Middlename"
        $middle_name = !empty($student['middle_name']) ? ' ' . $student['middle_name'] : '';
        $formatted_name = strtoupper($student['last_name']) . ', ' . 
                         ucfirst(strtolower($student['first_name'])) . $middle_name;
        
        $student_row[] = $formatted_name;
        $student_row[] = ''; // Empty column after name
        
        // Attendance data for each school day
        $monthly_present = 0;
        $monthly_absent = 0;
        $monthly_tardy = 0;
        
        foreach ($school_days as $index => $day) {
            $date = $day['date'];
            $status = $attendance_map[$student['lrn']][$date] ?? '';
            
            // Convert status to SF2 format
            $sf2_code = '';
            switch (strtolower($status)) {
                case 'present':
                    $sf2_code = 'P';
                    $monthly_present++;
                    $daily_totals[$index]++;
                    if ($student['gender_code'] == 'M') {
                        $male_daily_totals[$index]++;
                    } else {
                        $female_daily_totals[$index]++;
                    }
                    break;
                case 'late':
                    $sf2_code = 'L';
                    $monthly_tardy++;
                    break;
                case 'absent':
                    $sf2_code = 'A';
                    $monthly_absent++;
                    break;
                default:
                    $sf2_code = ''; // No record
            }
            
            $student_row[] = $sf2_code;
        }
        
        // Add summary columns
        $student_row[] = $monthly_present; // Total for the Month
        $student_row[] = $monthly_absent;  // ABSENT
        $student_row[] = $monthly_tardy;   // TARDY
        $student_row[] = '';               // REMARKS
        
        fputcsv($output, $student_row);
        
        // Separate students by gender
        if ($student['gender_code'] == 'M') {
            $male_students[] = $student;
        } else {
            $female_students[] = $student;
        }
    }
    
    // Male total row
    $male_row = ['MALE | TOTAL Per Day', ''];
    foreach ($male_daily_totals as $total) {
        $male_row[] = $total;
    }
    $male_row = array_merge($male_row, ['', '', '', '']); // Summary columns
    fputcsv($output, $male_row);
    
    // Female total row
    $female_row = ['FEMALE | TOTAL Per Day', ''];
    foreach ($female_daily_totals as $total) {
        $female_row[] = $total;
    }
    $female_row = array_merge($female_row, ['', '', '', '']); // Summary columns
    fputcsv($output, $female_row);
    
    // Combined total row
    $combined_row = ['Combined TOTAL PER DAY', ''];
    foreach ($daily_totals as $total) {
        $combined_row[] = $total;
    }
    $combined_row = array_merge($combined_row, ['', '', '', '']); // Summary columns
    fputcsv($output, $combined_row);
    
    // Add additional sections from the form (Guidelines, Summary, etc.)
    fputcsv($output, []); // Empty row
    fputcsv($output, ['GUIDELINES:']);
    fputcsv($output, ['1. The attendance shall be accomplished daily. Refer to the codes for checking learners\' attendance.']);
    fputcsv($output, ['2. Dates shall be written in the columns above each learner\'s name.']);
    fputcsv($output, ['3. To compute the following:']);
    fputcsv($output, []);
    
    // Summary section
    $summary_row1 = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Month:', 'No. of Days of Classes', '', 'Summary'];
    $summary_row2 = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'M', 'F', 'TOTAL'];
    fputcsv($output, $summary_row1);
    fputcsv($output, $summary_row2);
    
    // Add enrollment and attendance statistics
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Enrollment as of (1st Friday of June)', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Late Enrollment during the month', '', '', '']);
    
    fclose($output);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
