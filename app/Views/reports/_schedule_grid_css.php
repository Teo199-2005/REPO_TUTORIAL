<?php
/**
 * Print stylesheet for the weekly timetable grid rendered by
 * reports/_schedule_grid, shared by the Student and Teacher schedule exports.
 *
 * Follows the reports/_report_css convention: the letterhead, rules and footer
 * come from there, and this file only adds what a six-column week needs.
 * A4 landscape - the Time column plus Monday..Friday do not fit portrait.
 *
 * The blocks inside a cell are plain text on white: no fill, no accent bar.
 * An earlier version drew a left border beside every block, which read as a
 * stray rule floating in the middle of the cell and was removed.
 */
?>
.report-info { font-size: 9pt; text-align: center; margin-top: 1mm; }

table.schedule-grid { width: 100%; border-collapse: collapse; margin-top: 3mm; }
table.schedule-grid th, table.schedule-grid td {
    border: 0.5pt solid #000; padding: 1.2mm; vertical-align: top; font-size: 8.5pt;
}
table.schedule-grid th {
    background: #34495E; color: #F4D03F; font-weight: bold; text-align: center;
    text-transform: uppercase; font-size: 8pt; letter-spacing: 0.3pt;
}
table.schedule-grid thead { display: table-header-group; }
table.schedule-grid tr { page-break-inside: avoid; }
td.time-col {
    width: 22mm; text-align: center; font-weight: bold; background: #F2F2F2;
    font-size: 8pt; white-space: nowrap;
}
td.empty { text-align: center; padding: 8mm; font-style: italic; }
div.block { padding-left: 0; }
div.block .subject { font-weight: bold; font-size: 9pt; }
div.block .line { font-size: 8pt; }
