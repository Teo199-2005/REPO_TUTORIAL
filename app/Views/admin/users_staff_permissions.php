<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-end align-items-center mb-3">
  <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary">Back to dashboard</a>
</div>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= base_url('admin/dashboard/staff-permissions/' . (int) $targetUser->id) ?>">
      <?= csrf_field() ?>
      <p class="text-muted">Select which admin areas this staff member may open.</p>
      <div class="row">
        <?php foreach (admin_valid_page_keys() as $key): ?>
          <div class="col-md-4 mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="pages[]" value="<?= esc($key) ?>"
                id="page_<?= esc($key) ?>"
                <?= in_array($key, $selectedPages, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="page_<?= esc($key) ?>">
                <?= esc(admin_page_label($key)) ?>
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Save</button>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
