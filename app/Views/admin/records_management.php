<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.grade-card {
  border: var(--hairline);
  border-radius: var(--radius-lg);
  background: var(--surface-tint);
  box-shadow: var(--shadow-md);
  margin-bottom: 1rem;
}
/* Grade headers sit on a deep, saturated bar, so their title and chevron are
   explicitly white. The platform heading rules default an undecorated heading
   to the dark heading colour, which would be invisible on that bar. */
.grade-card > .card-header h5,
.grade-card > .card-header h5 .bi,
.grade-card > .card-header .bi {
  color: #ffffff !important;
}
.section-item { background: #f8fafc; border: var(--hairline); border-radius: var(--radius-md); padding: 12px; margin-bottom: 8px; cursor: pointer; transition: background 0.2s ease, box-shadow 0.2s ease; }
.section-item:hover { background: #f1f5f9; box-shadow: var(--shadow-sm); }
.student-link { display: block; padding: 10px 15px; border-radius: var(--radius-sm); text-decoration: none; color: inherit; transition: background 0.2s ease; }
.student-link:hover { background: #eef2f7; color: #1e40af; }
.badge-count { font-size: 0.85rem; padding: 4px 10px; }
</style>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-2">Records Management</h1>
            <p class="text-muted mb-0">View and manage student report cards by grade level and section</p>
        </div>
        <div class="text-end">
            <label class="fw-bold d-block mb-1">School Year</label>
            <select name="year" class="form-select form-select-sm" style="min-width: 150px;" onchange="window.location.href='?year='+this.value">
                <?php if (empty($schoolYears)): ?>
                    <option value="<?= get_current_school_year() ?>"><?= get_current_school_year() ?></option>
                <?php else: ?>
                    <?php foreach ($schoolYears as $year): ?>
                        <option value="<?= $year['school_year'] ?>" <?= $year['school_year'] == $selectedYear ? 'selected' : '' ?>>
                            <?= $year['school_year'] ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

</div>

<?php if (empty($groupedRecords)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <h5 class="mt-3">No Records</h5>
            <p class="text-muted">No sections found for <?= $selectedYear ?></p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($groupedRecords as $gradeLevel => $sections): ?>
        <?php
        $colors = [
            0 => '#6d28d9',
            1 => '#1d4ed8',
            2 => '#1e40af',
            3 => '#0f2f66',
            4 => '#111f42',
            5 => '#15803d',
            6 => '#14532d',
        ];
        $bgColor = $colors[$gradeLevel] ?? '#0d6efd';
        ?>
        <div class="card grade-card mb-3">
                    <div class="card-header" style="cursor: pointer; background: <?= $bgColor ?>;" onclick="toggleGrade(<?= $gradeLevel ?>)">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white"><i class="bi bi-mortarboard me-2"></i><?= esc(grade_level_label((int) $gradeLevel)) ?></h5>
                            <i class="bi bi-chevron-down text-white" id="icon-grade-<?= $gradeLevel ?>"></i>
                        </div>
                    </div>
                    <div class="card-body" id="grade-<?= $gradeLevel ?>" style="display: none;">
                        <div class="mb-3">
                            <small class="text-muted"><i class="bi bi-folder2-open me-1"></i><?= count($sections) ?> section(s) available</small>
                            <p class="text-muted small mb-0 mt-1">Click a section to view student report cards</p>
                        </div>
                        <div class="list-group">
                            <?php foreach ($sections as $sectionName => $students): ?>
                                <a href="<?= base_url('admin/records/section/' . $gradeLevel . '/' . urlencode($sectionName) . '?year=' . $selectedYear) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-people me-2"></i><?= esc($sectionName) ?></span>
                                    <i class="bi bi-arrow-right-circle"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function toggleGrade(grade) {
    // Get all grade content divs
    const allGrades = document.querySelectorAll('[id^="grade-"]');
    const currentContent = document.getElementById('grade-' + grade);
    const currentIcon = document.getElementById('icon-grade-' + grade);
    
    // Close all grades
    allGrades.forEach(el => {
        if (el !== currentContent) {
            el.style.display = 'none';
        }
    });
    
    // Reset all icons
    document.querySelectorAll('[id^="icon-grade-"]').forEach(icon => {
        if (icon !== currentIcon) {
            icon.className = 'bi bi-chevron-down text-white';
        }
    });
    
    // Toggle current grade
    if (currentContent.style.display === 'none' || currentContent.style.display === '') {
        currentContent.style.display = 'block';
        currentIcon.className = 'bi bi-chevron-up text-white';
    } else {
        currentContent.style.display = 'none';
        currentIcon.className = 'bi bi-chevron-down text-white';
    }
}
</script>

<?= $this->endSection() ?>
