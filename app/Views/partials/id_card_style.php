<?php
/**
 * Student ID card — shared stylesheet for the FRONT and BACK faces.
 *
 * Included inside a <style> block by every consumer:
 *   - admin/id_card_clean.php  (flip card, admin preview)
 *   - admin/id_card_print.php  (front + back side-by-side print sheet)
 *   - student/id_card.php      (flip card, student's own card)
 *
 * Every selector is `idc-` prefixed so the dashboard stylesheet cannot bleed
 * into the card and the card cannot bleed into the dashboard. The @media print
 * block is mandatory: public/css/app.css forces `background: transparent` and
 * `color: #000` on every element while printing, so the card colors have to be
 * re-asserted here or the printed card comes out blank.
 */
?>
.idc-stage {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

/* --- Flip card (on-screen preview) --- */
.idc-flip {
  width: 2.125in;
  height: 3.375in;
  perspective: 1200px;
  cursor: pointer;
}

.idc-flip:focus-visible {
  outline: 3px solid #fbbf24;
  outline-offset: 4px;
}

.idc-flip__inner {
  position: relative;
  width: 100%;
  height: 100%;
  transform-style: preserve-3d;
  transition: transform 0.55s ease;
}

.idc-flip.is-flipped .idc-flip__inner {
  transform: rotateY(180deg);
}

.idc-flip .idc-face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}

.idc-flip .idc-face--back {
  transform: rotateY(180deg);
}

/* --- Print sheet (front + back side-by-side) --- */
.idc-pair {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.35in;
}

.idc-pair__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
}

.idc-pair__item .idc-face {
  width: 2.125in;
  height: 3.375in;
  flex: 0 0 auto;
}

.idc-pair__label {
  font: 700 8pt/1 'Times New Roman', Times, serif;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #6b7280;
}

.idc-hint {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0;
  text-align: center;
}

/* --- Card chrome shared by both faces --- */
.idc-face {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 3mm;
  overflow: hidden;
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  color: #fff;
  font-family: 'Times New Roman', Times, serif;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.idc-face::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 20%;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  z-index: 0;
}

.idc-watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 78%;
  transform: translate(-50%, -50%);
  opacity: 0.08;
  z-index: 1;
  pointer-events: none;
}

.idc-head {
  position: relative;
  z-index: 2;
  height: 20%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 6px 8px;
}

.idc-logo {
  width: 34px;
  height: 34px;
  background: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding: 2px;
}

.idc-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 50%;
}

.idc-school-name {
  font-size: 10.5px;
  font-weight: 700;
  color: #111827;
  line-height: 1.15;
  text-align: center;
}

.idc-tagline {
  font-size: 6.5px;
  font-weight: 700;
  color: #78350f;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-align: center;
  margin-top: 1px;
}

.idc-foot {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2;
  height: 10%;
  background: rgba(0, 0, 0, 0.2);
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2px 8px;
  font-size: 6.5px;
  line-height: 1.2;
  color: rgba(255, 255, 255, 0.85);
}

/* --- Front face --- */
.idc-body {
  position: relative;
  z-index: 2;
  height: 70%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 10px 10px 4px;
}

.idc-photo {
  width: 74px;
  height: 74px;
  border-radius: 8px;
  border: 3px solid #fff;
  object-fit: cover;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
}

.idc-no-photo {
  width: 74px;
  height: 74px;
  border-radius: 8px;
  border: 3px solid rgba(255, 255, 255, 0.85);
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.idc-name {
  margin-top: 8px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  line-height: 1.2;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.idc-meta {
  margin-top: 4px;
  font-size: 9px;
  line-height: 1.35;
  opacity: 0.95;
}

.idc-badges {
  margin-top: 7px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.idc-badge-role {
  background: #fbbf24;
  color: #1f2937;
  font-size: 6.5px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 999px;
}

.idc-lrn {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.45);
  font-size: 0.8125rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 6px;
}

/* --- Back face --- */
.idc-back-body {
  position: relative;
  z-index: 2;
  height: 70%;
  display: flex;
  flex-direction: column;
  padding: 8px 9px 6px;
}

.idc-emergency {
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 6px;
  padding: 6px 7px;
}

.idc-emergency__title {
  font-size: 7px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #fbbf24;
  margin-bottom: 4px;
}

.idc-emergency__row {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin-top: 2px;
}

.idc-emergency__label {
  flex: 0 0 32%;
  font-size: 5.8px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.75);
}

.idc-emergency__value {
  flex: 1 1 auto;
  font-size: 8.5px;
  font-weight: 700;
  color: #fff;
  word-break: break-word;
}

.idc-emergency__value--empty {
  font-weight: 400;
  font-style: italic;
  color: rgba(255, 255, 255, 0.65);
}

.idc-sig {
  margin-top: auto;
  text-align: center;
  padding-top: 6px;
}

.idc-sig__name {
  font-family: 'Times New Roman', Times, serif;
  font-size: 13px;
  font-style: italic;
  color: #fff;
  line-height: 1.2;
}

.idc-sig__line {
  width: 62%;
  height: 0;
  margin: 3px auto 2px;
  border-top: 1px solid rgba(255, 255, 255, 0.7);
}

.idc-sig__rank {
  font-size: 7.5px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #fbbf24;
}

/* --- Printing --- */
@media print {
  .idc-face,
  .idc-face * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .idc-face {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%) !important;
    box-shadow: none !important;
    page-break-inside: avoid;
    break-inside: avoid;
  }

  .idc-face::before {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%) !important;
  }

  .idc-logo { background: #fff !important; }
  .idc-photo,
  .idc-no-photo { background: rgba(255, 255, 255, 0.2) !important; border-color: #fff !important; }
  .idc-badge-role { background: #fbbf24 !important; }
  .idc-lrn,
  .idc-emergency { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.45) !important; }
  .idc-foot { background: rgba(0, 0, 0, 0.2) !important; border-top-color: #fbbf24 !important; }

  /* app.css prints `* { color: #000 !important; background: transparent !important }`:
     restore the card's own colors (generic first, specifics after, since every
     declaration above is !important and specificity ties resolve by order). */
  .idc-face,
  .idc-face * { color: #fff !important; }
  .idc-school-name { color: #111827 !important; }
  .idc-tagline { color: #78350f !important; }
  .idc-badge-role { color: #1f2937 !important; }
  .idc-foot,
  .idc-foot * { color: rgba(255, 255, 255, 0.9) !important; }
  .idc-emergency__title,
  .idc-sig__rank { color: #fbbf24 !important; }
  .idc-emergency__label { color: rgba(255, 255, 255, 0.8) !important; }
  .idc-emergency__value--empty { color: rgba(255, 255, 255, 0.7) !important; }
  .idc-name { text-shadow: none !important; }

  /* A flip card is a screen affordance only: when printing, lay both faces out
     side-by-side so one sheet carries the front and the back of the card. */
  .idc-flip {
    width: auto !important;
    height: auto !important;
    perspective: none !important;
    cursor: default !important;
  }

  .idc-flip__inner {
    display: flex !important;
    gap: 0.35in;
    width: auto !important;
    height: auto !important;
    transform: none !important;
    transform-style: flat !important;
  }

  .idc-flip .idc-face {
    position: relative !important;
    inset: auto !important;
    width: 2.125in !important;
    height: 3.375in !important;
    flex: 0 0 auto;
    transform: none !important;
    backface-visibility: visible !important;
    -webkit-backface-visibility: visible !important;
  }

  .idc-hint { display: none !important; }
}
