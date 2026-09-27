<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<style>
<?= view('partials/password_requirements_style') ?>

.register-container {
  min-height: calc(100vh - 200px);
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  position: relative;
  margin: 0 -15px;
}

.register-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%233b82f6" stroke-width="0.5" opacity="0.2"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 0.9;
}

.register-container::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url('<?= asset_url('LPHS2.png') ?>');
  background-repeat: repeat;
  background-size: 110px 110px;
  background-position: center center;
  opacity: 0.095;
  filter: grayscale(0.7) saturate(0.8);
  pointer-events: none;
}

.register-card {
  background: rgba(30, 64, 175, 0.95);
  backdrop-filter: blur(25px);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 20px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(59, 130, 246, 0.2);
  width: 100%;
  max-width: 800px;
  overflow: hidden;
  position: relative;
  z-index: 1;
}

.register-header {
  text-align: center;
  padding: 3rem 2.5rem 2rem;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
  border-bottom: 1px solid rgba(59, 130, 246, 0.1);
}

.register-logo {
  width: 78px;
  height: 78px;
  object-fit: contain;
  border-radius: 50%;
  margin: 0 auto 1rem;
  display: block;
  background: rgba(255, 255, 255, 0.95);
  border: 2px solid rgba(255, 255, 255, 0.7);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.35);
}

.register-title {
  font-size: 2rem;
  font-weight: 700;
  font-family: 'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 0.5rem;
  letter-spacing: 0;
}

.register-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.95rem;
  font-weight: 400;
  margin: 0;
}

.register-form {
  padding: 1.5rem 2rem 2rem;
}

.form-control, .form-select {
  border: var(--hairline);
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  font-size: 0.85rem;
  background: #f8fafc;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-weight: 400;
  color: #000000;
}

.form-control:focus, .form-select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  background: white;
}

.form-label {
  color: white;
  font-weight: 700;
  font-size: 0.8rem;
  margin-bottom: 0.25rem;
}

/* Scoped to .register-card on purpose -- left as a bare `.section-title` this
   rule silently loses. app.css styles the same class as
     .section-title:not([class*="text-"]) { color: var(--color-heading) }
   and an attribute selector inside :not() counts toward specificity, so that
   rule is (0,2,0) -- heavier than a bare class at (0,1,0) -- and it loads from
   the head, before this block. It therefore won, and the step headings rendered
   #0f172a on this page's dark navy card and all but disappeared. Scoping here
   restores (0,2,0) and, being later in the cascade, takes the tie.
   teacher_register.php carries the identical fix. */
.register-card .section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.75rem;
  margin-top: 0;
  padding-bottom: 0.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
}

.section-title::before {
  display: none;
}

.section-title:first-of-type {
  margin-top: 0;
}

/* Text colour is dark navy, not white. The button is an amber gradient, and
   white on that averages out at 2.35:1 -- well under the 4.5:1 needed for
   14px bold text, so the label was effectively unreadable. Navy on the same
   amber reaches 4.40:1, and it is also what the sibling dialog already does
   (mascot.css styles the help modal's amber button with navy type), so the two
   now read as one family. */
.register-btn {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
  border: none;
  border-radius: 10px;
  color: #1e3a8a;
  font-weight: 700;
  font-size: 0.9rem;
  letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.register-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 25px rgba(251, 191, 36, 0.4);
  background: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #dc2626 100%);
}

/* The "No middle name" chip carries Bootstrap's .input-group-text, which
   ui-depth.css folds into the light form-control gradient with !important. On
   this page that is wrong: the chip sits on the dark navy card and its own
   rules keep the label white, so the white chip left white text on a white
   background -- 1.01:1, invisible. Restored to a translucent white chip, which
   both keeps it legible (white on the tinted navy lands near 7:1) and still
   reads as part of the input group it is attached to. */
.register-card .no-middle-name-check {
  background: rgba(255, 255, 255, 0.12) !important;
  background-color: rgba(255, 255, 255, 0.12) !important;
  background-image: none !important;
  border-color: rgba(255, 255, 255, 0.3) !important;
  color: #fff;
}

.btn-outline-secondary {
  border: var(--hairline);
  border-radius: 12px;
  padding: 1rem 2rem;
  font-weight: 700;
  transition: all 0.3s ease;
}

.btn-outline-secondary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.alert {
  border: none;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
}

.alert-danger {
  background: #fef2f2;
  color: #dc2626;
}

/* 0.75 rather than 0.6. At 0.6 these sat at 4.06:1 against the navy card --
   just under the 4.5:1 floor, for text that carries the required-field marker
   and the field guidance, which is exactly the copy a parent is squinting at. */
.form-text, .text-muted {
  color: rgba(255, 255, 255, 0.75) !important;
  font-size: 0.8125rem;
}

/* Prompt below the password field telling the applicant to type the password
   they want, so it is clear the account is created with this one. */
.password-prompt {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0.5rem 0;
  padding: 0.45rem 0.6rem;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.8125rem;
  line-height: 1.3;
}

.password-prompt .bi {
  color: #fbbf24;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.password-prompt strong {
  color: #fff;
  font-weight: 700;
}

/* ===== Conditional follow-ups =====
   The one full-width band that holds every field a selector can reveal.

   These used to be nested inside the column that triggered them. That is what
   made the step jump: revealing a field grew one third of the row to several
   times the height of its neighbours, and the religion "Other" case was worse
   still because its column was forced to 100% width, reflowing the entire row.

   Here each group is a horizontal grid, so a revealed field adds one short band
   below the row and the columns above it never change height. --single keeps
   the free-text "Other" boxes at a readable width instead of stretching them
   across the whole form. */
.form-extras__group {
  display: none;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0.75rem 1rem;
  margin-bottom: 0.85rem;
  padding: 0.85rem 1rem;
  border: 1px dashed rgba(147, 197, 253, 0.45);
  border-radius: 8px;
  background: rgba(59, 130, 246, 0.10);
}

.form-extras__group.is-visible {
  display: grid;
  animation: transfereeReveal 0.2s ease-out;
}

.form-extras__group--single {
  grid-template-columns: minmax(240px, 420px);
}

.form-extras__item {
  min-width: 0;
}

.form-extras__item .form-label {
  margin-bottom: 0.15rem;
}

.form-extras__item .form-text {
  font-size: 0.8125rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.15rem;
}

/* One revealed group at a time on a phone, so the fields stay reachable instead
   of being squeezed into two unreadable columns. */
@media (max-width: 575.98px) {
  .form-extras__group,
  .form-extras__group--single {
    grid-template-columns: 1fr;
  }
}

@keyframes transfereeReveal {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: none; }
}

.form-step {
  display: none;
}

.form-step.active {
  display: block;
}

