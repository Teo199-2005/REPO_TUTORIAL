<?php
/**
 * Shared print stylesheet for the official school report documents
 * (Academic Report Card, Learner Development Report, SNED Progress Report
 * Card, Analytics and Nutrition exports).
 *
 * Rendered inside a <style> block by the report views, then each report adds
 * only its own table rules. Keeping the letterhead, section titles, signature
 * block and footer here is what makes the documents look like one family.
 *
 * A4 portrait, 12mm margins: content uses the full 186mm printable width.
 * `$pageSize` optionally overrides the @page size for wide exports (the
 * nutrition table passes 'A4 landscape'); every report card keeps the default.
 * Deliberately no `html { margin: 0 }` rule - Dompdf applies the @page margin
 * to the root frame, so resetting html's margin would push the tables into
 * the paper edge.
 */
?>
@page { size: <?= $pageSize ?? 'A4 portrait' ?>; margin: 12mm 12mm 12mm 12mm; }

body {
    margin: 0;
    padding: 0;
    font-family: "Times New Roman", Times, serif;
    font-size: 10pt;
    line-height: 1.22;
    color: #000;
    background: #fff;
}
table { border-collapse: collapse; }
img { border: 0; }

/* ---------------------------------------------------------------- letterhead
   Both marks are square PNGs, so one width/height pair gives them identical
   visual weight and they are never stretched; `vertical-align: middle` on
   every cell puts logos and text block on one horizontal axis.

   Sizing is in pixels, not mm: Dompdf ignores `mm` on an <img> and falls back
   to the image's natural size (the 2194px seal would render ~580mm tall and
   push the document onto extra pages). 112px at 96dpi = 29.6mm, inside the
   28-32mm band; the matching width/height attributes in the markup make the
   size independent of the stylesheet. */
table.letterhead { width: 100%; page-break-inside: avoid; }
table.letterhead td { border: 0; padding: 0; vertical-align: middle; }
td.logo-cell { width: 32mm; text-align: left; }
td.logo-cell.seal { text-align: right; }
/* display:block removes the inline baseline gap that otherwise nudges one
   logo a few pixels above the other. */
img.brand-logo { display: block; width: 112px; height: 112px; }
td.letter { text-align: center; padding: 0 1mm; }
div.uh { font-size: 10pt; line-height: 1.24; }
div.uh.deped { font-size: 11pt; font-weight: bold; }
div.rule { height: 0; margin: 1.6mm 0; border-bottom: 0.8pt solid #000; }
div.school-name {
    font-size: 16pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.6pt; line-height: 1.15;
}
div.report-title {
    font-size: 14pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.5pt; margin-top: 2.4mm; line-height: 1.15;
}
div.sy-line { font-size: 11pt; font-weight: bold; margin-top: 1.2mm; }

/* ------------------------------------------------------------ section title */
.section-title {
    font-size: 11.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt;
    margin: 3mm 0 1.6mm 0; padding-bottom: 0.7mm; border-bottom: 0.8pt solid #000;
    page-break-after: avoid;
}

/* -------------------------------------------------------------- signatures */
table.sign { width: 100%; margin-top: 4mm; page-break-inside: avoid; }
table.sign td { width: 50%; border: 0; padding: 0 2mm; text-align: center; vertical-align: top; }
.sign-label { font-size: 10pt; text-transform: uppercase; text-align: left; }
.sign-space { height: 12mm; }
.sign-line { margin: 0 4mm; border-bottom: 0.8pt solid #000; }
.sign-name { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 1.4mm; }
.sign-title { font-size: 10pt; margin-top: 0.3mm; }
.sign-rank { font-size: 9.5pt; }

/* ------------------------------------------------------------------ footer */
.foot {
    margin-top: 3mm; padding-top: 1mm; border-top: 0.5pt solid #000;
    text-align: center; font-size: 8pt; line-height: 1.25; page-break-inside: avoid;
}
