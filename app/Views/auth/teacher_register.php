<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<style>
<?= view('partials/password_requirements_style') ?>

/* Teacher registration deliberately mirrors the student registration page
   (app/Views/auth/register.php) so both public sign-up forms look the same. */
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

/* Scoped to .register-card for the same reason, and with the same root cause,
   as the identical rule in register.php: app.css declares
     .section-title:not([class*="text-"]) { color: var(--color-heading) }
   whose :not([class*="text-"]) lifts it to (0,2,0), so it outranked a bare
   `.section-title` and painted the heading dark navy-on-dark-navy. This page
   shares that palette, so it needs the same rescue. */
.register-card .section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.75rem;
  margin-top: 0;
  padding-bottom: 0.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
}

/* Text colour is dark navy, not white, for the same reason as register.php:
   white on this amber gradient measured 2.35:1, under the 4.5:1 that 14px bold
   text needs. Navy on the same amber reaches 4.40:1 and matches the amber
   button in the registration help dialog. */
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

/* Same rescue as register.php: this chip inherits Bootstrap's .input-group-text,
   which ui-depth.css paints with the light form-control gradient !important.
   This page is also a dark navy card with white chip text, so that left white
   on white. Translucent white keeps the label legible and keeps its chip shape. */
.register-card .no-middle-name-check {
  background: rgba(255, 255, 255, 0.12) !important;
  background-color: rgba(255, 255, 255, 0.12) !important;
  background-image: none !important;
  border-color: rgba(255, 255, 255, 0.3) !important;
  color: #fff;
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

/* 0.75 rather than 0.6, for the same reason as register.php: at 0.6 this sat at
   4.06:1 on the navy card, under the 4.5:1 floor. */
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

/* ===== "No middle name" checkbox =====
   An append on the input itself, exactly as on the student form, for the same
   reason: a row of its own added height to the name row, and beside the label
   it overflowed the column and collided with the next one. Folded into the
   field it stays attached to the control it governs and cannot spill. */
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

/* ===== AGE INDICATOR (floating above Date of Birth) =====
   Absolutely positioned rather than in the flow, so appearing and disappearing
   never changes the page height and the step does not jump under the cursor. */
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
  color: #475569;
}

.age-indicator.is-meets {
  --age-bg: #f0fdf4;
  border-color: #bbf7d0;
}

.age-indicator.is-warning {
  --age-bg: #fffbeb;
  border-color: #fde68a;
}

.age-indicator.is-blocked {
  --age-bg: #fef2f2;
  border-color: #fecaca;
}

.age-indicator.is-blocked .age-indicator-status {
  color: #b91c1c;
  font-weight: 700;
}

/* ===== AGE REQUIREMENT MODAL =====
   The teacher twin of the student form's age dialog. Same structure and the
   same class names, so the two cannot drift apart visually; the content
   differs because a teacher has one hard floor (minimum age) rather than a
   per-grade table. */
.age-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 1040;
}

.age-modal-overlay[hidden] {
  display: none;
}

.age-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1050;
  width: min(560px, 94vw);
  max-height: 90vh;
  overflow-y: auto;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.45);
}

.age-modal[hidden] {
  display: none;
}

.age-modal-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem 0.75rem;
  background: #1e3a8a;
  color: #ffffff;
  border-radius: 16px 16px 0 0;
}

.age-modal-logo {
  width: 46px;
  height: 46px;
  object-fit: contain;
  border-radius: 50%;
  background: #ffffff;
  padding: 3px;
  flex-shrink: 0;
}

.age-modal-header-title {
  font-size: 1rem;
  font-weight: 700;
}

.age-modal-header-sub {
  font-size: 0.8125rem;
  opacity: 0.85;
}

.age-modal-body {
  padding: 1rem 1.25rem 0.5rem;
}

.age-modal-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.1rem;
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

.age-modal-btn--ok {
  background: #e2e8f0;
  color: #334155;
}

.age-modal-btn--ok:hover {
  background: #cbd5e1;
}

.age-modal-brand {
  padding: 0.45rem 1.25rem 0.7rem;
  text-align: center;
  font-size: 0.8125rem;
  color: #94a3b8;
  border-top: 1px solid #f1f5f9;
}

@media (max-width: 575.98px) {
  .age-modal-header { padding: 0.75rem 0.9rem; }
  .age-modal-logo { width: 38px; height: 38px; }
  .age-modal-body { padding: 0.9rem 0.9rem 0.4rem; }
  .age-modal-message { font-size: 0.82rem; }
  .age-modal-footer { padding: 0.6rem 0.9rem 0.4rem; flex-direction: column; }
  .age-modal-btn { flex: 1 1 auto; padding: 0.5rem 0.8rem; }
}

/* ===== Conditional follow-ups =====
   The one full-width band that holds every field a selector can reveal.

   These used to be nested inside the column that triggered them (the old
   .religion-other block), which is what made the step jump: revealing a field
   grew one third of the row to several times the height of its neighbours, and
   on a phone a media rule then forced that column to 100% width and reflowed the
   whole row. Each group is a horizontal grid here, so a revealed field adds one
   short band below the row and the columns above never change height.
   --single keeps a free-text "Other" box at a readable width. */
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
  animation: otherReveal 0.2s ease-out;
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

@keyframes otherReveal {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: none; }
}

/* ===== "Need help?" button in the step counter =====
   Taken out of flow, mirroring the student form. As a flex sibling it pushed
   "Step 1 of 3..." hard left, and no amount of flex alignment centres one item
   while pinning another to the opposite edge. */
.step-indicator {
  position: relative;
  display: block;
  text-align: center;
  padding: 0 2.75rem;
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.85rem;
}