.step-navigation {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1rem;
  /* Breathing room so the Next button is not flush against the card edge. */
  padding-right: 0.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.step-navigation .right-buttons {
  display: flex;
  gap: 10px;
  /* space-between alone is not enough here. #prevBtn is display:none on step 1,
     and a lone flex item under space-between lands at the START, which pinned
     Next to the left corner. margin-left:auto holds the button group against
     the right edge whether or not Previous is currently on screen. */
  margin-left: auto;
}

.btn-step {
  padding: 0.6rem 1.2rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 8px;
  background: transparent;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
  transition: all 0.3s ease;
}

.btn-step:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.step-indicator {
  position: relative;
  display: block;
  text-align: center;
  /* Symmetric inset, so the centred text stays optically centred against the
     card rather than being nudged right by the help button on one side only. */
  padding: 0 2.75rem;
  margin-bottom: 1.5rem;
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.85rem;
}

/* The help button is taken out of flow rather than sitting in the row beside the
   text. As a flex sibling under space-between it pushed "Step 1 of 4..." hard
   left, and no amount of flex alignment centres one item while pinning another
   to the opposite edge. */
.step-indicator .reg-help-open {
  position: absolute;
  top: 50%;
  right: 0;
  transform: translateY(-50%);
}

.custom-alert {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
  z-index: 1000;
  max-width: 400px;
  width: 90%;
}

.alert-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

.alert-title {
  color: #dc2626;
  font-weight: 700;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.alert-message {
  color: #374151;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.alert-close {
  background: #dc2626;
  color: white;
  border: none;
  padding: 0.5rem 1.5rem;
  border-radius: 6px;
  font-weight: 700;
  cursor: pointer;
  float: right;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* ===== MOBILE RESPONSIVE STYLES ===== */

/* Large tablets */
@media (max-width: 991.98px) {
  .register-header {
    padding: 2.5rem 2rem 1.5rem;
  }
  
  .register-title {
    font-size: 1.75rem;
  }
  
  .register-logo {
    width: 70px;
    height: 70px;
  }
  
  .register-form {
    padding: 1.25rem 1.5rem 1.5rem;
  }
}

/* Medium devices (tablets, 768px and below) */
@media (max-width: 767.98px) {
  .register-container {
    padding: 1.5rem 0.75rem;
  }
  
  .register-card {
    border-radius: 16px;
    max-width: 100%;
  }
  
  .register-header {
    padding: 2rem 1.5rem 1.25rem;
  }
  
  .register-logo {
    width: 60px;
    height: 60px;
    margin-bottom: 0.75rem;
  }
  
  .register-title {
    font-size: 1.5rem;
  }
  
  .register-subtitle {
    font-size: 0.85rem;
  }
  
  .register-form {
    padding: 1rem 1.25rem 1.25rem;
  }
  
  .form-control, .form-select {
    font-size: 16px; /* Prevents zoom on iOS */
    padding: 0.5rem 0.625rem;
  }
  
  .form-label {
    font-size: 0.78rem;
  }
  
  .section-title {
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
  }
  
  .step-indicator {
    font-size: 0.8rem;
    margin-bottom: 1rem;
  }
  
  /* 2-column grid for tablets */
  .register-form .row > [class*="col-"] {
    margin-bottom: 0.5rem;
  }
  
  .register-form .row.g-2 {
    --bs-gutter-y: 0.5rem;
  }
  
  .register-form .row.g-3 {
    --bs-gutter-y: 0.5rem;
  }

  /* Password toggle buttons on register */
  .register-form .position-relative button {
    right: 4px !important;
    padding: 6px !important;
    min-width: 36px !important;
    min-height: 36px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }
}

/* Small phones */
@media (max-width: 575.98px) {
  .register-container {
    padding: 1rem 0.5rem;
  }
  
  .register-header {
    padding: 1.5rem 1rem 1rem;
  }
  
  .register-logo {
    width: 52px;
    height: 52px;
    margin-bottom: 0.5rem;
  }
  
  .register-title {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
  }
  
  .register-subtitle {
    font-size: 0.8125rem;
  }
  
  .register-form {
    padding: 0.75rem 0.75rem 1rem;
  }
  
  /* 2x2x1 grid: 2 columns per row, last row full width */
  .register-form .row > .col-md-3,
  .register-form .row > .col-md-4,
  .register-form .row > .col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding-left: 4px;
    padding-right: 4px;
  }
  
  /* Full width items on mobile */
  .register-form .row > .col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
    padding-left: 4px;
    padding-right: 4px;
  }
  
  /* Ensure Address textarea and other full-width elements span full width */
  .register-form .row > .col-md-12:not([class*="col-sm-"]) {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  /* The religion column is deliberately NOT widened when its "Other" box opens.
     Forcing it to 100% used to reflow the whole row and shove Nationality onto a
     line of its own. The companion box now lives in .form-extras below the row,
     so this column keeps its 1/3 width whatever is selected. */

  .form-control, .form-select {
    font-size: 16px; /* Prevents zoom on iOS */
    padding: 0.45rem 0.5rem;
    border-radius: 6px;
  }
  
  .form-label {
    font-size: 0.8125rem;
    margin-bottom: 0.15rem;
  }
  
  .section-title {
    font-size: 0.85rem;
    margin-bottom: 0.35rem;
    padding-bottom: 0.15rem;
  }
  
  .step-indicator {
    font-size: 0.8125rem;
    margin-bottom: 0.75rem;
  }
  
  .register-form .row {
    margin-left: -4px;
    margin-right: -4px;
  }
  
  /* Fix row gap */
  .register-form .row.g-2 {
    --bs-gutter-x: 8px;
    --bs-gutter-y: 6px;
  }
  
  .register-form .row.g-3 {
    --bs-gutter-x: 8px;
    --bs-gutter-y: 6px;
  }
  
  .step-navigation {
    margin-top: 1rem;
    padding-top: 0.75rem;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .step-navigation .right-buttons {
    width: 100%;
    justify-content: center;
    /* Stacked full-width on phones, so the desktop right-alignment is moot. */
    margin-left: 0;
  }
  
  .btn-step {
    padding: 0.5rem 0.875rem;
    font-size: 0.8rem;
  }
  
  .register-btn {
    padding: 0.625rem 1rem;
    font-size: 0.85rem;
    min-height: 42px;
    -webkit-tap-highlight-color: transparent;
  }
  
  .alert {
    padding: 0.625rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    border-radius: 8px;
  }
  
  .alert ul {
    padding-left: 1.25rem;
    margin-bottom: 0;
  }
  
  .custom-alert {
    padding: 1.25rem;
    width: 88%;
  }
  
  .form-text, .text-muted {
    font-size: 0.7rem;
  }
  
  .step-navigation #prevBtn {
    width: 100%;
  }
}

/* Very small phones (< 360px) */
@media (max-width: 359px) {
  .register-header {
    padding: 1rem 0.75rem 0.75rem;
  }
  
  .register-logo {
    width: 44px;
    height: 44px;
  }
  
  .register-title {
    font-size: 1.1rem;
  }
  
  .register-form {
    padding: 0.5rem 0.5rem 0.75rem;
  }
  
  .register-form .row > .col-md-3,
  .register-form .row > .col-md-4,
  .register-form .row > .col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .form-control, .form-select {
    padding: 0.375rem 0.5rem;
    font-size: 16px;
  }
  
  .form-label {
    font-size: 0.7rem;
  }
  
  .section-title {
    font-size: 0.8rem;
  }
}

/* Landscape mode on small phones */
@media (max-height: 500px) and (orientation: landscape) {
  .register-container {
    min-height: auto;
    padding: 0.75rem 0.5rem;
  }
  
  .register-header {
    padding: 1rem 1rem 0.75rem;
  }
  
  .register-logo {
    width: 40px;
    height: 40px;
    margin-bottom: 0.25rem;
  }
  
  .register-title {
    font-size: 1.1rem;
    margin-bottom: 0.15rem;
  }
  
  .register-subtitle {
    font-size: 0.7rem;
  }
  
  .register-form {
    padding: 0.5rem 0.75rem 0.75rem;
  }
  
  .form-control, .form-select {
    padding: 0.35rem 0.5rem;
  }
  
  .form-label {
    font-size: 0.7rem;
  }
}


/* ===== "No middle name" checkbox =====

   An append on the input itself, not a row of its own.

   Under the input it added a line and made the whole name row taller. Beside
   the label it overflowed the column and collided with "Last Name", because
   "Middle Name *" is already close to the full width of a col-md-3 by itself at
   normal text sizes, so there was never room for both on one line. Folding the
   checkbox into the field keeps it attached to the control it actually governs,
   costs no height, and cannot spill into a neighbouring column at any width. */
.no-middle-name-check {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.8125rem;
  line-height: 1.2;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.25);
}

.no-middle-name-check input {
  width: auto;
  margin: 0;
  flex-shrink: 0;
  cursor: pointer;
}

.no-middle-name-check:hover {
  color: #fff;
}

.no-middle-name-check .form-text {
  font-size: 0.8125rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.15rem;
}

/* ===== AGE INDICATOR (floating above Date of Birth) =====
   It used to sit in the normal flow underneath the field, so every time it
   appeared -- valid date or not -- it pushed the rest of the row down and the
   form visibly jumped. It is absolutely positioned above the field now: it
   overlays whatever is above it and the page height never changes.

   The variant colour is carried by --age-bg so the little tail underneath can
   match the body without a second rule per state. */
.dob-field {
  position: relative;
}

.age-indicator {
  --age-bg: #f8fafc;
  position: absolute;
  left: 0;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 30;
  display: flex;
  align-items: baseline;
  gap: 0.3rem;
  flex-wrap: wrap;
  margin: 0;
  padding: 0.4rem 0.6rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: var(--age-bg);
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.35);
  font-size: 0.7rem;
  line-height: 1.35;
  animation: ageIndicatorPop 0.18s ease-out;
}

