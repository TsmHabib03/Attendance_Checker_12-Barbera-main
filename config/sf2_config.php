<?php
/**
 * SF2 School Configuration
 * Configure your school's information for SF2 reports
 */

return [
    // Basic School Information
    'school_id' => '305327',
    'school_name' => 'SAN FRANCISCO HIGH SCHOOL QUEZON CITY',
    'school_year' => '2025-2026',
    
    // Default Class Information (can be overridden by filters)
    'default_grade_level' => '12',
    'default_section' => 'BARBERA',
    
    // Address and Contact (for future use)
    'school_address' => 'Quezon City, Metro Manila',
    'school_division' => 'Schools Division Office - Quezon City',
    
    // Report Settings
    'attendance_codes' => [
        'present' => 'P',
        'late' => 'L', 
        'absent' => 'A',
        'excused' => 'E',
        'default' => ''  // No record
    ],
    
    // File naming format
    'filename_format' => 'SF2_Attendance_{month}_{year}_Grade{grade}_{section}',
    
    // Additional SF2 specific settings
    'form_version' => 'SF2',
    'form_title' => 'School Form 2 (SF2) Daily Attendance Report of Learners',
    'form_subtitle' => '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)'
];
?>
