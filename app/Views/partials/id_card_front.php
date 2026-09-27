<?php
/**
 * FRONT face of the student ID card.
 *
 * Expects:
 *   $student  array  student record (photo/emergency_* fields are optional)
 *   $branding array  optional; resolved through id_card_branding() when absent
 */
$branding   = isset($branding) && is_array($branding) ? $branding : id_card_branding();
$photo      = trim((string) ($student['photo'] ?? ''));
$fullName   = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$grade      = grade_level_label((int) ($student['grade_level'] ?? 0));
$section    = trim((string) ($student['section_name'] ?? ''));
$schoolYear = trim((string) ($student['school_year'] ?? '')) ?: get_current_school_year();
$lrn        = trim((string) ($student['lrn'] ?? ''));
$dob        = ! empty($student['date_of_birth'])
    ? date('M d, Y', strtotime((string) $student['date_of_birth']))
    : null;
?>
<div class="idc-face idc-face--front">
    <img class="idc-watermark" src="<?= esc($branding['logo_url']) ?>" alt="" aria-hidden="true">

    <div class="idc-head">
        <span class="idc-logo">
            <img src="<?= esc($branding['logo_url']) ?>" alt="School logo">
        </span>
        <div>
            <div class="idc-school-name"><?= esc($branding['school_name']) ?></div>
            <div class="idc-tagline"><?= esc($branding['tagline']) ?></div>
        </div>
    </div>

    <div class="idc-body">
        <?php if ($photo !== ''): ?>
            <img class="idc-photo" src="<?= esc(base_url('files/' . $photo)) ?>" alt="Student photo">
        <?php else: ?>
            <div class="idc-no-photo" aria-hidden="true">&#128100;</div>
        <?php endif; ?>

        <div class="idc-name"><?= esc(strtoupper($fullName)) ?></div>

        <div class="idc-meta">
            <?= esc($grade) ?><?= $section !== '' ? ' &middot; ' . esc($section) : '' ?><br>
            S.Y. <?= esc($schoolYear) ?><br>
            <?php if ($dob !== null): ?>
                DOB: <?= esc($dob) ?>
            <?php endif; ?>
        </div>

        <div class="idc-badges">
            <span class="idc-badge-role">Student</span>
            <span class="idc-lrn"><?= $lrn !== '' ? esc($lrn) : 'LRN PENDING' ?></span>
        </div>
    </div>

    <div class="idc-foot"><?= esc($branding['address']) ?></div>
</div>