@keyframes ageIndicatorPop {
  from { opacity: 0; transform: translateY(4px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Tail pointing back down at the field it describes. */
.age-indicator::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 1rem;
  border: 6px solid transparent;
}

.age-indicator[hidden] {
  display: none;
}

.age-indicator .age-indicator-age {
  font-weight: 700;
}

.age-indicator .age-indicator-status {
  color: #64748b;
}

.age-indicator.is-meets {
  --age-bg: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.age-indicator.is-warning {
  --age-bg: #fffbeb;
  border-color: #fde68a;
  color: #92400e;
}

.age-indicator.is-blocked,
.age-indicator.is-invalid {
  --age-bg: #fef2f2;
  border-color: #fecaca;
  color: #991b1b;
}

.age-indicator.is-meets .age-indicator-status,
.age-indicator.is-warning .age-indicator-status,
.age-indicator.is-blocked .age-indicator-status,
.age-indicator.is-invalid .age-indicator-status {
  color: inherit;
  opacity: 0.85;
}

/* ===== AGE REQUIREMENT CONFIRMATION MODAL ===== */
.age-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(2, 6, 23, 0.6);
  backdrop-filter: blur(3px);
  z-index: 1000;
}

.age-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 92%;
  max-width: 520px;
  max-height: 92vh;
  overflow-y: auto;
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 25px 60px rgba(2, 6, 23, 0.45);
  z-index: 1001;
  animation: ageModalIn 0.2s ease-out;
}

@keyframes ageModalIn {
  from { opacity: 0; transform: translate(-50%, -46%) scale(0.97); }
  to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}

.age-modal-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
  color: #ffffff;
  overflow: hidden;
}

/* School seal watermarked inside the branded header. */
.age-modal-header::after {
  content: '';
  position: absolute;
  top: 50%;
  right: -20px;
  width: 140px;
  height: 140px;
  transform: translateY(-50%);
  background: url('<?= asset_url('LPHS2.png') ?>') no-repeat center center / contain;
  opacity: 0.16;
  filter: grayscale(0.3);
  pointer-events: none;
}

.age-modal-logo {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: contain;
  background: rgba(255, 255, 255, 0.92);
  border: 2px solid rgba(255, 255, 255, 0.7);
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.age-modal-header-text {
  position: relative;
  z-index: 1;
}

.age-modal-header-title {
  font-weight: 700;
  font-size: 0.95rem;
  line-height: 1.2;
}

.age-modal-header-sub {
  font-size: 0.7rem;
  opacity: 0.85;
}

.age-modal-body {
  position: relative;
  padding: 1.1rem 1.25rem 0.5rem;
}

/* Ghosted seal behind the modal content. */
.age-modal-body::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 260px;
  height: 260px;
  transform: translate(-50%, -50%);
  background: url('<?= asset_url('LPHS2.png') ?>') no-repeat center center / contain;
  opacity: 0.055;
  filter: grayscale(0.7) saturate(0.8);
  pointer-events: none;
}

.age-modal-body > * {
  position: relative;
  z-index: 1;
}

.age-modal-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: #b45309;
  margin-bottom: 0.75rem;
}

.age-modal.is-blocked .age-modal-title {
  color: #b91c1c;
}

.age-modal-message {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 0.75rem 0.9rem;
  font-size: 0.88rem;
  color: #374151;
  line-height: 1.5;
  margin-bottom: 1rem;
}

.age-modal.is-blocked .age-modal-message {
  background: #fef2f2;
  border-color: #fecaca;
  color: #7f1d1d;
}

.age-modal-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.age-modal-chip {
  flex: 1 1 120px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.45rem 0.6rem;
  text-align: center;
}

.age-modal-chip span {
  display: block;
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}

.age-modal-chip strong {
  font-size: 0.85rem;
  color: #0f172a;
}

.age-modal-table-title {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #334155;
  margin-bottom: 0.4rem;
}

.age-ref-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
  margin-bottom: 0.5rem;
}

.age-ref-table th,
.age-ref-table td {
  padding: 0.3rem 0.5rem;
  border: 1px solid #e2e8f0;
  text-align: left;
}

.age-ref-table th {
  background: #f1f5f9;
  color: #334155;
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.age-ref-table tr.is-selected td {
  background: #dbeafe;
  font-weight: 700;
  color: #1e3a8a;
}

.age-modal-note {
  font-size: 0.7rem;
  color: #64748b;
  font-style: italic;
  margin-bottom: 0.5rem;
}

.age-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem 0.5rem;
}

.age-modal-btn {
  border: none;
  border-radius: 8px;
  padding: 0.55rem 1.2rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: filter 0.15s ease, background 0.15s ease;
}

.age-modal-btn--yes {
  background: #16a34a;
  color: #ffffff;
}

.age-modal-btn--yes:hover {
  filter: brightness(1.08);
}

.age-modal-btn--no,
.age-modal-btn--ok {
  background: #e2e8f0;
  color: #334155;
}

.age-modal-btn--no:hover,
.age-modal-btn--ok:hover {
  background: #cbd5e1;
}

.age-modal-brand {
  padding: 0.45rem 1.25rem 0.7rem;
  text-align: center;
  font-size: 0.8125rem;
  color: #94a3b8;
  border-top: 1px solid #f1f5f9;
  padding-top: 0.5rem;
}

@media (max-width: 575.98px) {
  .age-modal {
    max-width: 100%;
  }

  .age-modal-header {
    padding: 0.75rem 0.9rem;
  }

  .age-modal-logo {
    width: 40px;
    height: 40px;
  }

  .age-modal-header-title {
    font-size: 0.85rem;
  }

  .age-modal-body {
    padding: 0.9rem 0.9rem 0.4rem;
  }

  .age-modal-message {
    font-size: 0.82rem;
  }

  .age-modal-footer {
    padding: 0.6rem 0.9rem 0.4rem;
  }

  .age-modal-btn {
    flex: 1 1 auto;
    padding: 0.5rem 0.8rem;
  }
}

/* Ensure password toggle buttons inside register are clickable */
.register-form .position-relative {
  position: relative !important;
}

.register-form .position-relative button {
  position: absolute !important;
  cursor: pointer !important;
  z-index: 5 !important;
}

/* Ensure password fields have padding for toggle icon */
.register-form input[type="password"],
.register-form input[id^="password"] {
  padding-right: 40px !important;
}
</style>

