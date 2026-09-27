<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.student-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: var(--hairline);
    min-height: 120px;
}
.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.student-card.present { border-color: #28a745; background-color: #f8fff9; }
.student-card.absent { border-color: #dc3545; background-color: #fff8f8; }
.student-card.late { border-color: #ffc107; background-color: #fffdf5; }
.student-card.excused { border-color: #17a2b8; background-color: #f8fdff; }

/* Fix for date input overlay issue */
.col-auto {
    position: relative;
    z-index: 1;
}
.form-control[type="date"] {
    position: relative;
    z-index: 2;
    background-color: #fff;
}
.card-body {
    overflow: visible;
}

.status-btn {
    border: 1px solid #dee2e6;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8125rem;
    margin: 2px;
    min-width: 60px;
    background-color: #f8f9fa;
    color: #6c757d;
    transition: all 0.2s ease;
}
.status-btn:hover {
    background-color: #e9ecef;
}
.status-btn.active {
    font-weight: bold;
    transform: scale(1.05);
}
.status-present.active { border-color: #28a745; background-color: #d4edda; color: #155724; }
.status-absent.active { border-color: #dc3545; background-color: #f8d7da; color: #721c24; }
.status-late.active { border-color: #ffc107; background-color: #fff3cd; color: #856404; }
.status-excused.active { border-color: #17a2b8; background-color: #d1ecf1; color: #0c5460; }

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

/* Attendance summary widgets (matches dashboard stat tiles) */
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

@media (max-width: 768px) {
    .grid-container {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }
    .att-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Student Attendance</h1>
        <h5 class="text-muted mb-0"><?= date('F j, Y', strtotime($selectedDate)) ?></h5>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <input type="date" class="form-control" value="<?= $selectedDate ?>" onchange="changeDate(this.value)" style="width: 160px;">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gridViewBtn" onclick="toggleView('grid')">
                <i class="bi bi-grid-3x3-gap"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="listViewBtn" onclick="toggleView('list')">
                <i class="bi bi-list"></i>
            </button>
            <a href="<?= base_url('teacher/attendance/history') ?>" class="btn btn-warning btn-sm" style="font-weight: 700; box-shadow: 0 2px 4px rgba(255,193,7,0.3);">
                <i class="bi bi-clock-history me-1"></i>View History
            </a>
        </div>
        <select class="form-select" id="sectionFilter" style="width: 220px; font-weight: 400;">
            <option value="">All Sections</option>
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
        </select>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>



<?php if (!empty($students)): ?>
    <!-- Attendance Summary (minimalist stat tiles, same style as other pages) -->
    <div class="att-summary-grid mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="dash-icon-tile dash-icon-tile--emerald" aria-hidden="true">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-muted small">Present</div>
                    <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;" id="presentCount">0</div>
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
                    <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;" id="absentCount">0</div>
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
                    <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;" id="lateCount">0</div>
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
                    <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;" id="excusedCount">0</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form method="post" action="<?= base_url('teacher/attendance') ?>" id="attendanceForm">
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= $selectedDate ?>">
        
        <!-- Grid View -->
        <div id="gridViewContainer" class="grid-container">
            <?php foreach ($students as $student): ?>
                <?php 
                $currentStatus = $attendanceData[$student['id']]['status'] ?? 'present';
                ?>
                <div class="card student-card <?= $currentStatus ?>" data-student="<?= $student['id'] ?>" data-section="<?= esc(grade_level_label((int) ($student['grade_level'] ?? 0)) . ' - ' . $student['section_name']) ?>">
                    <div class="card-body p-3 text-center">
                        <div class="mb-2">
                            <strong class="d-block"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                            <small class="text-muted"><?= esc($student['lrn']) ?></small>
                            <small class="d-block text-muted"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0)) . ' - ' . $student['section_name']) ?></small>
                        </div>
                        
                        <div class="status-buttons">
                            <button type="button" class="status-btn status-present <?= $currentStatus === 'present' ? 'active' : '' ?>" 
                                    onclick="setStatus(<?= $student['id'] ?>, 'present')">Present</button>
                            <button type="button" class="status-btn status-absent <?= $currentStatus === 'absent' ? 'active' : '' ?>" 
                                    onclick="setStatus(<?= $student['id'] ?>, 'absent')">Absent</button>
                            <button type="button" class="status-btn status-late <?= $currentStatus === 'late' ? 'active' : '' ?>" 
                                    onclick="setStatus(<?= $student['id'] ?>, 'late')">Late</button>
                            <button type="button" class="status-btn status-excused <?= $currentStatus === 'excused' ? 'active' : '' ?>" 
                                    onclick="setStatus(<?= $student['id'] ?>, 'excused')">Excused</button>
                        </div>
                        
                        <input type="hidden" name="attendance[<?= $student['id'] ?>]" value="<?= $currentStatus ?>" id="status_<?= $student['id'] ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- List View -->
        <div id="listViewContainer" class="d-none">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>LRN</th>
                            <th>Grade & Section</th>
                            <th>Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php 
                            $currentStatus = $attendanceData[$student['id']]['status'] ?? 'present';
                            ?>
                            <tr data-section="<?= esc(grade_level_label((int) ($student['grade_level'] ?? 0)) . ' - ' . $student['section_name']) ?>" class="list-student-row">
                                <td><strong><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
                                <td><?= esc($student['lrn']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0)) . ' - ' . $student['section_name']) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm status-btn status-present <?= $currentStatus === 'present' ? 'active' : '' ?>" 
                                                onclick="setStatusList(<?= $student['id'] ?>, 'present', this)">Present</button>
                                        <button type="button" class="btn btn-sm status-btn status-absent <?= $currentStatus === 'absent' ? 'active' : '' ?>" 
                                                onclick="setStatusList(<?= $student['id'] ?>, 'absent', this)">Absent</button>
                                        <button type="button" class="btn btn-sm status-btn status-late <?= $currentStatus === 'late' ? 'active' : '' ?>" 
                                                onclick="setStatusList(<?= $student['id'] ?>, 'late', this)">Late</button>
                                        <button type="button" class="btn btn-sm status-btn status-excused <?= $currentStatus === 'excused' ? 'active' : '' ?>" 
                                                onclick="setStatusList(<?= $student['id'] ?>, 'excused', this)">Excused</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-success btn-lg px-5">
                <i class="bi bi-check-circle me-2"></i> Save Attendance
            </button>
        </div>
    </form>

<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-people fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">No Students Found</h5>
            <p class="text-muted mb-0">You are not assigned as an adviser to any section with enrolled students.</p>
        </div>
    </div>
<?php endif; ?>



<script>
function setStatus(studentId, status) {
    // Update hidden input
    document.getElementById('status_' + studentId).value = status;
    
    // Update card appearance
    const card = document.querySelector('[data-student="' + studentId + '"]');
    card.className = 'card student-card ' + status;
    
    // Update button states
    const buttons = card.querySelectorAll('.status-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    card.querySelector('.status-' + status).classList.add('active');
    
    updateCounts();
}

function markAll(status) {
    <?php foreach ($students as $student): ?>
        setStatus(<?= $student['id'] ?>, status);
    <?php endforeach; ?>
}

function clearAll() {
    <?php foreach ($students as $student): ?>
        setStatus(<?= $student['id'] ?>, 'present');
    <?php endforeach; ?>
}

// Initialize counts on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCounts();
    toggleView('grid'); // Set default view
    
    // Section filter functionality
    const sectionFilter = document.getElementById('sectionFilter');
    if (sectionFilter) {
        sectionFilter.addEventListener('change', function() {
            const selectedSection = this.value;
            const studentCards = document.querySelectorAll('.student-card[data-section]');
            const listRows = document.querySelectorAll('.list-student-row[data-section]');
            
            // Filter grid view
            studentCards.forEach(card => {
                if (selectedSection === '' || card.dataset.section === selectedSection) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Filter list view
            listRows.forEach(row => {
                if (selectedSection === '' || row.dataset.section === selectedSection) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update counts after filtering
            updateCounts();
        });
    }
});

// Update counts to only count visible students
function updateCounts() {
    const counts = { present: 0, absent: 0, late: 0, excused: 0 };
    
    document.querySelectorAll('.student-card:not([style*="display: none"]) input[name^="attendance"]').forEach(input => {
        counts[input.value]++;
    });
    
    document.getElementById('presentCount').textContent = counts.present;
    document.getElementById('absentCount').textContent = counts.absent;
    document.getElementById('lateCount').textContent = counts.late;
    document.getElementById('excusedCount').textContent = counts.excused;
}

// Toggle between grid and list view
function toggleView(view) {
    const gridView = document.getElementById('gridViewContainer');
    const listView = document.getElementById('listViewContainer');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    
    if (view === 'grid') {
        gridView.classList.remove('d-none');
        listView.classList.add('d-none');
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        gridView.classList.add('d-none');
        listView.classList.remove('d-none');
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    }
}

// Set status for list view
function setStatusList(studentId, status, button) {
    // Update hidden input (same as grid view)
    document.getElementById('status_' + studentId).value = status;
    
    // Update button states in list view
    const row = button.closest('tr');
    const buttons = row.querySelectorAll('.status-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
    
    // Also update grid view card if it exists
    const card = document.querySelector('[data-student="' + studentId + '"]');
    if (card) {
        card.className = 'card student-card ' + status;
        const cardButtons = card.querySelectorAll('.status-btn');
        cardButtons.forEach(btn => btn.classList.remove('active'));
        card.querySelector('.status-' + status).classList.add('active');
    }
    
    updateCounts();
}

// Change date function
function changeDate(date) {
    window.location.href = '<?= base_url('teacher/attendance') ?>?date=' + date;
}


</script>

<?= $this->endSection() ?>