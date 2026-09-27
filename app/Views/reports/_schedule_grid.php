<?php
/**
 * Weekly timetable grid shared by the Student and Teacher schedule PDF
 * exports, so both documents lay the same week out identically.
 *
 * Expects:
 *   $schedules   array<string, array<string, array>> day (lowercase) =>
 *                "start-end" => schedule row. Built by
 *                Student\Dashboard::schedule() and Teacher\Dashboard::schedule().
 *   $showSection bool  print the section name inside a block (teacher export).
 *   $showTeacher bool  print the teacher name inside a block.
 *
 * Only slots that actually carry a block are printed, so a nearly empty week
 * does not spend a page on blank rows, and the rows are keyed off each block's
 * own start/end (the two callers shape the slot key differently - 'H:i-H:i'
 * for a student, 'HH:MM:SS-HH:MM:SS' for a teacher).
 */
$days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
];

$showSection = $showSection ?? false;
$showTeacher = $showTeacher ?? true;

$clock = static function (?string $time): string {
    $time = trim((string) $time);

    return $time === '' ? '' : date('g:i A', strtotime($time));
};

$slots = [];

foreach ($days as $dayKey => $dayLabel) {
    foreach (($schedules[$dayKey] ?? []) as $slot => $row) {
        if (! is_array($row) || trim((string) ($row['subject_name'] ?? '')) === '') {
            continue;
        }

        $start = trim((string) ($row['start_time'] ?? ''));
        $end   = trim((string) ($row['end_time'] ?? ''));

        if ($start === '' || $end === '') {
            // Fall back to the slot key, which is always "start-end".
            $parts = explode('-', (string) $slot);
            $start = $parts[0] ?? '';
            $end   = $parts[1] ?? '';
        }

        $key = substr($start, 0, 5) . '-' . substr($end, 0, 5);

        $slots[$key] ??= ['start' => substr($start, 0, 5), 'end' => substr($end, 0, 5), 'cells' => []];
        $slots[$key]['cells'][$dayKey] = $row;
    }
}

uksort($slots, static fn ($a, $b): int => strcmp($a, $b));
?>
<table class="schedule-grid">
    <thead>
        <tr>
            <th class="time-col">Time</th>
            <?php foreach ($days as $dayLabel): ?>
                <th><?= esc($dayLabel) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if ($slots === []): ?>
            <tr><td colspan="6" class="empty">No schedule has been set up yet.</td></tr>
        <?php else: ?>
            <?php foreach ($slots as $slot): ?>
                <tr>
                    <td class="time-col"><?= esc($clock($slot['start'])) ?><br><?= esc($clock($slot['end'])) ?></td>
                    <?php foreach ($days as $dayKey => $dayLabel): ?>
                        <td>
                            <?php $block = $slot['cells'][$dayKey] ?? null; ?>
                            <?php if ($block === null): ?>
                                &nbsp;
                            <?php else: ?>
                                <div class="block">
                                    <div class="subject"><?= esc($block['subject_name']) ?></div>
                                    <?php if ($showTeacher && ! empty($block['teacher_name'])): ?>
                                        <div class="line"><?= esc($block['teacher_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($showSection && ! empty($block['section_name'])): ?>
                                        <div class="line">Section: <?= esc($block['section_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if (! empty($block['room'])): ?>
                                        <div class="line">Room: <?= esc($block['room']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