<div class="register-container">


  <div class="register-card">
    <div class="register-header">
      <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="register-logo">
      <h1 class="register-title">Student Registration</h1>
      <p class="register-subtitle">Register for enrollment at Cauayan South Central School</p>
    </div>

    <div class="register-form">
        <?php if (session()->getFlashdata('error')): ?>
          <div class="alert alert-danger">
            <?= session()->getFlashdata('error') ?>
          </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('errors')): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        
        <?php 
        $errorStep = session()->getFlashdata('error_step') ?? 1;

        // Religion: a selector of the ten most populated Philippine
        // affiliations plus an "Other" choice that reveals a free-text box.
        // A stored/legacy value that is not on the list (or text previously
        // typed under "Other") reopens that box, so a redisplayed form never
        // silently loses the answer.
        $religionOptions  = religion_options();
        $religionOld      = old('religion', '', false);
        $religionValue    = is_string($religionOld) ? trim($religionOld) : '';
        $religionIsOther  = $religionValue !== '' && ! in_array($religionValue, $religionOptions, true);

        // Nationality: same shape as religion. "A stored/legacy value that is not
        // on the list" is what reopens the box, so after normalisation has put the
        // applicant's typed text into old('nationality') the companion field is
        // still shown and still filled in.
        $nationalityOptions = nationality_options();
        $nationalityOld     = old('nationality', '', false);
        $nationalityValue   = is_string($nationalityOld) ? trim($nationalityOld) : '';
        $nationalityIsOther = $nationalityValue !== ''
            && ! in_array($nationalityValue, $nationalityOptions, true);

        // Student Type: transferees additionally declare the school they came
        // from and the school year they last attended it. A redisplayed form
        // (server error, or the applicant switching back) reopens those boxes.
        $studentTypeOld   = old('student_type', '', false);
        $studentTypeValue = is_string($studentTypeOld) ? trim($studentTypeOld) : '';
        $isTransferee     = $studentTypeValue === 'Transferee';
        $transfereeYears  = previous_school_year_choices();
        ?>

        <form method="post" action="<?= base_url('register') ?>" id="registrationForm" novalidate>
          <?= csrf_field() ?>

          <?php /* Reachable for the whole visit, not only on the one-time popup: the
                   people who most need this guidance are the least likely to notice a
                   dialog that has already been dismissed for this session.

                   It sits beside the step counter rather than in the page header.
                   As a long labelled button in the header it pushed the form down
                   and read as a second call to action competing with registering
                   itself; as an icon here it is a quiet way out for anyone stuck. */ ?>
          <div class="step-indicator">
            <span id="stepText">Step 1 of 4: Personal Information</span>
            <button type="button" class="reg-help-open" data-bs-toggle="modal"
                    data-bs-target="#registrationHelpModal"
                    aria-label="Need help? Guidance for parents and teachers"
                    title="Need help? Guidance for parents and teachers">
              <i class="bi bi-question-circle" aria-hidden="true"></i>
            </button>
          </div>

          <!-- Step 1: Personal Information -->
          <div class="form-step active" id="step1">
            <h5 class="section-title">Personal Information</h5>
          <div class="row g-2">
            <div class="col-md-3 col-6">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" name="first_name" value="<?= old('first_name') ?>" autocomplete="given-name" oninput="validateNameField(this)" required />
            </div>
            <?php
            // Applicants without a middle name tick the box; the field is then
            // cleared and left unrequired. A supplied middle name must be at
            // least two characters (a lone letter is rejected).
            $noMiddleName = old(NO_MIDDLE_NAME_POST_KEY) === no_middle_name_rule();
            $middleNameValue = $noMiddleName ? '' : old('middle_name');
            ?>
            <div class="col-md-4 col-6">
              <label class="form-label" for="middle_name">Middle Name *</label>
              <?php /* The checkbox is an append on the input, not a row of its own
                       and not a second item on the label line. */ ?>
              <div class="input-group">
                <input type="text" class="form-control" name="middle_name" id="middle_name" value="<?= esc($middleNameValue) ?>" autocomplete="additional-name" oninput="validateNameField(this)" minlength="2" <?= $noMiddleName ? 'disabled' : 'required' ?> />
                <label class="input-group-text no-middle-name-check" for="no_middle_name">
                  <input type="checkbox" name="<?= NO_MIDDLE_NAME_POST_KEY ?>" id="no_middle_name" value="<?= no_middle_name_rule() ?>" onchange="toggleNoMiddleName()" <?= $noMiddleName ? 'checked' : '' ?>>
                  <span>No middle name</span>
                </label>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" name="last_name" value="<?= old('last_name') ?>" autocomplete="family-name" oninput="validateNameField(this)" required />
            </div>
            <div class="col-md-2 col-6">
              <label class="form-label">Suffix</label>
              <select class="form-select" name="suffix" autocomplete="honorific-suffix">
                <option value="">None</option>
                <option value="Jr." <?= old('suffix') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                <option value="Sr." <?= old('suffix') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                <option value="II" <?= old('suffix') === 'II' ? 'selected' : '' ?>>II</option>
                <option value="III" <?= old('suffix') === 'III' ? 'selected' : '' ?>>III</option>
                <option value="IV" <?= old('suffix') === 'IV' ? 'selected' : '' ?>>IV</option>
                <option value="V" <?= old('suffix') === 'V' ? 'selected' : '' ?>>V</option>
              </select>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Gender *</label>
              <select class="form-select" name="gender" required>
                <option value="">Select</option>
                <option value="Male" <?= old('gender') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= old('gender') === 'Female' ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
            <div class="col-md-3 col-6 dob-field">
              <label class="form-label" for="date_of_birth">Date of Birth *</label>
              <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= old('date_of_birth') ?>" required />
              <div class="age-indicator" id="ageIndicator" hidden>
                <i class="bi bi-person-bounding-box age-indicator-icon" aria-hidden="true"></i>
                <span class="age-indicator-age" id="ageIndicatorAge"></span>
                <span class="age-indicator-status" id="ageIndicatorStatus"></span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Grade Level *</label>
              <select class="form-select" name="grade_level" id="grade_level" required>
                <option value="">Select</option>
                <?php foreach (grade_level_options() as $g): ?>
                  <option value="<?= $g ?>" <?= old('grade_level') === (string) $g ? 'selected' : '' ?>><?= esc(grade_level_label($g)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">LRN *</label>
              <input type="text" class="form-control" name="lrn" value="<?= old('lrn') ?>" placeholder="e.g. 123456789012" maxlength="12" pattern="[0-9]{12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)" required />
            </div>
            <div class="col-md-4 col-6" id="studentTypeField">
              <label class="form-label" for="student_type">Student Type *</label>
              <select class="form-select" name="student_type" id="student_type" required onchange="toggleTransfereeFields()">
                <option value="">Select</option>
                <option value="New Student" <?= old('student_type') === 'New Student' ? 'selected' : '' ?>>New Student</option>
                <option value="Transferee" <?= $isTransferee ? 'selected' : '' ?>>Transferee</option>
                <option value="Old Student" <?= old('student_type') === 'Old Student' ? 'selected' : '' ?>>Old Student</option>
              </select>
            </div>
            <div class="col-md-4 col-6" id="nationalityField">
              <label class="form-label" for="nationality">Nationality *</label>
              <select class="form-select" name="nationality" id="nationality" required onchange="toggleNationalityOther()">
                <option value="">Select nationality</option>
                <?php foreach ($nationalityOptions as $nationality): ?>
                  <option value="<?= esc($nationality) ?>" <?= old('nationality', 'Filipino') === $nationality ? 'selected' : '' ?>><?= esc($nationality) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 col-6" id="religionField">
              <label class="form-label" for="religion">Religion *</label>
              <select class="form-select" name="religion" id="religion" required onchange="toggleReligionOther()">
                <option value="">Select religion</option>
                <?php foreach ($religionOptions as $religionOption): ?>
                  <option value="<?= esc($religionOption) ?>" <?= $religionValue === $religionOption ? 'selected' : '' ?>><?= esc($religionOption) ?></option>
                <?php endforeach; ?>
                <option value="<?= esc(religion_other_option()) ?>" <?= $religionIsOther ? 'selected' : '' ?>>Other (please specify)</option>
              </select>
            </div>
          </div>

          <!-- ===== Conditional follow-ups =====
               Every "revealed" field lives in this one full-width row rather than
               inside the column that triggers it.

               They used to be nested in their own column, which is what made the
               form look broken: picking Transferee revealed a tall, narrow stack
               inside a third of the width, so that one column grew far past its
               neighbours and the step jumped. Religion's "Other" box did the same
               and was worse, because a stylesheet rule forced its column to 100%
               width and reflowed the whole row.

               Revealing a field now extends the form downward by one predictable,
               horizontal band, and the three columns above stay exactly the same
               height whatever the applicant picked. -->
          <div class="col-12 col-12">
            <div class="form-extras">
              <div class="form-extras__group<?= $isTransferee ? ' is-visible' : '' ?>" id="transfereeFields">
                <div class="form-extras__item">
                  <label class="form-label" for="previous_school">Previous School (Last school attended) *</label>
                  <input type="text" class="form-control" name="previous_school" id="previous_school"
                         value="<?= esc(old('previous_school', '')) ?>"
                         data-field-label="Previous School"
                         maxlength="255" placeholder="e.g. Cauayan Central Elementary School" autocomplete="off"
                         <?= $isTransferee ? 'required' : 'disabled' ?> />
                </div>
                <div class="form-extras__item">
                  <label class="form-label" for="previous_school_year">School Year Last Attended *</label>
                  <select class="form-select" name="previous_school_year" id="previous_school_year"
                          data-field-label="School Year Last Attended"
                          <?= $isTransferee ? 'required' : 'disabled' ?>>
                    <option value="">Select school year</option>
                    <?php foreach ($transfereeYears as $transfereeYear): ?>
                      <option value="<?= esc($transfereeYear) ?>" <?= old('previous_school_year') === $transfereeYear ? 'selected' : '' ?>><?= esc($transfereeYear) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">The school year the student was enrolled in that school.</div>
                </div>
              </div>

              <div class="form-extras__group form-extras__group--single<?= $nationalityIsOther ? ' is-visible' : '' ?>" id="nationalityOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="nationality_other">Specify Nationality *</label>
                  <input type="text" class="form-control" name="nationality_other" id="nationality_other"
                         value="<?= $nationalityIsOther ? esc($nationalityValue) : '' ?>"
                         data-field-label="Specify Nationality"
                         maxlength="100" placeholder="e.g. Bolivian" autocomplete="off"
                         <?= $nationalityIsOther ? 'required' : 'disabled' ?> />
                </div>
              </div>

              <div class="form-extras__group form-extras__group--single<?= $religionIsOther ? ' is-visible' : '' ?>" id="religionOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="religion_other">Specify Religion *</label>
                  <input type="text" class="form-control" name="religion_other" id="religion_other"
                         value="<?= $religionIsOther ? esc($religionValue) : '' ?>"
                         data-field-label="Specify Religion"
                         maxlength="100" placeholder="e.g. Aglipayan Church" autocomplete="off"
                         <?= $religionIsOther ? 'required' : 'disabled' ?> />
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-12 col-12">
              <label class="form-label">Place of Birth *</label>
              <input type="hidden" name="place_of_birth" id="place_of_birth" value="<?= old('place_of_birth') ?>">
              <div data-loc-group="place_of_birth" data-loc-field="place_of_birth" data-loc-required data-loc-label="Place of Birth"></div>
            </div>
          </div>

          </div>

          <!-- Step 2: Contact Information -->
          <div class="form-step" id="step2">
            <h5 class="section-title">Contact Information</h5>
          <div class="row g-3">
            <div class="col-md-6 col-12">
              <label class="form-label">Email Address *</label>
              <input type="email" class="form-control" name="email" value="<?= old('email') ?>" autocomplete="email" required />
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label">Contact Number *</label>
              <input type="text" class="form-control" name="contact_number" value="<?= old('contact_number') ?>" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX" required />
            </div>
            <div class="col-md-12 col-12">
              <label class="form-label">Address *</label>
              <input type="hidden" name="address" id="address" value="<?= old('address') ?>">
              <div data-loc-group="address" data-loc-field="address" data-loc-required data-loc-label="Address"></div>
            </div>
          </div>

          </div>

          <!-- Step 3: Emergency Contact -->
          <div class="form-step" id="step3">
            <h5 class="section-title">Emergency Contact</h5>
          <div class="row g-3">
            <div class="col-md-4 col-6">
              <label class="form-label">Emergency Contact Name *</label>
              <input type="text" class="form-control" name="emergency_contact_name" value="<?= old('emergency_contact_name') ?>" oninput="validateNameField(this)" required />
            </div>
            <div class="col-md-4 col-6">
              <label class="form-label">Emergency Contact Number *</label>
              <input type="text" class="form-control" name="emergency_contact_number" value="<?= old('emergency_contact_number') ?>" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX" required />
            </div>
            <div class="col-md-4 col-12">
              <?php
              // A relationship outside the listed choices reveals a free-text
              // box, so "Other" is never stored as a literal value.
              $relOptions = emergency_contact_relationship_options();
              $relOld     = old('emergency_contact_relationship', '', false);
              $relValue   = is_string($relOld) ? trim($relOld) : '';
              $relIsOther = emergency_contact_relationship_is_other($relValue);
              ?>
              <label class="form-label" for="emergency_contact_relationship">Relationship *</label>
              <select class="form-select" name="emergency_contact_relationship" id="emergency_contact_relationship" required onchange="toggleRelationshipOther()">
                <option value="">Select relationship</option>
                <?php foreach ($relOptions as $relOption): ?>
                  <option value="<?= esc($relOption) ?>" <?= $relValue === $relOption ? 'selected' : '' ?>><?= esc($relOption) ?></option>
                <?php endforeach; ?>
              </select>
              <!-- Same "Other" companion pattern as religion and nationality. It
                   used to borrow the .religion-other class, which is misleading
                   and meant it silently depended on another field's styling. -->
              <div class="form-extras__group form-extras__group--single<?= $relIsOther ? ' is-visible' : '' ?>" id="relationshipOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="emergency_contact_relationship_other">Specify Relationship *</label>
                  <input type="text" class="form-control" name="emergency_contact_relationship_other" id="emergency_contact_relationship_other"
                         value="<?= $relIsOther ? esc($relValue) : '' ?>"
                         data-field-label="Specify Relationship"
                         maxlength="50" placeholder="e.g. Godparent, Friend" autocomplete="off"
                         <?= $relIsOther ? 'required' : 'disabled' ?> />
                </div>
              </div>
            </div>
          </div>

          </div>

          <!-- Step 4: Account Information -->
          <div class="form-step" id="step4">
            <h5 class="section-title">Account Information</h5>
          <div class="row g-3">
            <div class="col-md-6 col-12">
              <label class="form-label" for="password">Password *</label>
              <div class="position-relative">
                <input type="password" class="form-control" name="password" id="password" autocomplete="new-password"
                       minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}" required
                       data-password-indicator />
                <button type="button" class="btn btn-sm position-absolute" style="right: 8px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #6c757d; padding: 8px; min-width: 36px; min-height: 36px; display: flex; align-items: center; justify-content: center; z-index: 10;" onclick="togglePassword('password')">
                  <i class="bi bi-eye" id="password-icon"></i>
                </button>
              </div>
              <div class="password-prompt">
                <i class="bi bi-key-fill" aria-hidden="true"></i>
                <span><strong>Input your desired password.</strong> This is the password you will use to log in.</span>
              </div>
              <div data-password-indicator>
                <?= view('partials/password_requirements', ['id' => 'password-hint']) ?>
              </div>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label">Confirm Password *</label>
              <div class="position-relative">
                <input type="password" class="form-control" name="password_confirm" id="password_confirm" autocomplete="new-password" required />
                <button type="button" class="btn btn-sm position-absolute" style="right: 8px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #6c757d; padding: 8px; min-width: 36px; min-height: 36px; display: flex; align-items: center; justify-content: center; z-index: 10;" onclick="togglePassword('password_confirm')">
                  <i class="bi bi-eye" id="password_confirm-icon"></i>
                </button>
              </div>
              <div class="form-text" id="password-match-hint"></div>
            </div>
          </div>

          </div>

          <div class="step-navigation">
            <button type="button" class="btn-step" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
            <i class="bi bi-arrow-left me-2"></i>Previous
          </button>
            <div class="right-buttons">
              <button type="button" class="btn-step" id="nextBtn" onclick="validateAndNext()">
                Next<i class="bi bi-arrow-right ms-2"></i>
              </button>
              <button class="register-btn" type="submit" id="submitBtn" style="display: none;">
                <i class="bi bi-check-circle me-2"></i>SUBMIT REGISTRATION
              </button>
            </div>
          </div>



          <div class="mt-3">
            <small class="text-muted">
              * Required fields. Your registration will be reviewed by school administrators before approval.
            </small>
          </div>
        </form>

        <!-- Age requirement confirmation modal (branded, opened on Next) -->
        <div class="age-modal-overlay" id="ageModalOverlay" hidden onclick="closeAgeModal()"></div>
        <div class="age-modal" id="ageModal" role="dialog" aria-modal="true" aria-labelledby="ageModalTitle" hidden>
          <div class="age-modal-header">
            <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="age-modal-logo">
            <div class="age-modal-header-text">
              <div class="age-modal-header-title">Cauayan South Central School</div>
              <div class="age-modal-header-sub">Student Registration &middot; Age Requirement Verification</div>
            </div>
          </div>
          <div class="age-modal-body">
            <h5 class="age-modal-title" id="ageModalTitle">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span id="ageModalTitleText">Age Requirement Not Met</span>
            </h5>
            <p class="age-modal-message" id="ageModalMessage"></p>

            <div class="age-modal-chips">
              <div class="age-modal-chip">
                <span>Enrolling to</span>
                <strong id="ageModalGradeChip">&ndash;</strong>
              </div>
              <div class="age-modal-chip">
                <span>Student age</span>
                <strong id="ageModalAgeChip">&ndash;</strong>
              </div>
              <div class="age-modal-chip">
                <span>Minimum age</span>
                <strong id="ageModalMinChip">&ndash;</strong>
              </div>
            </div>

            <div class="age-modal-table-title">Minimum age per grade level</div>
            <table class="age-ref-table">
              <thead>
                <tr>
                  <th scope="col">Grade Level</th>
                  <th scope="col">Minimum Age</th>
                  <th scope="col">Typical Range</th>
                </tr>
              </thead>
              <tbody id="ageRefTableBody"></tbody>
            </table>
            <p class="age-modal-note">Minimum age is measured at the start of the school year.</p>
          </div>
          <div class="age-modal-footer">
            <button type="button" class="age-modal-btn age-modal-btn--no" id="ageModalNo" onclick="closeAgeModal()">No, go back</button>
            <button type="button" class="age-modal-btn age-modal-btn--yes" id="ageModalYes" onclick="confirmAgeProceed()">Yes, proceed</button>
            <button type="button" class="age-modal-btn age-modal-btn--ok" id="ageModalOk" onclick="closeAgeModal()">OK</button>
          </div>
          <div class="age-modal-brand">Mabini Street, District I, Cauayan City, Isabela &middot; CSCS Tap n Track</div>
        </div>

        <script>
        let currentStep = <?= $errorStep ?>;
        const totalSteps = 4;
        const stepTitles = [
          'Personal Information',
          'Contact Information', 
          'Emergency Contact',
          'Account Information'
        ];

        function showStep(step) {
          document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
          document.getElementById('step' + step).classList.add('active');
          document.getElementById('stepText').textContent = `Step ${step} of ${totalSteps}: ${stepTitles[step-1]}`;
          
          document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
          document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'inline-block';
          document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';
        }

        function changeStep(direction) {
          const newStep = currentStep + direction;
          if (newStep >= 1 && newStep <= totalSteps) {
            currentStep = newStep;
            showStep(currentStep);
          }
        }

        function showCustomAlert(message) {
          const overlay = document.createElement('div');
          overlay.className = 'alert-overlay';
          
          const alertBox = document.createElement('div');
          alertBox.className = 'custom-alert';
          alertBox.innerHTML = `
            <div class="alert-title"><i class="bi bi-exclamation-triangle"></i> Required Fields Missing</div>
            <div class="alert-message">${message}</div>
            <button class="alert-close" onclick="closeCustomAlert()">OK</button>
          `;
          
          document.body.appendChild(overlay);
          document.body.appendChild(alertBox);
        }
        
        function closeCustomAlert() {
          document.querySelector('.alert-overlay')?.remove();
          document.querySelector('.custom-alert')?.remove();
        }
        
        function showSubmittingNotification() {
          const notification = document.createElement('div');
          notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            z-index: 9999;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
          `;
          notification.innerHTML = `
            <i class="bi bi-hourglass-split" style="font-size: 1.2rem;"></i>
            <span>Submitting your registration...</span>
          `;
          document.body.appendChild(notification);
        }

        function getFieldLabel(field) {
          // A companion control can name itself when the column heading is not
          // specific enough (e.g. the religion "Other" textbox).
          if (field.dataset && field.dataset.fieldLabel) {
            return field.dataset.fieldLabel;
          }

          const wrapper = field.closest('[class*="col-"]');
          const label = wrapper ? wrapper.querySelector('label') : null;
          if (label) {
            return label.textContent.replace(/\s*\*$/, '').trim();
          }
          return field.name || 'This field';
        }

        function isFieldEmpty(field) {
          if (field.type === 'file') {
            return !field.files.length;
          }
          if (field.tagName === 'SELECT') {
            return !String(field.value).trim();
          }
          return !String(field.value).trim();
        }

        function validateStep(stepNumber) {
          const stepElement = document.getElementById('step' + stepNumber);
          const requiredFields = stepElement.querySelectorAll('input[required], select[required], textarea[required]');
          const emptyFields = [];

          requiredFields.forEach(field => {
            if (isFieldEmpty(field)) {
              emptyFields.push(getFieldLabel(field));
            }
          });

          if (stepNumber === 2) {
            const emailField = stepElement.querySelector('input[type="email"]');
            if (emailField && emailField.value) {
              const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
              if (!emailRegex.test(emailField.value)) {
                return { valid: false, message: 'Please enter a valid email address.<br><br>Example: <strong>student@example.com</strong>' };
              }
            }

            const contactField = stepElement.querySelector('input[name="contact_number"]');
            if (contactField && contactField.value && !isValidContactNumber(contactField.value)) {
              return { valid: false, message: 'Contact number must be 11 digits and start with 09.<br><br>Example: <strong>09171234567</strong>' };
            }
          }

          if (stepNumber === 3) {
            const emergencyField = stepElement.querySelector('input[name="emergency_contact_number"]');
            if (emergencyField && emergencyField.value && !isValidContactNumber(emergencyField.value)) {
              return { valid: false, message: 'Emergency contact number must be 11 digits and start with 09.<br><br>Example: <strong>09171234567</strong>' };
            }
          }

          if (stepNumber === 1) {
            const lrnField = stepElement.querySelector('input[name="lrn"]');
            if (lrnField && lrnField.value.length !== 12) {
              return { valid: false, message: 'LRN must be exactly 12 digits.' };
            }

            // A filled middle name must be 2+ characters, matching the server
            // rule: a lone letter — including a padded "M " — is rejected, so
            // Next cannot advance with one. Ticking "No middle name" clears and
            // disables the field, so the check is skipped then.
            const middleNameField = stepElement.querySelector('input[name="middle_name"]');
            if (middleNameField && middleNameField.value.trim() !== '' && middleNameField.value.trim().length < 2) {
              return { valid: false, message: 'Middle Name must be at least 2 characters long. Tick the "No middle name" box if the student has no middle name.' };
            }
          }

          if (emptyFields.length > 0) {
            return {
              valid: false,
              message: 'Please fill in the following required fields:<br><br><strong>' + emptyFields.join('<br>') + '</strong>'
            };
          }

          return { valid: true };
        }

        function validateAllSteps() {
          for (let step = 1; step <= totalSteps; step++) {
            const result = validateStep(step);
            if (!result.valid) {
              currentStep = step;
              showStep(currentStep);
              showCustomAlert(result.message);
              return false;
            }
          }

          // Re-check the age requirement on submit too: a server re-render
          // (error_step) can drop the applicant straight onto a later step and
          // skip the Step 1 gate. The form must not submit in that case.
          return ageGate(false);
        }

        function validateAndNext() {
          const result = validateStep(currentStep);
          if (!result.valid) {
            showCustomAlert(result.message);
            return;
          }

          // Step 1 additionally checks the age requirement; it advances the
          // wizard itself once the applicant accepts (or the check passes).
          if (currentStep === 1) {
            ageGate(true);
            return;
          }

          changeStep(1);
        }

        function togglePassword(fieldId) {
          const field = document.getElementById(fieldId);
          const icon = document.getElementById(fieldId + '-icon');
          
          if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'bi bi-eye-slash';
          } else {
            field.type = 'password';
            icon.className = 'bi bi-eye';
          }
        }

        function validateNameField(input) {
          // Remove any numbers and special characters, keep only letters, spaces, hyphens, and apostrophes
          input.value = input.value.replace(/[^a-zA-Z\s\-\']/g, '');
        }

        // The relationship selector reveals its free-text box for "Other",
        // mirroring the religion selector. While hidden that box is disabled
        // (so a stale value is never submitted) and drops `required`.
        function toggleRelationshipOther() {
          toggleOtherChoice(
            'emergency_contact_relationship',
            'relationshipOtherWrap',
            'emergency_contact_relationship_other',
            <?= json_encode(emergency_contact_relationship_other_option()) ?>
          );
        }

        // "No middle name": when ticked the field is cleared, disabled (so no
        // stale value is ever submitted) and unrequired. When unticked it is
        // required and must be at least two characters.
        function toggleNoMiddleName() {
          const checkbox = document.getElementById('no_middle_name');
          const field = document.getElementById('middle_name');
          if (!checkbox || !field) {
            return;
          }

          const noMiddleName = checkbox.checked;
          if (noMiddleName) {
            field.value = '';
          }

          field.disabled = noMiddleName;
          field.required = !noMiddleName;

          field.style.opacity = noMiddleName ? '0.5' : '';
        }

        // The religion selector only reveals its free-text box for "Other".
        // While hidden that box is disabled (so a stale value is never
        // submitted) and drops `required`, keeping the step validation focused
        // on what the applicant can actually see.
        function toggleReligionOther() {
          toggleOtherChoice('religion', 'religionOtherWrap', 'religion_other');
        }

        // Nationality works exactly the same way. It always offered an "Other"
        // entry, but nothing was listening for it, so picking it stored the
        // literal word "Other" and left the applicant no way to say what their
        // nationality actually was.
        function toggleNationalityOther() {
          toggleOtherChoice('nationality', 'nationalityOtherWrap', 'nationality_other');
        }

        /**
         * Show or hide one "Other" companion box.
         *
         * The box lives in .form-extras, in a full-width band below the row, so
         * revealing it never changes the height of the column that triggered it.
         *
         * @param {string} selectId id of the <select> holding the options
         * @param {string} wrapId   id of the wrapper inside .form-extras
         * @param {string} inputId  id of the free-text box inside that wrapper
         * @param {string} [otherValue] the option's value, defaulting to 'Other'
         */
        function toggleOtherChoice(selectId, wrapId, inputId, otherValue) {
          const select = document.getElementById(selectId);
          const wrapper = document.getElementById(wrapId);
          const input = document.getElementById(inputId);

          if (!select || !wrapper || !input) {
            return;
          }

          const isOther = select.value === (otherValue || 'Other');

          wrapper.classList.toggle('is-visible', isOther);
          input.required = isOther;
          // Disabled while hidden, so a value typed earlier is never submitted
          // against a choice that is no longer selected.
          input.disabled = !isOther;
        }

        // Transferees must declare the school they came from and the school
        // year they last attended it. While hidden those controls are disabled
        // (so a stale value is never submitted) and drop `required`, keeping
        // the step validation focused on what the applicant can actually see.
        function toggleTransfereeFields() {
          const select = document.getElementById('student_type');
          const wrapper = document.getElementById('transfereeFields');
          if (!select || !wrapper) {
            return;
          }

          const isTransferee = select.value === 'Transferee';
          wrapper.classList.toggle('is-visible', isTransferee);

          wrapper.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.required = isTransferee;
            field.disabled = !isTransferee;
          });
        }

        // A Philippine mobile number is 09 followed by exactly 9 digits.
        function isValidContactNumber(value) {
          if (window.PhoneInput) {
            return window.PhoneInput.isValid(value);
          }
          return /^09[0-9]{9}$/.test(String(value || '').replace(/\D/g, ''));
        }

        /* ===== AGE REQUIREMENT CHECK (Step 1) =====
           Minimum ages come from grade_min_age_reference(): Kindergarten = 5,
           Grade 1 = 6 ... Grade 6 = 11. SNED (7) and Custom (99) carry no
           minimum and are always exempt. Only students BELOW the minimum are
           challenged: a 1-year gap is confirmable, a 2-year gap or more is
           blocked. Over-aged students are never challenged. */
        const GRADE_MIN_AGE = <?= json_encode(grade_min_age_reference()) ?>;
        const GRADE_LABELS = <?= json_encode(grade_level_js_labels()) ?>;
        let ageModalState = null;

        function parseBirthDate(value) {
          const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
          if (!match) {
            return null;
          }

          const year = Number(match[1]);
          const month = Number(match[2]);
          const day = Number(match[3]);
          const birthDate = new Date(year, month - 1, day);

          // Rejects impossible dates (e.g. 2020-02-31) that Date would roll over.
          if (birthDate.getFullYear() !== year || birthDate.getMonth() !== month - 1 || birthDate.getDate() !== day) {
            return null;
          }

          return birthDate;
        }

        // Whole years old today; -1 when the date is invalid or in the future.
        function studentCurrentAge() {
          const dobField = document.getElementById('date_of_birth');
          const birthDate = parseBirthDate(dobField ? dobField.value : '');
          if (!birthDate) {
            return -1;
          }

          const now = new Date();
          if (birthDate.getTime() > now.getTime()) {
            return -1;
          }

          let age = now.getFullYear() - birthDate.getFullYear();
          const hadBirthday = now.getMonth() > birthDate.getMonth()
            || (now.getMonth() === birthDate.getMonth() && now.getDate() >= birthDate.getDate());
          if (!hadBirthday) {
            age -= 1;
          }

          return age;
        }

        function ageText(age) {
          return age + (age === 1 ? ' year old' : ' years old');
        }

        function gradeLevelLabelFor(value) {
          return GRADE_LABELS[String(value)] || 'the selected grade level';
        }

        function studentNameDisplay() {
          const parts = ['first_name', 'middle_name', 'last_name']
            .map(function (name) {
              const field = document.querySelector('[name="' + name + '"]');
              return field ? field.value.trim() : '';
            })
            .filter(Boolean);

          return parts.length ? parts.join(' ') : 'This student';
        }

        // The table is generated from the same helper that feeds GRADE_MIN_AGE,
        // so the displayed minimums can never drift from the enforced ones.
        function renderAgeReferenceTable(selectedGrade) {
          const body = document.getElementById('ageRefTableBody');
          if (!body) {
            return;
          }

          body.innerHTML = '';
          GRADE_MIN_AGE.forEach(function (row) {
            const tr = document.createElement('tr');
            if (String(row.grade) === String(selectedGrade)) {
              tr.className = 'is-selected';
            }

            const gradeCell = document.createElement('td');
            gradeCell.textContent = row.label;
            const minCell = document.createElement('td');
            minCell.textContent = row.min_age + ' years old';
            const rangeCell = document.createElement('td');
            rangeCell.textContent = row.typical_min + '–' + row.typical_max;

            tr.appendChild(gradeCell);
            tr.appendChild(minCell);
            tr.appendChild(rangeCell);
            body.appendChild(tr);
          });
        }

        // Live badge beside the Date of Birth field.
        function updateAgeIndicator() {
          const indicator = document.getElementById('ageIndicator');
          const ageEl = document.getElementById('ageIndicatorAge');
          const statusEl = document.getElementById('ageIndicatorStatus');
          if (!indicator || !ageEl || !statusEl) {
            return;
          }

          indicator.className = 'age-indicator';
          indicator.hidden = true;
          ageEl.textContent = '';
          statusEl.textContent = '';

          const dobField = document.getElementById('date_of_birth');
          const gradeField = document.getElementById('grade_level');
          if (!dobField || !dobField.value || !gradeField || gradeField.value === '') {
            return;
          }

          const age = studentCurrentAge();
          if (age < 0) {
            indicator.hidden = false;
            indicator.classList.add('is-invalid');
            ageEl.textContent = 'Invalid date';
            statusEl.textContent = 'Date of birth must be a valid past date.';
            return;
          }

          const gradeValue = String(gradeField.value);
          const gradeName = gradeLevelLabelFor(gradeValue);
          const row = GRADE_MIN_AGE.find(function (entry) {
            return String(entry.grade) === gradeValue;
          });

          indicator.hidden = false;
          ageEl.textContent = ageText(age);

          if (!row) {
            indicator.classList.add('is-meets');
            statusEl.textContent = 'No minimum age requirement for ' + gradeName + '.';
            return;
          }

          const gap = row.min_age - age;
          if (gap <= 0) {
            indicator.classList.add('is-meets');
            statusEl.textContent = 'Meets the minimum for ' + gradeName + ' (' + row.min_age + ' years old).';
          } else if (gap === 1) {
            indicator.classList.add('is-warning');
            statusEl.textContent = '1 year below the minimum for ' + gradeName + ' — confirmation required.';
          } else {
            indicator.classList.add('is-blocked');
            statusEl.textContent = gap + ' years below the minimum for ' + gradeName + ' — not allowed.';
          }
        }

        function closeAgeModal() {
          const modal = document.getElementById('ageModal');
          const overlay = document.getElementById('ageModalOverlay');
          if (modal) {
            modal.hidden = true;
            modal.classList.remove('is-blocked');
          }
          if (overlay) {
            overlay.hidden = true;
          }
          ageModalState = null;
        }

        // mode: 'confirm' (1-year gap, proceedable) or 'blocked' (2+ year gap).
        function showAgeModal(mode, state) {
          const modal = document.getElementById('ageModal');
          const overlay = document.getElementById('ageModalOverlay');
          if (!modal) {
            return;
          }

          const titleText = document.getElementById('ageModalTitleText');
          const message = document.getElementById('ageModalMessage');
          const gradeChip = document.getElementById('ageModalGradeChip');
          const ageChip = document.getElementById('ageModalAgeChip');
          const minChip = document.getElementById('ageModalMinChip');
          const yesBtn = document.getElementById('ageModalYes');
          const noBtn = document.getElementById('ageModalNo');
          const okBtn = document.getElementById('ageModalOk');

          ageModalState = { mode: mode, grade: state.grade, gradeLabel: state.gradeLabel, age: state.age, minAge: state.minAge };
          renderAgeReferenceTable(state.grade);

          const isBlocked = mode === 'blocked';
          modal.classList.toggle('is-blocked', isBlocked);

          if (titleText) {
            titleText.textContent = isBlocked ? 'Registration Not Allowed' : 'Age Requirement Confirmation';
          }
          if (message) {
            message.textContent = isBlocked
              ? state.name + ' is ' + ageText(state.age) + ' and is ' + (state.minAge - state.age)
                + ' years below the minimum age for ' + state.gradeLabel + ' (' + state.minAge
                + ' years old). This age gap is too large, so registration for ' + state.gradeLabel
                + ' cannot proceed. Please select the appropriate grade level.'
              : 'Are you sure you are enrolling to ' + state.gradeLabel + '? ' + state.name + ' is '
                + ageText(state.age) + '. The minimum age for ' + state.gradeLabel + ' is ' + state.minAge
                + ' years old. You may still continue, and the registration will be reviewed by school administrators.';
          }
          if (gradeChip) { gradeChip.textContent = state.gradeLabel; }
          if (ageChip) { ageChip.textContent = ageText(state.age); }
          if (minChip) { minChip.textContent = state.minAge + ' years old'; }

          if (yesBtn) { yesBtn.style.display = isBlocked ? 'none' : ''; }
          if (noBtn) { noBtn.style.display = isBlocked ? 'none' : ''; }
          if (okBtn) { okBtn.style.display = isBlocked ? '' : 'none'; }

          if (overlay) {
            overlay.hidden = false;
          }
          modal.hidden = false;
        }

        // Returns null when there is nothing to enforce, otherwise a state
        // object whose `mode` is 'confirm' or 'blocked'.
        function ageRequirementState() {
          const dobField = document.getElementById('date_of_birth');
          const gradeField = document.getElementById('grade_level');
          const dobValue = dobField ? dobField.value : '';
          const gradeValue = gradeField ? String(gradeField.value) : '';

          if (!dobValue || gradeValue === '') {
            return null;
          }

          const age = studentCurrentAge();
          if (age < 0) {
            return { mode: 'invalid', name: studentNameDisplay(), grade: gradeValue, gradeLabel: gradeLevelLabelFor(gradeValue), age: 0, minAge: 0 };
          }

          const row = GRADE_MIN_AGE.find(function (entry) {
            return String(entry.grade) === gradeValue;
          });
          if (!row) {
            // SNED / Custom levels have no minimum age.
            return null;
          }

          const gap = row.min_age - age;
          if (gap <= 0) {
            return null;
          }

          return {
            mode: gap === 1 ? 'confirm' : 'blocked',
            name: studentNameDisplay(),
            grade: gradeValue,
            gradeLabel: row.label,
            age: age,
            minAge: row.min_age
          };
        }

        // Shared gate for the Next button and for the final submit. `advance`
        // is false while submitting so a rejection never moves the wizard.
        function ageGate(advance) {
          const state = ageRequirementState();

          if (state && state.mode === 'invalid') {
            if (!advance) {
              currentStep = 1;
              showStep(currentStep);
            }
            showCustomAlert('Please enter a valid date of birth in the past.');
            return false;
          }

          if (!state) {
            if (advance) {
              changeStep(1);
            }
            return true;
          }

          // Age problem found on submit (e.g. a server re-render skipped the
          // Step 1 gate): send the applicant back to the fields that matter.
          if (!advance) {
            currentStep = 1;
            showStep(currentStep);
          }

          showAgeModal(state.mode, state);
          return false;
        }

        function confirmAgeProceed() {
          if (ageModalState && ageModalState.mode === 'confirm') {
            closeAgeModal();
            changeStep(1);
          }
        }

        // Fields left showing only the untouched "09" prefix are cleared so the
        // browser and server both treat them as empty.
        function normaliseContactFields() {
          document.querySelectorAll('input[name="contact_number"], input[name="emergency_contact_number"]').forEach(function (field) {
            if (!window.PhoneInput) {
              return;
            }
            const value = window.PhoneInput.normalise(field.value);
            field.value = value === window.PhoneInput.PREFIX ? '' : value;
          });
        }

        document.addEventListener('DOMContentLoaded', function() {
          showStep(currentStep);

          // Live age indicator next to the date of birth.
          const dobField = document.getElementById('date_of_birth');
          const gradeField = document.getElementById('grade_level');
          if (dobField) {
            dobField.addEventListener('input', updateAgeIndicator);
            dobField.addEventListener('change', updateAgeIndicator);
          }
          if (gradeField) {
            gradeField.addEventListener('change', updateAgeIndicator);
          }
          updateAgeIndicator();

          // Escape closes the age modal (it never confirms by accident).
          document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
              const modal = document.getElementById('ageModal');
              if (modal && !modal.hidden) {
                closeAgeModal();
              }
            }
          });

          // Restore the religion box visibility for a redisplayed form.
          toggleReligionOther();

          // Nationality gets the same treatment, for the same reason.
          toggleNationalityOther();

          // Restore the transferee box visibility for a redisplayed form.
          toggleTransfereeFields();

          // Restore the middle-name box state for a redisplayed form.
          toggleNoMiddleName();

          // Restore the relationship box for a redisplayed form.
          toggleRelationshipOther();

          // Real-time password validation
          const passwordField = document.getElementById('password');
          const passwordConfirmField = document.getElementById('password_confirm');
          const passwordHint = document.getElementById('password-hint');
          const passwordMatchHint = document.getElementById('password-match-hint');

          // The requirement list is shared markup, so only the field border is
          // coloured here; password-policy.js ticks off each rule.
          const passwordPolicy = <?= json_encode([
              'min_length' => password_policy_min_length(),
              'min_digits' => password_policy_min_digits(),
          ]) ?>;

          function passwordIsValid(password) {
            return password.length >= passwordPolicy.min_length
              && (password.match(/\d/g) || []).length >= passwordPolicy.min_digits;
          }

          function validatePassword() {
            const password = passwordField.value;
            if (password.length > 0 && !passwordIsValid(password)) {
              passwordField.style.borderColor = '#dc2626';
            } else if (password.length > 0) {
              passwordField.style.borderColor = '#16a34a';
            } else {
              passwordField.style.borderColor = '#e2e8f0';
            }

            // Keep the shared requirement list in step with the field.
            if (window.PasswordPolicy) {
              window.PasswordPolicy.updateField(passwordField);
            }

            validatePasswordMatch();
          }
          
          function validatePasswordMatch() {
            const password = passwordField.value;
            const passwordConfirm = passwordConfirmField.value;
            
            if (passwordConfirm.length > 0) {
              if (password !== passwordConfirm) {
                passwordMatchHint.style.color = '#dc2626';
                passwordMatchHint.textContent = '❌ Passwords do not match';
                passwordConfirmField.style.borderColor = '#dc2626';
              } else {
                passwordMatchHint.style.color = '#16a34a';
                passwordMatchHint.textContent = '✓ Passwords match';
                passwordConfirmField.style.borderColor = '#16a34a';
              }
            } else {
              passwordMatchHint.textContent = '';
              passwordConfirmField.style.borderColor = '#e2e8f0';
            }
          }
          
          passwordField.addEventListener('input', validatePassword);
          passwordField.addEventListener('blur', validatePassword);
          passwordConfirmField.addEventListener('input', validatePasswordMatch);
          passwordConfirmField.addEventListener('blur', validatePasswordMatch);
          
          // Initialize form validation
          const form = document.getElementById('registrationForm');
          form.addEventListener('submit', function(e) {
            e.preventDefault();

            normaliseContactFields();

            if (!validateAllSteps()) {
              return;
            }

            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            if (password !== passwordConfirm) {
              currentStep = 4;
              showStep(currentStep);
              showCustomAlert('Passwords do not match. Please make sure both password fields are identical.');
              return;
            }

            if (password.length < 8) {
              currentStep = 4;
              showStep(currentStep);
              showCustomAlert('Password must be at least 8 characters long.');
              return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> SUBMITTING...';
            showSubmittingNotification();

            HTMLFormElement.prototype.submit.call(form);
          });
        })


        </script>
        <script src="<?= asset_url('js/password-policy.js') ?>"></script>
      </div>
    </div>
  </div>
</div>

<?php /* The guidance dialog sits OUTSIDE .register-container, as a sibling and
         not a descendant, and that is load-bearing rather than tidiness.

         .register-card has backdrop-filter: blur(25px), overflow: hidden and
         position: relative with z-index: 1. Between them those three create a
         stacking context AND make the card the containing block for any
         position: fixed descendant. A Bootstrap modal nested inside is therefore
         resolved against the card rather than the viewport: its z-index of 1055
         is trapped inside a z-index: 1 context and overflow: hidden clips it, so
         the dialog renders behind the page and takes no clicks. This is the same
         trap the login form already documents for its own card.

         The auto-open script must live here too, for the same reason: the modal
         element is found by id, so it can sit anywhere, but it must not be
         reparented. */ ?>
<?= view('partials/registration_help_modal') ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('registrationHelpModal');
  if (!el || typeof bootstrap === 'undefined') { return; }

  // Once per browser session, not once ever. A parent who dismisses it to go
  // and find the birth certificate should meet it again on their way back;
  // sessionStorage gives that and clears itself on its own.
  var KEY = 'cscs.reg-help-seen';
  var seen = false;
  try { seen = window.sessionStorage.getItem(KEY) === '1'; } catch (e) { seen = true; }

  if (seen) { return; }

  var instance = bootstrap.Modal.getOrCreateInstance(el);
  el.addEventListener('shown.bs.modal', function () {
    try { window.sessionStorage.setItem(KEY, '1'); } catch (e) { /* private mode */ }
  }, { once: true });
  instance.show();
});
</script>
<?= $this->endSection() ?>