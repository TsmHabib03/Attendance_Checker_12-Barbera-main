<?php
require_once 'config.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Attendance Reports';
$pageIcon = 'chart-bar';

// Include the admin header
include 'includes/header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Report Controls -->
<div class="card reports-filters-card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter"></i> Report Filters</h3>
    </div>
    <div class="card-body">
        <div class="filters-grid">
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
        
        <div class="filter-actions">
            <button id="generate-report" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> Generate Report
            </button>
            <button id="export-csv" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export Simple CSV
            </button>
            <button id="export-sf2" class="btn btn-success">
                <i class="fas fa-file-alt"></i> Export SF2 Format
            </button>
        </div>
    </div>
</div>

<!-- Analytics Overview -->
<div class="analytics-section">
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Analytics Overview</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Report Results -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table"></i> Attendance Report</h3>
        <div class="card-actions">
            <input type="text" id="table-search" class="form-control" placeholder="Search records..." style="display: none;">
        </div>
    </div>
    <div class="card-body">
        <div id="report-loading" class="loading-state" style="display: none;">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Generating report...</p>
            </div>
        </div>
        
        <div id="report-results">
            <div class="empty-state">
                <i class="fas fa-chart-bar fa-3x"></i>
                <h4>Welcome to Attendance Analytics</h4>
                <p>Loading your recent attendance data...</p>
            </div>
        </div>
        
        <div id="pagination-container" style="display: none;">
            <div class="pagination-info">
                <span id="pagination-text"></span>
            </div>
            <div class="pagination-controls">
                <button id="prev-page" class="btn btn-secondary" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div id="page-numbers"></div>
                <button id="next-page" class="btn btn-secondary" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables for pagination and data management
