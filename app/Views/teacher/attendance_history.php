<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Attendance History</h1>
        <p class="text-muted mb-0">View historical attendance records for your students</p>
    </div>
    <a href="<?= base_url('teacher/attendance') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Attendance
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>Filter Records
        </h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" class="form-control" id="fromDate" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" class="form-control" id="toDate" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Section</label>
                <select class="form-select" id="sectionFilter">
                    <option value="">All Sections</option>
                    <?php if (!empty($students)): ?>
                        <?php 
                        $sections = [];
                        foreach ($students as $student) {
                            $sectionKey = grade_level_label((int) ($student['grade_level'] ?? 0)) . ' - ' . $student['section_name'];
                            if (!in_array($sectionKey, $sections)) {
                                $sections[] = $sectionKey;
                            }
                        }
                        sort($sections);
                        foreach ($sections as $section): ?>
                            <option value="<?= esc($section) ?>"><?= esc($section) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-primary" onclick="loadHistory()">
            <i class="bi bi-search me-2"></i>Load History
        </button>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-calendar3 me-2"></i>Attendance Records
        </h5>
    </div>
    <div class="card-body">
        <div id="historyContent">
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar3 fs-1 mb-3"></i>
                <h5>No Records Loaded</h5>
                <p>Select date range and click "Load History" to view attendance records.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Attendance history summary widgets (matches dashboard stat tiles) */
.att-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}

.att-summary-grid .card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.att-summary-grid .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.att-summary-grid .dash-icon-tile {
  width: 44px;
  height: 44px;
}

.att-summary-grid .dash-icon-tile i {
  font-size: 1.2rem;
}

@media (max-width: 767.98px) {
  .att-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

<script>
// Load attendance history
function loadHistory() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const historyContent = document.getElementById('historyContent');
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates.');
        return;
    }
    
    historyContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3 text-muted">Loading history...</p></div>';
    
    fetch(`<?= base_url('teacher/attendance/history/data') ?>?from=${fromDate}&to=${toDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayHistory(data.history);
            } else {
                historyContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error loading history: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            historyContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-wifi-off me-2"></i>Failed to load attendance history. Please check your connection.</div>';
        });
}

// Display attendance history
function displayHistory(history) {
    const historyContent = document.getElementById('historyContent');
    
    if (history.length === 0) {
        historyContent.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x fs-1 mb-3"></i>
                <h5>No Records Found</h5>
                <p>No attendance records found for the selected date range.</p>
            </div>
        `;
        return;
    }
    
    // Group records by date
    const groupedHistory = {};
    history.forEach(record => {
        if (!groupedHistory[record.date]) {
            groupedHistory[record.date] = [];
        }
        groupedHistory[record.date].push(record);
    });
    
    let html = '';
    
    // Sort dates in descending order
    const sortedDates = Object.keys(groupedHistory).sort((a, b) => new Date(b) - new Date(a));
    
    sortedDates.forEach(date => {
        const records = groupedHistory[date];
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Count statuses for this date
        const statusCounts = { present: 0, absent: 0, late: 0, excused: 0 };
        records.forEach(record => {
            statusCounts[record.status]++;
        });
        
        html += `
            <div class="mb-4">
                <h6 class="mb-2 text-primary">${formattedDate}</h6>
                <div class="att-summary-grid mb-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="dash-icon-tile dash-icon-tile--emerald" aria-hidden="true">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Present</div>
                                <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;">${statusCounts.present}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="dash-icon-tile dash-icon-tile--rose" aria-hidden="true">
                                <i class="bi bi-person-x-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Absent</div>
                                <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;">${statusCounts.absent}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="dash-icon-tile dash-icon-tile--amber" aria-hidden="true">
                                <i class="bi bi-alarm-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Late</div>
                                <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;">${statusCounts.late}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <div class="dash-icon-tile dash-icon-tile--cyan" aria-hidden="true">
                                <i class="bi bi-envelope-check-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Excused</div>
                                <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;">${statusCounts.excused}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>LRN</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        records.forEach(record => {
            const statusClass = {
                'present': 'success',
                'absent': 'danger', 
                'late': 'warning',
                'excused': 'info'
            }[record.status] || 'secondary';
            
            html += `
                <tr>
                    <td>${record.student_name}</td>
                    <td><small class="text-muted">${record.lrn}</small></td>
                    <td><span class="badge bg-${statusClass}">${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</span></td>
                    <td>${record.remarks || '-'}</td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    });
    
    historyContent.innerHTML = html;
}

// Auto-load history on page load
document.addEventListener('DOMContentLoaded', function() {
    loadHistory();
});
</script>

<?= $this->endSection() ?>