.step-indicator .reg-help-open {
  position: absolute;
  top: 50%;
  right: 0;
  transform: translateY(-50%);
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
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.step-navigation .right-buttons {
  display: flex;
  gap: 10px;
  /* space-between alone is not enough. #prevBtn is display:none on step 1, and a
     lone flex item under space-between lands at the START, which pinned Next to
     the left corner. margin-left:auto holds the button group against the right
     edge whether or not Previous is currently on screen. */
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

.notice-box {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 10px;
  padding: 0.75rem 0.9rem;
  margin-bottom: 1.25rem;
  color: rgba(255, 255, 255, 0.92);
  font-size: 0.78rem;
  line-height: 1.5;
}

.notice-box i {
  color: #fbbf24;
}

/* ===== MOBILE RESPONSIVE STYLES ===== */
@media (max-width: 991.98px) {
  .register-header { padding: 2.5rem 2rem 1.5rem; }
  .register-title { font-size: 1.75rem; }
  .register-logo { width: 70px; height: 70px; }
  .register-form { padding: 1.25rem 1.5rem 1.5rem; }
}

@media (max-width: 767.98px) {
  .register-container { padding: 1.5rem 0.75rem; }
  .register-card { border-radius: 16px; max-width: 100%; }
  .register-header { padding: 2rem 1.5rem 1.25rem; }
  .register-logo { width: 60px; height: 60px; margin-bottom: 0.75rem; }
  .register-title { font-size: 1.5rem; }
  .register-subtitle { font-size: 0.85rem; }
  .register-form { padding: 1rem 1.25rem 1.25rem; }
  .form-control, .form-select { font-size: 16px; padding: 0.5rem 0.625rem; }
  .form-label { font-size: 0.78rem; }
  .section-title { font-size: 0.95rem; margin-bottom: 0.5rem; }
  .step-indicator { font-size: 0.8rem; margin-bottom: 1rem; }
  .register-form .row > [class*="col-"] { margin-bottom: 0.5rem; }
  .register-form .row.g-2,
  .register-form .row.g-3 { --bs-gutter-y: 0.5rem; }
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

@media (max-width: 575.98px) {
  .register-container { padding: 1rem 0.5rem; }
  .register-header { padding: 1.5rem 1rem 1rem; }
  .register-logo { width: 52px; height: 52px; margin-bottom: 0.5rem; }
  .register-title { font-size: 1.25rem; margin-bottom: 0.25rem; }
  .register-subtitle { font-size: 0.8125rem; }
  .register-form { padding: 0.75rem 0.75rem 1rem; }
  .register-form .row > .col-md-3,
  .register-form .row > .col-md-4,
  .register-form .row > .col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding-left: 4px;
    padding-right: 4px;
  }
  .register-form .row > .col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
    padding-left: 4px;
    padding-right: 4px;
  }
  .form-control, .form-select { font-size: 16px; padding: 0.45rem 0.5rem; border-radius: 6px; }
  .form-label { font-size: 0.8125rem; margin-bottom: 0.15rem; }
  .section-title { font-size: 0.85rem; margin-bottom: 0.35rem; padding-bottom: 0.15rem; }
  .step-indicator { font-size: 0.8125rem; margin-bottom: 0.75rem; }
  .register-form .row { margin-left: -4px; margin-right: -4px; }
  .step-navigation { margin-top: 1rem; padding-top: 0.75rem; flex-direction: column; gap: 0.5rem; }
  .step-navigation .right-buttons { width: 100%; justify-content: center; }
  .btn-step { padding: 0.5rem 0.875rem; font-size: 0.8rem; }
  .register-btn { padding: 0.625rem 1rem; font-size: 0.85rem; min-height: 42px; }
  .alert { padding: 0.625rem; margin-bottom: 0.75rem; font-size: 0.8rem; border-radius: 8px; }
  .custom-alert { padding: 1.25rem; width: 88%; }
  .form-text, .text-muted { font-size: 0.7rem; }
  .notice-box { font-size: 0.8125rem; }
  .step-navigation #prevBtn { width: 100%; }
}

@media (max-width: 359px) {
  .register-form .row > .col-md-3,
  .register-form .row > .col-md-4,
  .register-form .row > .col-md-6 { flex: 0 0 100%; max-width: 100%; }
}

/* Password toggle buttons stay clickable */
.register-form .position-relative { position: relative !important; }

.register-form .position-relative button {
  position: absolute !important;
  cursor: pointer !important;
  z-index: 5 !important;
}

.register-form input[type="password"] {
  padding-right: 40px !important;
}
</style>

<div class="register-container">
  <div class="register-card">
    <div class="register-header">
      <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="register-logo">
      <h1 class="register-title">Teacher Registration</h1>
      <p class="register-subtitle">Create your teacher account at Cauayan South Central School</p>
    </div>

    <div class="register-form">
      <div class="notice-box">
        <i class="bi bi-info-circle-fill me-1"></i>
        Fill out this form to request a teacher account. An administrator will review your
        submission before it is activated &mdash; you will be able to sign in once it has been approved.
      </div>

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

      // Religion: a selector of the ten most populated Philippine affiliations
      // plus an "Other" choice that reveals a free-text box — the same control
      // the student registration form uses (see auth/register.php). A stored or
      // previously typed value that is not on the list reopens the box.
      $religionOptions  = religion_options();
      $religionOld      = old('religion', '', false);
      $religionValue    = is_string($religionOld) ? trim($religionOld) : '';
      $religionIsOther  = $religionValue !== '' && ! in_array($religionValue, $religionOptions, true);

      // "No middle name", the same policy the student form uses. Ticking the box
      // clears the field, disables it (so no stale value is ever submitted) and
      // drops `required`; a supplied middle name must be 2+ characters.
      $noMiddleName    = old(NO_MIDDLE_NAME_POST_KEY) === no_middle_name_rule();
      $middleNameValue = $noMiddleName ? '' : old('middle_name');

      // The four dropdowns that each gained an "Other" escape hatch. Resolved from
      // teacher_other_choices() so the view, the controller's normaliser and the
      // test that guards this all read the same list — a selector cannot ship with
      // an "Other" entry and no box behind it.
      //
      // A restored value that is not in the list reopens its box, so a redisplayed
      // form (a server error, or the applicant switching back) never silently drops
      // what was typed.
      $otherChoices = [];
      foreach (teacher_other_choices() as $choiceKey => [$field, $companion, $label]) {
          $value      = old($field, '', false);
          $value      = is_string($value) ? trim($value) : '';
          $isOther    = $value !== '' && $value === teacher_other_option();

          // The option list the dropdown itself renders, always with "Other" on the
          // end. Kept next to the state so the two cannot disagree about whether
          // the escape hatch is being offered.
          $options = match ($field) {
              'position'    => teacher_options_with_other(teacher_position_options()),
              'subjects'    => teacher_options_with_other(teacher_subject_options()),
              'designation' => teacher_options_with_other(teacher_designation_options()),
              'civil_status' => teacher_options_with_other(teacher_civil_status_options()),
              default       => [],
          };

          $otherChoices[$choiceKey] = [
              'field'     => $field,
              'companion' => $companion,
              'label'     => $label,
              'value'     => $value,
              'isOther'   => $isOther,
              'options'   => $options,
          ];
      }
      ?>

      <form method="post" action="<?= base_url('teacher/register') ?>" id="teacherRegistrationForm" novalidate>
        <?= csrf_field() ?>

        <?php /* The help button sits beside the step counter rather than in the
                 page header. As a long labelled button in the header it pushed
                 the form down and read as a second call to action competing with
                 registering itself; as an icon here it is a quiet way out for
                 anyone stuck. */ ?>
        <div class="step-indicator">
          <span id="stepText">Step 1 of 3: Personal Information</span>
          <button type="button" class="reg-help-open" data-bs-toggle="modal"
                  data-bs-target="#teacherRegistrationHelpModal"
                  aria-label="Need help? Guidance for teacher applicants"
                  title="Need help? Guidance for teacher applicants">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
          </button>
        </div>

        <!-- Step 1: Personal Information -->
        <div class="form-step active" id="step1">
          <h5 class="section-title">Personal Information</h5>
          <div class="row g-2">
            <div class="col-md-3 col-6">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" name="first_name" value="<?= old('first_name') ?>" maxlength="100" autocomplete="given-name" oninput="validateNameField(this)" required />
            </div>
            <div class="col-md-4 col-6">
              <label class="form-label" for="middle_name">Middle Name *</label>
              <?php /* The checkbox is an append on the input, not a row of its own
                       and not a second item on the label line. */ ?>
              <div class="input-group">
                <input type="text" class="form-control" name="middle_name" id="middle_name" value="<?= esc($middleNameValue) ?>" maxlength="100" autocomplete="additional-name" oninput="validateNameField(this)" minlength="2" <?= $noMiddleName ? 'disabled' : 'required' ?> />
                <label class="input-group-text no-middle-name-check" for="no_middle_name">
                  <input type="checkbox" name="<?= NO_MIDDLE_NAME_POST_KEY ?>" id="no_middle_name" value="<?= no_middle_name_rule() ?>" onchange="toggleNoMiddleName()" <?= $noMiddleName ? 'checked' : '' ?>>
                  <span>No middle name</span>
                </label>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" name="last_name" value="<?= old('last_name') ?>" maxlength="100" autocomplete="family-name" oninput="validateNameField(this)" required />
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Suffix</label>
              <select class="form-select" name="suffix">
                <option value="">None</option>
                <?php foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'] as $suffixOption): ?>
                  <option value="<?= esc($suffixOption) ?>" <?= old('suffix') === $suffixOption ? 'selected' : '' ?>><?= esc($suffixOption) ?></option>
                <?php endforeach; ?>
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
              <?php /* Both bounds come from the helpers, so the browser's own date
                       picker cannot offer a date the server would reject. The old
                       markup only had `max`, which meant it silently accepted a
                       date of birth over a century ago. */ ?>
              <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= old('date_of_birth') ?>"
                min="<?= esc(teacher_dob_min()) ?>" max="<?= esc(teacher_dob_max()) ?>" required />
              <div class="age-indicator" id="ageIndicator" hidden>
                <i class="bi bi-person-bounding-box age-indicator-icon" aria-hidden="true"></i>
                <span class="age-indicator-age" id="ageIndicatorAge"></span>
                <span class="age-indicator-status" id="ageIndicatorStatus"></span>
              </div>
            </div>
            <?php $civilStatus = $otherChoices['civil_status']; ?>
            <div class="col-md-3 col-6">
              <label class="form-label" for="civil_status">Civil Status</label>
              <select class="form-select" name="civil_status" id="civil_status" onchange="toggleOtherChoice('civil_status', 'civilStatusOtherWrap', 'civil_status_other')">
                <option value="">Select</option>
                <?php foreach ($civilStatus['options'] as $civilStatusOption): ?>
                  <option value="<?= esc($civilStatusOption) ?>" <?= old('civil_status') === $civilStatusOption ? 'selected' : '' ?>><?= esc($civilStatusOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label" for="religion">Religion *</label>
              <select class="form-select" name="religion" id="religion" required onchange="toggleReligionOther()">
                <option value="">Select religion</option>
                <?php foreach ($religionOptions as $religionOption): ?>
                  <option value="<?= esc($religionOption) ?>" <?= $religionValue === $religionOption ? 'selected' : '' ?>><?= esc($religionOption) ?></option>
                <?php endforeach; ?>
                <option value="<?= esc(religion_other_option()) ?>" <?= $religionIsOther ? 'selected' : '' ?>>Other (please specify)</option>
              </select>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label">PhilSys Number (National ID)</label>
              <input type="text" class="form-control" id="philsys_number" name="philsys_number" value="<?= old('philsys_number') ?>"
                maxlength="12" inputmode="numeric" placeholder="12-digit PhilSys ID" autocomplete="off"
                oninput="maskDigitsField(this, 12)" />
              <div class="form-text">12 digits, no spaces or dashes.</div>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label">TIN</label>
              <input type="text" class="form-control" id="tin" name="tin" value="<?= old('tin') ?>"
                maxlength="11" inputmode="numeric" placeholder="000-000-000" autocomplete="off"
                oninput="maskTinField(this)" />
              <div class="form-text">Format: 999-999-999</div>
            </div>
          </div>

          <!-- ===== Conditional follow-ups =====
               Every field a selector can reveal lives in this one full-width row
               rather than inside the column that triggers it.

               They used to be nested in their own column, which is what made the
               form look broken: picking an "Other" value revealed a tall, narrow
               stack inside a third of the width, so that one column grew far past
               its neighbours and the step jumped.

               Revealing a field now extends the form downward by one predictable,
               horizontal band, and the columns above stay exactly the same height
               whatever the applicant picked. -->
          <div class="col-12 col-12">
            <div class="form-extras">
              <div class="form-extras__group form-extras__group--single<?= $civilStatus['isOther'] ? ' is-visible' : '' ?>" id="civilStatusOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="civil_status_other">Specify Civil Status *</label>
                  <input type="text" class="form-control" name="civil_status_other" id="civil_status_other"
                         value="<?= $civilStatus['isOther'] ? esc(old($civilStatus['field'] . '_other', $civilStatus['value'])) : '' ?>"
                         data-field-label="Specify Civil Status"
                         maxlength="30" autocomplete="off"
                         <?= $civilStatus['isOther'] ? 'required' : 'disabled' ?> />
                </div>
              </div>

              <div class="form-extras__group form-extras__group--single<?= $religionIsOther ? ' is-visible' : '' ?>" id="religionOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="religion_other">Specify Religion *</label>
                  <input type="text" class="form-control" name="religion_other" id="religion_other"
                         value="<?= $religionIsOther ? esc($religionValue) : '' ?>"
                         data-field-label="Specify Religion"
                         maxlength="50" placeholder="e.g. Aglipayan Church" autocomplete="off"
                         <?= $religionIsOther ? 'required' : 'disabled' ?> />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Contact Information -->
        <div class="form-step" id="step2">
          <h5 class="section-title">Contact Information</h5>
          <div class="row g-3">
            <div class="col-md-6 col-12">
              <label class="form-label">Email Address *</label>
              <input type="email" class="form-control" name="email" value="<?= old('email') ?>"
                maxlength="254" inputmode="email" autocomplete="email" placeholder="teacher@lphs.edu" required />
              <div class="form-text">This will be your username when signing in.</div>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label">Contact Number *</label>
              <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= old('contact_number') ?>"
                maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX" required />
              <div class="form-text">Format: 09XXXXXXXXX (11 digits, starts with 09)</div>
            </div>
            <div class="col-md-12 col-12">
              <label class="form-label">Address *</label>
              <input type="hidden" name="address" id="address" maxlength="500" value="<?= old('address') ?>">
              <div data-loc-group="address" data-loc-field="address" data-loc-required data-loc-label="Address"></div>
            </div>
          </div>
        </div>
        <!-- Step 3: Employment & Account -->
        <div class="form-step" id="step3">
          <h5 class="section-title">Employment Information</h5>
          <div class="row g-3">
            <?php $position = $otherChoices['position']; ?>
            <div class="col-md-4 col-6">
              <label class="form-label" for="position">Position *</label>
              <select class="form-select" name="position" id="position" required onchange="toggleOtherChoice('position', 'positionOtherWrap', 'position_other')">
                <option value="">Select</option>
                <?php foreach ($position['options'] as $positionOption): ?>
                  <option value="<?= esc($positionOption) ?>" <?= old('position') === $positionOption ? 'selected' : '' ?>><?= esc($positionOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php $teachingArea = $otherChoices['subjects']; ?>
            <div class="col-md-4 col-6">
              <label class="form-label" for="subjects">Teaching Area *</label>
              <select class="form-select" name="subjects" id="subjects" required onchange="toggleOtherChoice('subjects', 'subjectsOtherWrap', 'subjects_other')">
                <option value="">Select</option>
                <?php foreach ($teachingArea['options'] as $subjectOption): ?>
                  <option value="<?= esc($subjectOption) ?>" <?= old('subjects') === $subjectOption ? 'selected' : '' ?>><?= esc($subjectOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php $designation = $otherChoices['designation']; ?>
            <div class="col-md-4 col-6">
              <label class="form-label" for="designation">Designation</label>
              <select class="form-select" name="designation" id="designation" onchange="toggleOtherChoice('designation', 'designationOtherWrap', 'designation_other')">
                <option value="">Select</option>
                <?php foreach ($designation['options'] as $designationOption): ?>
                  <option value="<?= esc($designationOption) ?>" <?= old('designation') === $designationOption ? 'selected' : '' ?>><?= esc($designationOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 col-6">
              <label class="form-label" for="license_number">PRC License Number</label>
              <input type="text" class="form-control" id="license_number" name="license_number" value="<?= old('license_number') ?>"
                maxlength="7" inputmode="numeric" placeholder="7 digits" autocomplete="off"
                oninput="maskDigitsField(this, 7)" />
              <div class="form-text">Exactly 7 digits (optional if not yet available)</div>
            </div>
            <div class="col-md-4 col-6">
              <label class="form-label" for="prc_specialization">PRC Specialization</label>
              <input type="text" class="form-control" id="prc_specialization" name="prc_specialization" value="<?= old('prc_specialization') ?>" maxlength="100" autocomplete="off" />
            </div>
            <div class="col-md-4 col-6">
              <label class="form-label" for="baccalaureate_degree">Baccalaureate Degree</label>
              <input type="text" class="form-control" id="baccalaureate_degree" name="baccalaureate_degree" value="<?= old('baccalaureate_degree') ?>" maxlength="255" placeholder="e.g. BEED" autocomplete="off" />
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label" for="masters_degree">Master's Degree</label>
              <input type="text" class="form-control" id="masters_degree" name="masters_degree" value="<?= old('masters_degree') ?>" maxlength="255" autocomplete="off" />
            </div>
          </div>

          <!-- The three employment "Other" boxes, in the same full-width band the
               student form uses. One band for all three, so revealing one extends
               the step downward by the same predictable amount every time. -->
          <div class="col-12 col-12">
            <div class="form-extras">
              <div class="form-extras__group form-extras__group--single<?= $position['isOther'] ? ' is-visible' : '' ?>" id="positionOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="position_other">Specify Position *</label>
                  <input type="text" class="form-control" name="position_other" id="position_other"
                         value="<?= $position['isOther'] ? esc(old('position_other', $position['value'])) : '' ?>"
                         data-field-label="Specify Position"
                         maxlength="100" placeholder="e.g. Instructor II" autocomplete="off"
                         <?= $position['isOther'] ? 'required' : 'disabled' ?> />
                  <div class="form-text">The exact title on your appointment letter.</div>
                </div>
              </div>

              <div class="form-extras__group form-extras__group--single<?= $teachingArea['isOther'] ? ' is-visible' : '' ?>" id="subjectsOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="subjects_other">Specify Teaching Area *</label>
                  <input type="text" class="form-control" name="subjects_other" id="subjects_other"
                         value="<?= $teachingArea['isOther'] ? esc(old('subjects_other', $teachingArea['value'])) : '' ?>"
                         data-field-label="Specify Teaching Area"
                         maxlength="100" placeholder="e.g. Science - Grade 3" autocomplete="off"
                         <?= $teachingArea['isOther'] ? 'required' : 'disabled' ?> />
                </div>
              </div>

              <div class="form-extras__group form-extras__group--single<?= $designation['isOther'] ? ' is-visible' : '' ?>" id="designationOtherWrap">
                <div class="form-extras__item">
                  <label class="form-label" for="designation_other">Specify Designation *</label>
                  <input type="text" class="form-control" name="designation_other" id="designation_other"
                         value="<?= $designation['isOther'] ? esc(old('designation_other', $designation['value'])) : '' ?>"
                         data-field-label="Specify Designation"
                         maxlength="100" placeholder="e.g. Subject Area Specialist" autocomplete="off"
                         <?= $designation['isOther'] ? 'required' : 'disabled' ?> />
                </div>
              </div>
            </div>
          </div>

          <h5 class="section-title mt-3">Account Information</h5>
          <div class="row g-3">
            <div class="col-md-6 col-12">
              <label class="form-label" for="password">Password *</label>
              <div class="position-relative">
                <input type="password" class="form-control" name="password" id="password"
                       minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                       autocomplete="new-password" required data-password-indicator />
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
                <input type="password" class="form-control" name="password_confirm" id="password_confirm" minlength="8" autocomplete="new-password" required />
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
    </div>
  </div>
</div>

<!-- Age requirement dialog (branded, opened on Next).

     A sibling of .register-container rather than a descendant, which is
     load-bearing rather than tidiness: .register-card has
     backdrop-filter: blur(25px), overflow: hidden and position: relative with
     z-index: 1. Between them those three create a stacking context AND make the
     card the containing block for any position: fixed descendant, so a dialog
     nested inside would be resolved against the card rather than the viewport.
     This one is a hand-rolled fixed overlay rather than a Bootstrap modal, so it
     sits above the card on its own z-index and is never clipped. -->
<div class="age-modal-overlay" id="ageModalOverlay" hidden onclick="closeAgeModal()"></div>
<div class="age-modal" id="ageModal" role="dialog" aria-modal="true" aria-labelledby="ageModalTitle" hidden>
  <div class="age-modal-header">
    <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="age-modal-logo">
    <div>
      <div class="age-modal-header-title">Cauayan South Central School</div>
      <div class="age-modal-header-sub">Teacher Registration &middot; Age Requirement Verification</div>
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
        <span>Your age</span>
        <strong id="ageModalAgeChip">&ndash;</strong>
      </div>
      <div class="age-modal-chip">
        <span>Minimum age</span>
        <strong id="ageModalMinChip">&ndash;</strong>
      </div>
    </div>

    <p class="age-modal-note">
      A teaching appointment requires a minimum age of
      <?= esc(teacher_minimum_age()) ?>. This is a legal requirement rather than
      a school policy, so it cannot be waived at the office &mdash; the applicant
      has to be old enough to be appointed.
    </p>
  </div>
  <div class="age-modal-footer">
    <button type="button" class="age-modal-btn age-modal-btn--ok" id="ageModalOk" onclick="closeAgeModal()">OK</button>
  </div>
  <div class="age-modal-brand">Mabini Street, District I, Cauayan City, Isabela &middot; CSCS Tap n Track</div>
</div>

<script>
let currentStep = <?= (int) $errorStep ?>;
const totalSteps = 3;
const stepTitles = [
  'Personal Information',
  'Contact Information',
  'Employment & Account'
];

/* Server-side constants, emitted from the same helpers the controller validates
   with. If teacher_minimum_age() is ever raised, the browser follows
   automatically rather than drifting to a hard-coded 18. */
const TEACHER_MIN_AGE = <?= json_encode(teacher_minimum_age()) ?>;
const TEACHER_OLDEST_AGE = <?= json_encode(teacher_oldest_age()) ?>;
const TEACHER_DOB_MIN = <?= json_encode(teacher_dob_min()) ?>;
const TEACHER_DOB_MAX = <?= json_encode(teacher_dob_max()) ?>;
const TEACHER_OTHER_OPTION = <?= json_encode(teacher_other_option()) ?>;

function showStep(step) {
  document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
  const target = document.getElementById('step' + step);
  if (target) {
    target.classList.add('active');
  }
  document.getElementById('stepText').textContent = `Step ${step} of ${totalSteps}: ${stepTitles[step - 1]}`;
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
    <div class="alert-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please Check Your Input</div>
    <div class="alert-message">${message}</div>
    <button type="button" class="alert-close">OK</button>
  `;

  overlay.appendChild(alertBox);
  document.body.appendChild(overlay);

  alertBox.querySelector('.alert-close').addEventListener('click', function () {
    document.body.removeChild(overlay);
  });
}

function showSubmittingNotification() {
  const overlay = document.createElement('div');
  overlay.className = 'alert-overlay';

  const alertBox = document.createElement('div');
  alertBox.className = 'custom-alert';
  alertBox.innerHTML = `
    <div class="alert-title" style="color: #16a34a;"><i class="bi bi-hourglass-split me-2"></i>Submitting</div>
    <div class="alert-message">Please wait while we submit your registration...</div>
  `;

  overlay.appendChild(alertBox);
  document.body.appendChild(overlay);
}

function getFieldLabel(field) {
  // A companion control can name itself when the column heading is not
  // specific enough (e.g. the religion "Other" textbox).
  if (field.dataset && field.dataset.fieldLabel) {
    return field.dataset.fieldLabel;
  }

  const container = field.closest('.col-md-3, .col-md-4, .col-md-6, .col-md-12');
  const label = container ? container.querySelector('label') : null;
  return label ? label.textContent.replace('*', '').trim() : (field.name || 'Field');
}

function isFieldEmpty(field) {
  return !field.value || field.value.trim() === '';
}
function validateStep(stepNumber) {
  const stepElement = document.getElementById('step' + stepNumber);
  if (!stepElement) {
    return { valid: true };
  }

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
        return { valid: false, message: 'Please enter a valid email address.<br><br>Example: <strong>teacher@example.com</strong>' };
      }
    }
  }

  if (stepNumber === 1) {
    const philsysField = stepElement.querySelector('input[name="philsys_number"]');
    if (philsysField && philsysField.value && digitCount(philsysField.value) !== 12) {
      return { valid: false, message: 'PhilSys number must be exactly 12 digits.<br><br>Example: <strong>123456789012</strong>' };
    }

    const tinField = stepElement.querySelector('input[name="tin"]');
    if (tinField && tinField.value && digitCount(tinField.value) !== 9) {
      return { valid: false, message: 'TIN must be exactly 9 digits.<br><br>Format: <strong>999-999-999</strong>' };
    }

    // A filled middle name must be 2+ characters, matching the server rule: a lone
    // letter — including a padded "J " — is rejected. Ticking "No middle name"
    // clears and disables the field, so the check is skipped then; unticked, the
    // field is required and the empty-field pass above reports it by name.
    const middleNameField = stepElement.querySelector('input[name="middle_name"]');
    if (middleNameField && middleNameField.value.trim() !== '' && middleNameField.value.trim().length < 2) {
      return { valid: false, message: 'Middle Name must be at least 2 characters long. Tick the "No middle name" box if you do not have one.' };
    }

    // Same bound the server enforces, so the applicant is stopped at the field
    // rather than after a full three-step submit.
    const dobProblem = dobValidationMessage();
    if (dobProblem) {
      return { valid: false, message: dobProblem };
    }
  }

  if (stepNumber === 2) {
    const phoneField = stepElement.querySelector('input[name="contact_number"]');
    if (phoneField && phoneField.value && !isValidMobileNumber(phoneField.value)) {
      return { valid: false, message: 'Contact number must be 11 digits and start with 09.<br><br>Example: <strong>09171234567</strong>' };
    }
  }

  if (stepNumber === 3) {
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    const licenseField = stepElement.querySelector('input[name="license_number"]');

    if (licenseField && licenseField.value && digitCount(licenseField.value) !== 7) {
      return { valid: false, message: 'PRC License Number must be exactly 7 digits.' };
    }

    if (password && password.value && password.value.length < 8) {
      return { valid: false, message: 'Password must be at least 8 characters long.' };
    }

    if (password && passwordConfirm && password.value !== passwordConfirm.value) {
      return { valid: false, message: 'Passwords do not match. Please make sure both password fields are identical.' };
    }
  }

  // A revealed "Other" box is only `required` while its dropdown still says
  // "Other", so the empty-field pass above already covers it — but only while the
  // box is on screen. This catches the case where a stale required box is left
  // over from a choice the applicant has since changed, which would otherwise
  // report a field they can no longer see.
  const staleOther = findStaleOtherBox();
  if (staleOther) {
    return { valid: false, message: staleOther };
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
  return true;
}

function validateAndNext() {
  const result = validateStep(currentStep);
  if (!result.valid) {
    showCustomAlert(result.message);
    return;
  }

  // Step 1 additionally enforces the minimum age, and shows the branded dialog
  // rather than the plain alert because the applicant cannot fix it by changing
  // the date: the floor is a legal requirement for the appointment.
  if (currentStep === 1) {
    const age = teacherCurrentAge();
    if (age >= 0 && age < TEACHER_MIN_AGE) {
      showAgeModal({
        name: teacherNameDisplay(),
        age: age,
        minAge: TEACHER_MIN_AGE
      });
      return;
    }
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
  // Letters, spaces, hyphens and apostrophes only
  input.value = input.value.replace(/[^a-zA-Z\s\-']/g, '');
}

// Digits-only fields (PRC license number, PhilSys number)
function maskDigitsField(input, maxLen) {
  input.value = input.value.replace(/\D/g, '').slice(0, maxLen);
}

// TIN is always displayed as 999-999-999
function maskTinField(input) {
  const digits = input.value.replace(/\D/g, '').slice(0, 9);

  if (digits.length <= 3) {
    input.value = digits;
  } else if (digits.length <= 6) {
    input.value = digits.slice(0, 3) + '-' + digits.slice(3);
  } else {
    input.value = digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
  }
}

// Mobile numbers are always 09XXXXXXXXX (11 digits, plain digits only).
// phone-input.js normally owns the input; this wrapper covers pre-submit
// normalisation of restored (old-input) values before that script loads.
function maskPhoneField(input) {
  if (window.PhoneInput) {
    input.value = window.PhoneInput.normalise(input.value);
    return;
  }
  input.value = input.value.replace(/\D/g, '').slice(0, 11);
}

// Counts only the digits so masked values can be validated reliably.
function digitCount(value) {
  return (value || '').replace(/\D/g, '').length;
}

// A Philippine mobile number must be exactly 11 digits and start with 09.
function isValidMobileNumber(value) {
  const digits = (value || '').replace(/\D/g, '');
  return digits.length === 11 && digits.startsWith('09');
}

/* ===== "Other" companion boxes =====

   One shared implementation for every dropdown that offers an escape hatch, so
   Position, Teaching Area, Designation, Civil Status and Religion cannot each
   grow their own hand-copied version that then has to be kept in step by hand.
   Mirrors the student form's toggleOtherChoice() exactly. */
function toggleOtherChoice(selectId, wrapId, inputId, otherValue) {
  const select = document.getElementById(selectId);
  const wrapper = document.getElementById(wrapId);
  const input = document.getElementById(inputId);

  if (!select || !wrapper || !input) {
    return;
  }

  const isOther = select.value === (otherValue || TEACHER_OTHER_OPTION);

  wrapper.classList.toggle('is-visible', isOther);
  // Disabled while hidden, so a value typed before the choice was changed back
  // can never be submitted against a selection that no longer wants it.
  input.required = isOther;
  input.disabled = !isOther;
}

// The religion selector resolves through its own server-side helpers (a
// varchar(50) column), but the reveal itself is the same control.
function toggleReligionOther() {
  toggleOtherChoice('religion', 'religionOtherWrap', 'religion_other', 'Other');
}

/* ===== "No middle name" =====
   Ticked: the field is cleared, disabled (so no stale value is ever submitted)
   and unrequired. Unticked: required, and at least two characters. Identical
   policy to the student form, and enforced the same way on the server. */
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
function teacherCurrentAge() {
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

function teacherNameDisplay() {
  const parts = ['first_name', 'middle_name', 'last_name']
    .map(function (name) {
      const field = document.querySelector('[name="' + name + '"]');
      return field ? field.value.trim() : '';
    })
    .filter(Boolean);

  return parts.length ? parts.join(' ') : 'This applicant';
}

// null when the date of birth is acceptable, otherwise the message to show.
// Mirrors teacher_dob_validation_rule() on the server, so the applicant is
// stopped at the field rather than after a full three-step submit.
function dobValidationMessage() {
  const dobField = document.getElementById('date_of_birth');
  if (!dobField || !dobField.value) {
    return null;
  }

  const raw = String(dobField.value).trim();
  if (!parseBirthDate(raw)) {
    return 'Please enter a valid date of birth in the past.';
  }

  if (raw > TEACHER_DOB_MAX) {
    return 'The date of birth cannot be in the future.';
  }

  if (raw < TEACHER_DOB_MIN) {
    return 'Please check the year. The date of birth entered is more than ' + TEACHER_OLDEST_AGE + ' years ago.';
  }

  const age = teacherCurrentAge();
  if (age >= 0 && age < TEACHER_MIN_AGE) {
    return 'You must be at least ' + TEACHER_MIN_AGE + ' years old to hold a teaching appointment. '
      + 'The date of birth entered makes you ' + ageText(age) + '.';
  }

  return null;
}

// Catches a reveal box left `required` after its dropdown was changed away from
// "Other", which would otherwise block a submit over a field the applicant can
// no longer see.
function findStaleOtherBox() {
  const pairs = [
    ['position', 'position_other'],
    ['subjects', 'subjects_other'],
    ['designation', 'designation_other'],
    ['civil_status', 'civil_status_other'],
    ['religion', 'religion_other']
  ];

  for (let i = 0; i < pairs.length; i++) {
    const select = document.getElementById(pairs[i][0]);
    const input = document.getElementById(pairs[i][1]);
    if (select && input && input.required && select.value !== TEACHER_OTHER_OPTION) {
      return 'Please finish or clear the box still open under a changed selection.';
    }
  }

  return null;
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
}

function showAgeModal(state) {
  const modal = document.getElementById('ageModal');
  const overlay = document.getElementById('ageModalOverlay');
  if (!modal) {
    return;
  }

  const titleText = document.getElementById('ageModalTitleText');
  const message = document.getElementById('ageModalMessage');
  const ageChip = document.getElementById('ageModalAgeChip');
  const minChip = document.getElementById('ageModalMinChip');

  modal.classList.add('is-blocked');

  if (titleText) {
    titleText.textContent = 'Age Requirement Not Met';
  }
  if (message) {
    message.textContent = state.name + ' is ' + ageText(state.age) + '. A teaching appointment requires a minimum age of '
      + state.minAge + ', so ' + state.name + ' is ' + (state.minAge - state.age) + ' year'
      + ((state.minAge - state.age) === 1 ? '' : 's') + ' below it. This is a legal requirement and cannot be waived at the school office.';
  }
  if (ageChip) { ageChip.textContent = ageText(state.age); }
  if (minChip) { minChip.textContent = state.minAge + ' years old'; }

  if (overlay) {
    overlay.hidden = false;
  }
  modal.hidden = false;
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
  if (!dobField || !dobField.value) {
    return;
  }

  const raw = String(dobField.value).trim();
  const age = teacherCurrentAge();

  if (age < 0) {
    indicator.hidden = false;
    indicator.classList.add('is-blocked');
    ageEl.textContent = 'Invalid date';
    statusEl.textContent = raw > TEACHER_DOB_MAX
      ? 'The date of birth cannot be in the future.'
      : 'Date of birth must be a valid past date.';
    return;
  }

  indicator.hidden = false;
  ageEl.textContent = ageText(age);

  if (raw < TEACHER_DOB_MIN) {
    indicator.classList.add('is-blocked');
    statusEl.textContent = 'More than ' + TEACHER_OLDEST_AGE + ' years ago — please check the year.';
    return;
  }

  if (age < TEACHER_MIN_AGE) {
    indicator.classList.add('is-blocked');
    statusEl.textContent = 'Below the minimum age of ' + TEACHER_MIN_AGE + ' — not eligible.';
    return;
  }

  indicator.classList.add('is-meets');
  statusEl.textContent = 'Meets the minimum age of ' + TEACHER_MIN_AGE + '.';
}

document.addEventListener('DOMContentLoaded', function () {
  showStep(currentStep);

  // Re-open every reveal box whose dropdown says "Other", so a redisplayed form
  // (a server error, or the applicant switching back) shows what it stored. Done
  // here rather than relying on the server-rendered class alone, so the disabled
  // and required state of each box is correct too.
  ['position', 'subjects', 'designation', 'civil_status'].forEach(function (id) {
    toggleOtherChoice(id, id + 'OtherWrap', id + '_other');
  });
  toggleReligionOther();
  toggleNoMiddleName();

  // Live age indicator next to the date of birth.
  const dobField = document.getElementById('date_of_birth');
  if (dobField) {
    dobField.addEventListener('input', updateAgeIndicator);
    dobField.addEventListener('change', updateAgeIndicator);
  }
  updateAgeIndicator();

  // Escape closes the age dialog, and Tab cannot walk out of it: without this the
  // keyboard focus stays on the form behind the overlay, which is still clickable
  // by tab even though it looks blocked.
  document.addEventListener('keydown', function (event) {
    const modal = document.getElementById('ageModal');
    if (!modal || modal.hidden) {
      return;
    }
    if (event.key === 'Escape') {
      closeAgeModal();
    }
  });

  // Normalise any values restored from old input so masked fields always match
  // the format the server validates (999-999-999 and 7/12 digit numbers).
  [
    { id: 'tin', fn: maskTinField },
    { id: 'contact_number', fn: maskPhoneField },
    { id: 'license_number', fn: function (el) { maskDigitsField(el, 7); } },
    { id: 'philsys_number', fn: function (el) { maskDigitsField(el, 12); } }
  ].forEach(function (field) {
    const el = document.getElementById(field.id);
    if (el && el.value) {
      field.fn(el);
    }
  });

  const passwordField = document.getElementById('password');
  const passwordConfirmField = document.getElementById('password_confirm');
  const passwordMatchHint = document.getElementById('password-match-hint');

  // The requirement list is shared markup, so only the field border is coloured
  // here; password-policy.js ticks off each rule.
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
        passwordMatchHint.style.color = '#fca5a5';
        passwordMatchHint.textContent = 'Passwords do not match';
        passwordConfirmField.style.borderColor = '#dc2626';
      } else {
        passwordMatchHint.style.color = '#86efac';
        passwordMatchHint.textContent = 'Passwords match';
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

  const form = document.getElementById('teacherRegistrationForm');
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // A field left showing only the untouched "09" prefix counts as empty.
    const phoneField = document.getElementById('contact_number');
    if (phoneField && phoneField.value === '09') {
      phoneField.value = '';
    }

    if (!validateAllSteps()) {
      return;
    }

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> SUBMITTING...';
    showSubmittingNotification();

    HTMLFormElement.prototype.submit.call(form);
  });
});

// Normalise any pre-filled values (from old input after a failed submit) so the
// masked formats are always respected, even before the user types again.
document.addEventListener('DOMContentLoaded', function () {
  const prefilled = [
    ['#tin', function (el) { maskTinField(el); }],
    ['#philsys_number', function (el) { maskDigitsField(el, 12); }],
    ['#license_number', function (el) { maskDigitsField(el, 7); }],
    ['#contact_number', function (el) { maskPhoneField(el); }],
  ];

  prefilled.forEach(function (entry) {
    const field = document.querySelector(entry[0]);
    if (field && field.value) {
      entry[1](field);
    }
  });
});
</script>
<script src="<?= asset_url('js/password-policy.js') ?>"></script>

<?php /* The guidance dialog sits OUTSIDE .register-container, as a sibling and
         not a descendant, for the same reason the age dialog does: .register-card
         has backdrop-filter, overflow: hidden and position: relative with a
         z-index, which together make it a stacking context AND the containing
         block for any position: fixed descendant. A Bootstrap modal nested inside
         would be resolved against the card rather than the viewport, so its
         z-index would be trapped and overflow: hidden would clip it. */ ?>
<?= view('partials/teacher_registration_help_modal') ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('teacherRegistrationHelpModal');
  if (!el || typeof bootstrap === 'undefined') { return; }

  // Once per browser session, not once ever, and under a DIFFERENT key from the
  // student form's. A parent who has already read the enrolment guidance should
  // not be shown it again when they open the teacher form in the same visit.
  var KEY = 'cscs.teacher-reg-help-seen';
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