let currentPage = 1;
let rowsPerPage = 15;
let allTableData = [];
let currentSummary = null;
let attendanceChart = null;

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
    document.getElementById('export-sf2').addEventListener('click', exportSF2);
    document.getElementById('table-search').addEventListener('keyup', filterTable);
    
    // Auto-load report on page load
    generateReport();
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
    
    // Validate date range
    if (new Date(startDate) > new Date(endDate)) {
        alert('Start date must be before or equal to end date.');
        return;
    }
    
    document.getElementById('report-loading').style.display = 'block';
    document.getElementById('report-results').innerHTML = '';
    document.getElementById('table-search').style.display = 'none';
    document.getElementById('pagination-container').style.display = 'none';
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        class: classFilter,
        status: statusFilter
    });
    
    const apiUrl = `../api/get_attendance_report.php?${params}`;
    console.log('API URL:', apiUrl); // Debug log
    
    fetch(apiUrl)
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data); // Debug log
            document.getElementById('report-loading').style.display = 'none';
            
            if (data.success) {
                allTableData = data.data || [];
                currentSummary = data.summary || {};
                
                console.log('Summary data:', currentSummary); // Debug log
                console.log('Table data count:', allTableData.length); // Debug log
                
                displayReport(allTableData, currentSummary);
                displayChart(currentSummary);
                
                if (allTableData.length > 0) {
                    document.getElementById('table-search').style.display = 'inline-block';
                }
            } else {
                document.getElementById('report-results').innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Error: ${data.message || 'Unknown error occurred'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch Error Details:', error); // Debug log
            document.getElementById('report-loading').style.display = 'none';
            document.getElementById('report-results').innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Error loading report data: ${error.message || 'Network error'}
                    <br><small>Check the browser console for more details.</small>
                </div>
            `;
        });
}

function displayChart(summary) {
    const ctx = document.getElementById('attendanceChart');
    
    if (!ctx) {
        console.error('Chart canvas element not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (attendanceChart) {
        attendanceChart.destroy();
        attendanceChart = null;
    }
    
    if (!summary || (summary.present === 0 && summary.late === 0 && summary.absent === 0)) {
        const chartContainer = ctx.parentElement;
        chartContainer.innerHTML = '<canvas id="attendanceChart"></canvas><div class="empty-chart"><i class="fas fa-chart-pie fa-3x"></i><p>No data to display</p></div>';
        return;
    }
    
    // Ensure canvas is properly reset
    const newCanvas = document.createElement('canvas');
    newCanvas.id = 'attendanceChart';
    ctx.parentElement.replaceChild(newCanvas, ctx);
    
    try {
        attendanceChart = new Chart(newCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'Absent'],
                datasets: [{
                    data: [summary.present || 0, summary.late || 0, summary.absent || 0],
                    backgroundColor: [
                        '#10B981',  // Success color
                        '#F59E0B',  // Warning color
                        '#EF4444'   // Danger color
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                if (total === 0) return `${context.label}: 0`;
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error creating chart:', error);
        newCanvas.parentElement.innerHTML = '<div class="empty-chart"><i class="fas fa-exclamation-triangle fa-3x"></i><p>Error displaying chart</p></div>';
    }
}

function displayReport(data, summary) {
    let html = '';
    
    // Summary section with clickable cards
    if (summary) {
        html += `
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card clickable-stat" data-status="" onclick="filterByStatus('')">
                    <div class="stat-header">
                        <span class="stat-title">Total Records</span>
                        <div class="stat-icon info">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.total || 0}</h3>
                </div>
                
                <div class="stat-card clickable-stat" data-status="present" onclick="filterByStatus('present')">
                    <div class="stat-header">
                        <span class="stat-title">Present</span>
                        <div class="stat-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.present || 0}</h3>
                </div>
                
                <div class="stat-card clickable-stat" data-status="late" onclick="filterByStatus('late')">
                    <div class="stat-header">
                        <span class="stat-title">Late</span>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <h3 class="stat-number">${summary.late || 0}</h3>
                </div>
                
                <div class="stat-card clickable-stat" data-status="absent" onclick="filterByStatus('absent')">
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
            <div class="table-wrapper">
                <div class="table-container">
                    <table class="table" id="attendance-table">
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
                        <tbody id="table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        document.getElementById('report-results').innerHTML = html;
        renderTablePage(data, 1);
        setupPagination(data.length);
    } else {
        html += `
            <div class="empty-state">
                <i class="fas fa-info-circle fa-3x"></i>
                <h4>No Records Found</h4>
                <p>No attendance records found for the selected criteria. Try adjusting your filters.</p>
            </div>
        `;
        document.getElementById('report-results').innerHTML = html;
        document.getElementById('pagination-container').style.display = 'none';
    }
}

function renderTablePage(data, page) {
    const startIndex = (page - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const pageData = data.slice(startIndex, endIndex);
    
    let tableHTML = '';
    pageData.forEach(row => {
        // Ensure all fields exist with fallback values
        const date = row.date || '';
        const lrn = row.lrn || '';
        const firstName = row.first_name || '';
        const lastName = row.last_name || '';
        const className = row.class || '';
        const subject = row.subject || 'N/A';
        const time = row.time || '';
        const status = row.status || 'unknown';
        
        tableHTML += `
            <tr>
                <td>${formatDate(date)}</td>
                <td>${lrn}</td>
                <td>${firstName} ${lastName}</td>
                <td>${className}</td>
                <td>${subject}</td>
                <td>${formatTime(time)}</td>
                <td><span class="status-badge ${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
            </tr>
        `;
    });
    
    const tableBody = document.getElementById('table-body');
    if (tableBody) {
        tableBody.innerHTML = tableHTML;
    } else {
        console.error('Table body element not found');
    }
    currentPage = page;
}

function setupPagination(totalRows) {
    if (totalRows <= rowsPerPage) {
        document.getElementById('pagination-container').style.display = 'none';
        return;
    }
    
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    document.getElementById('pagination-container').style.display = 'block';
    
    // Update pagination info
    const startRow = ((currentPage - 1) * rowsPerPage) + 1;
    const endRow = Math.min(currentPage * rowsPerPage, totalRows);
    document.getElementById('pagination-text').textContent = 
        `Showing ${startRow}-${endRow} of ${totalRows} records`;
    
    // Update navigation buttons
    document.getElementById('prev-page').disabled = currentPage <= 1;
    document.getElementById('next-page').disabled = currentPage >= totalPages;
    
    // Generate page numbers
    let pageNumbersHTML = '';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        pageNumbersHTML += `
            <button class="btn btn-page ${i === currentPage ? 'active' : ''}" 
                    onclick="changePage(${i})">${i}</button>
        `;
    }
    
    document.getElementById('page-numbers').innerHTML = pageNumbersHTML;
    
    // Add event listeners for prev/next buttons
    document.getElementById('prev-page').onclick = () => {
        if (currentPage > 1) changePage(currentPage - 1);
    };
    document.getElementById('next-page').onclick = () => {
        if (currentPage < totalPages) changePage(currentPage + 1);
    };
}

