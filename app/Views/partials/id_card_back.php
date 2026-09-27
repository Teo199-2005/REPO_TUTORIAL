<?php
/**
 * BACK face of the student ID card: emergency contact on top, the current
 * principal's signature block below.
 *
 * Expects:
 *   $student  array  student record (emergency_contact_* fields are optional —
 *                   students without them print "Not yet provided" instead of
 *                   an empty box, so it is obvious the data is missing)
 *   $branding array  optional; resolved through id_card_branding() when absent
 */
$branding = isset($branding) && is_array($branding) ? $branding : id_card_branding();

$emergency = [
    'Name' => trim((string) ($student['emergency_contact_name'] ?? '')),
    'Mobile' => trim((string) ($student['emergency_contact_number'] ?? '')),
    'Relation' => trim((string) ($student['emergency_contact_relationship'] ?? '')),
];
?>
<div class="idc-face idc-face--back">
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

    <div class="idc-back-body">
        <div class="idc-emergency">
            <div class="idc-emergency__title">In case of emergency</div>
            <?php foreach ($emergency as $label => $value): ?>
                <div class="idc-emergency__row">
                    <span class="idc-emergency__label"><?= esc($label) ?></span>
                    <span class="idc-emergency__value<?= $value === '' ? ' idc-emergency__value--empty' : '' ?>">
                        <?= $value !== '' ? esc($value) : 'Not yet provided' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="idc-sig">
            <div class="idc-sig__name"><?= esc($branding['principal_name']) ?></div>
            <div class="idc-sig__line"></div>
            <div class="idc-sig__rank"><?= esc($branding['principal_rank']) ?></div>
        </div>
    </div>

    <div class="idc-foot">If found, please return to the school administration.</div>
</div>
