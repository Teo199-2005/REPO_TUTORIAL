<?php
/**
 * Shared DepEd letterhead: [School Logo] [header block] [DepEd Seal].
 *
 * Expects: $schoolName, $reportTitle, $schoolYear, $logoB64, $sealUri.
 * $region and $division are optional and fall back to school_region() /
 * school_division(). The 0.8pt rules run the full width of the centre block,
 * so they read as one band aligned with the logos on either side.
 */
?>
<table class="letterhead">
    <tr>
        <td class="logo-cell">
            <?php if (! empty($logoB64)): ?>
                <img class="brand-logo" width="112" height="112" src="data:image/png;base64,<?= esc($logoB64, 'attr') ?>" alt="School Logo">
            <?php endif; ?>
        </td>
        <td class="letter">
            <div class="uh">Republic of the Philippines</div>
            <div class="uh deped">Department of Education</div>
            <div class="uh"><?= esc($region ?? school_region()) ?></div>
            <div class="uh"><?= esc($division ?? school_division()) ?></div>
            <div class="rule"></div>
            <div class="school-name"><?= esc($schoolName) ?></div>
            <div class="rule"></div>
            <div class="report-title"><?= esc($reportTitle) ?></div>
            <div class="sy-line">School Year: <?= esc($schoolYear) ?></div>
        </td>
        <td class="logo-cell seal">
            <?php if (! empty($sealUri)): ?>
                <img class="brand-logo" width="112" height="112" src="<?= esc($sealUri, 'attr') ?>" alt="DepEd Official Seal">
            <?php endif; ?>
        </td>
    </tr>
</table>
