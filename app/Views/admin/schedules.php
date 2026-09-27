<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-calendar-week"></i> Section Schedules</h2>
    </div>

    <div class="row g-4 align-items-start">
        <!-- Left: grade groups (single column) -->
        <div class="col-lg-7 col-xl-8">
            <div class="d-flex flex-column gap-3">
    <?php foreach (grade_level_options() as $grade): ?>
        <?php 
        $gradeSections = array_filter($sections, fn($s) => $s['grade_level'] == $grade);
        if (empty($gradeSections)) continue;
        ?>
        
        <div>
            <div class="card schedules-grade-card">
                <div class="card-header bg-primary text-white py-2 schedules-grade-header"
                     role="button" tabindex="0"
                     data-bs-toggle="collapse" data-bs-target="#schedules-grade-<?= $grade ?>"
                     aria-expanded="false" aria-controls="schedules-grade-<?= $grade ?>">
                    <h6 class="mb-0">
                        <i class="bi bi-chevron-right schedules-grade-chevron me-1"></i>
                        <i class="bi bi-mortarboard"></i> <?= esc(grade_level_label((int) $grade)) ?>
                    </h6>
                </div>
                <div id="schedules-grade-<?= $grade ?>" class="collapse">
                <div class="card-body p-2">
                    <?php foreach ($gradeSections as $section): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= esc($section['section_name']) ?></strong><br>
                                    <small class="text-muted">
                                        <i class="bi bi-people"></i> <?= $section['current_enrollment'] ?>/<?= $section['max_capacity'] ?>
                                        <i class="bi bi-person-badge ms-2"></i> <?= esc($section['adviser_name'] ?? 'No Adviser') ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-primary" onclick="viewSchedule(<?= $section['id'] ?>, '<?= esc($section['section_name']) ?>', <?= $grade ?>)">
                                    <i class="bi bi-calendar-check me-1"></i>Manage
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div><!-- /.collapse -->
            </div>
        </div>
    <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: subtle info panel about section schedules -->
        <div class="col-lg-5 col-xl-4">
            <div class="card schedules-info-card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <div class="schedules-info-icon mx-auto mb-2">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                        <h6 class="fw-semibold mb-1">About Section Schedules</h6>
                        <p class="text-muted small mb-0">Class timetables for every section</p>
                    </div>
                    <p class="text-muted small mb-3">
                        Each section has its own weekly schedule of subjects. Open a grade level
                        below, pick a section, and manage its timetable — subjects, teachers,
                        days, time slots and rooms — all in one place.
                    </p>
                    <ul class="list-unstyled schedules-info-list small mb-0">
                        <li class="mb-2">
                            <i class="bi bi-calendar2-check text-primary me-2"></i>
                            Set the subject, teacher, day, time and room for every slot.
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-person-check text-primary me-2"></i>
                            Teachers and students see the schedule update instantly.
                        </li>
                        <li>
                            <i class="bi bi-shield-check text-primary me-2"></i>
                            Schedules apply to the current school year only.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Collapsible grade-level schedule groups */
.schedules-grade-header {
    cursor: pointer;
    user-select: none;
    transition: background-color .15s ease;
}
.schedules-grade-header:hover,
.schedules-grade-header:focus-visible {
    background-color: #0b5ed7;
}
.schedules-grade-header:focus-visible {
    outline: 2px solid #ffc107;
    outline-offset: -2px;
}
.schedules-grade-chevron {
    display: inline-block;
    transition: transform .2s ease;
}
.schedules-grade-header[aria-expanded="true"] .schedules-grade-chevron {
    transform: rotate(90deg);
}

/* Right-side info panel */
.schedules-info-card {
    border: 1px solid #e9ecef;
    border-radius: .75rem;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
    position: sticky;
    top: 1rem;
}
.schedules-info-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #0d6efd;
    background: #e7f1ff;
    border-radius: 50%;
}
.schedules-info-list li {
    display: flex;
    align-items: flex-start;
    color: #495057;
}
.schedules-info-list i {
    margin-top: .15rem;
}
</style>

<script>
function viewSchedule(sectionId, sectionName, gradeLevel) {
    window.location.href = `<?= base_url('admin/schedules/section/') ?>${sectionId}`;
}

// Keyboard support for the collapsible grade-level headers (Enter / Space)
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const header = e.target.closest('.schedules-grade-header');
    if (!header) return;
    e.preventDefault();
    header.click();
});
</script>

<?= $this->endSection() ?>
