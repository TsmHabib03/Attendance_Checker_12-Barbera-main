<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Attendance Reports';
$pageIcon = 'chart-bar';

// Include the admin header
include 'includes/header.php';
?>

<!-- Report Controls -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter"></i> Report Filters</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label for="start-date">From Date:</label>
                <input type="date" id="start-date" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="end-date">To Date:</label>
                <input type="date" id="end-date" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="class-filter">Class:</label>
                <select id="class-filter" class="form-control">
                    <option value="">All Classes</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status-filter">Status:</label>
                <select id="status-filter" class="form-control">
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <button id="generate-report" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> Generate Report
            </button>
            <button id="export-csv" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Report Results -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table"></i> Attendance Report</h3>
    </div>
    <div class="card-body">
        <div id="report-loading" style="display: none; text-align: center; padding: 50px;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Generating report...</p>
        </div>
        
        <div id="report-results">
            <p class="text-center" style="color: #666; margin: 50px 0;">
                Select date range and click "Generate Report" to view attendance data.
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates
    const today = new Date();
    const oneWeekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    document.getElementById('end-date').value = today.toISOString().split('T')[0];
    document.getElementById('start-date').value = oneWeekAgo.toISOString().split('T')[0];
    
    // Load classes for filter
    loadClasses();
    
    // Event listeners
    document.getElementById('generate-report').addEventListener('click', generateReport);
    document.getElementById('export-csv').addEventListener('click', exportCSV);
});

function loadClasses() {
    fetch('../api/get_classes.php')
        .then(response => response.json())
        .then(data => {
            const classFilter = document.getElementById('class-filter');
            if (data.success) {
                data.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls;
                    option.textContent = cls;
                    classFilter.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading classes:', error));
}

function generateReport() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const classFilter = document.getElementById('class-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        return;
    }
    
    document.getElementById('report-loading').style.display = 'block';
    document.getElementById('report-results').innerHTML = '';
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        class: classFilter,
        status: statusFilter
    });
    
    fetch(`../api/get_attendance_report.php?${params}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('report-loading').style.display = 'none';
            
            if (data.success) {
                displayReport(data.data, data.summary);
            } else {
                document.getElementById('report-results').innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Error: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('report-loading').style.display = 'none';
            document.getElementById('report-results').innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Error loading report data.
                </div>
            `;
            console.error('Error:', error);
        });
}

function displayReport(data, summary) {
    let html = '';
    
    // Summary section
    if (summary) {
        html += `
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Records</span>
                        <div class="stat-icon info">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.total || 0}</h3>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Present</span>
                        <div class="stat-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.present || 0}</h3>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Late</span>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.late || 0}</h3>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Absent</span>
                        <div class="stat-icon danger">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.absent || 0}</h3>
                </div>
            </div>
        `;
    }
    
    // Data table
    if (data && data.length > 0) {
        html += `
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>LRN</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.forEach(row => {
            html += `
                <tr>
                    <td>${formatDate(row.date)}</td>
                    <td>${row.lrn}</td>
                    <td>${row.first_name} ${row.last_name}</td>
                    <td>${row.class}</td>
                    <td>${row.subject}</td>
                    <td>${formatTime(row.time)}</td>
                    <td><span class="status-badge ${row.status}">${row.status.charAt(0).toUpperCase() + row.status.slice(1)}</span></td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    } else {
        html += `
            <div class="alert alert-info" style="text-align: center; margin: 30px 0;">
                <i class="fas fa-info-circle"></i>
                No attendance records found for the selected criteria.
            </div>
        `;
    }
    
    document.getElementById('report-results').innerHTML = html;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const date = new Date();
    date.setHours(hours, minutes);
    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function exportCSV() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const classFilter = document.getElementById('class-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    
    if (!startDate || !endDate) {
        alert('Please generate a report first.');
        return;
    }
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        class: classFilter,
        status: statusFilter,
        export: 'csv'
    });
    
    window.open(`../api/get_attendance_report.php?${params}`, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>
