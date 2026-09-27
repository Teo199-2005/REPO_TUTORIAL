<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 mb-1"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add New Teacher</h1>
    <p class="text-muted mb-0">Register a new school personnel in the system</p>
  </div>
  <a href="<?= base_url('admin/teachers') ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back to Teachers
  </a>
</div>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<?php
$validationErrors = session()->getFlashdata('validation');
if ($validationErrors instanceof \CodeIgniter\Validation\Validation):
?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($validationErrors->getErrors() as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<style>
  .teacher-create-card .card-header {
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
    border-bottom: 1px solid #e2e8f0;
  }
  .teacher-create-card .card-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e3a8a;
  }
  .teacher-create-card .btn-primary {
    padding: .55rem 1.5rem;
    font-weight: 700;
  }
</style>

<div class="card shadow-sm teacher-create-card">
  <div class="card-header py-3">
    <h5 class="card-title mb-1"><i class="bi bi-file-earmark-person text-primary me-2"></i>Teacher Personnel Record</h5>
    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Complete all required fields per school personnel data sheet</small>
  </div>
  <div class="card-body">
    <form method="post" action="<?= base_url('admin/teachers/store') ?>">
      <?= csrf_field() ?>
      <?= view('admin/partials/teacher_personnel_fields', ['mode' => 'create', 'showAccount' => true]) ?>
      <div class="d-flex justify-content-end gap-2 pt-3 mt-3 border-top">
        <a href="<?= base_url('admin/teachers') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-circle"></i> Create Teacher
        </button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