function changePage(page) {
    renderTablePage(allTableData, page);
    setupPagination(allTableData.length);
}

function filterTable() {
    const searchTerm = document.getElementById('table-search').value.toLowerCase();
    
    if (!searchTerm) {
        renderTablePage(allTableData, 1);
        setupPagination(allTableData.length);
        return;
    }
    
    const filteredData = allTableData.filter(row => {
        return (
            row.lrn.toLowerCase().includes(searchTerm) ||
            `${row.first_name} ${row.last_name}`.toLowerCase().includes(searchTerm) ||
            row.class.toLowerCase().includes(searchTerm) ||
            row.subject.toLowerCase().includes(searchTerm) ||
            row.status.toLowerCase().includes(searchTerm) ||
            formatDate(row.date).toLowerCase().includes(searchTerm)
        );
    });
    
    renderTablePage(filteredData, 1);
    setupPagination(filteredData.length);
}

function filterByStatus(status) {
    // Update status filter dropdown
    document.getElementById('status-filter').value = status;
    
    // Update visual indicator on cards
    document.querySelectorAll('.clickable-stat').forEach(card => {
        card.classList.remove('active');
    });
    
    if (status) {
        document.querySelector(`.clickable-stat[data-status="${status}"]`).classList.add('active');
    } else {
        document.querySelector('.clickable-stat[data-status=""]').classList.add('active');
    }
    
    // Regenerate report with new filter
    generateReport();
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return 'Invalid Date';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        console.error('Error formatting date:', dateStr, error);
        return 'Invalid Date';
    }
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    try {
        const [hours, minutes] = timeStr.split(':');
        if (!hours || !minutes) return timeStr; // Return as-is if not in expected format
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes));
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    } catch (error) {
        console.error('Error formatting time:', timeStr, error);
        return timeStr || 'N/A';
    }
}

function exportSF2() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const classFilter = document.getElementById('class-filter').value;
    
    if (!startDate || !endDate) {
        alert('Please select date range first.');
        return;
    }
    
    // Validate it's a reasonable month range (not more than 2 months)
    const start = new Date(startDate);
    const end = new Date(endDate);
    const daysDiff = (end - start) / (1000 * 60 * 60 * 24);
    
    if (daysDiff > 62) {
        if (!confirm('SF2 reports are typically for one month. Continue with this date range?')) {
            return;
        }
    }
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        class: classFilter,
        grade: '12', // You can make this dynamic
        section: classFilter || 'BARBERA' // Use class as section or default
    });
    
    // Show loading message
    const originalText = document.getElementById('export-sf2').innerHTML;
    document.getElementById('export-sf2').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating SF2...';
    document.getElementById('export-sf2').disabled = true;
    
    // Create download link
    const downloadUrl = `../api/export_sf2.php?${params}`;
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button after a short delay
    setTimeout(() => {
        document.getElementById('export-sf2').innerHTML = originalText;
        document.getElementById('export-sf2').disabled = false;
    }, 2000);
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
