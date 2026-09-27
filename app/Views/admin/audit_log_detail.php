<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
  helper('audit_display');

  $entry          = $entry ?? [];
  $before         = $before ?? [];
  $after          = $after ?? [];
  $metadata       = $metadata ?? [];
  $categoryLabels = $category_labels ?? [];

  $date = \DateTime::createFromFormat('Y-m-d H:i:s', (string) ($entry['created_at'] ?? ''));
  if ($date !== false) {
      $date->setTimezone(new \DateTimeZone('Asia/Manila'));
  }

  $actionD  = audit_display_action((string) ($entry['action'] ?? ''));
  $statusD  = audit_display_status((string) ($entry['status'] ?? ''));
  $catD     = audit_display_category((string) ($entry['category'] ?? ''));
  $actorNm  = trim((string) ($entry['actor_name'] ?? ''));
  $actorD   = audit_display_role((string) ($entry['actor_role'] ?? ''));
  $ipD      = audit_display_ip((string) ($entry['ip_address'] ?? ''));
  $hasRes   = ! empty($entry['resource_type']);
  $resD     = $hasRes ? audit_display_resource((string) $entry['resource_type']) : null;
  $readable = audit_display_description($entry);

  $formatValue = static function ($value): string {
      if (is_bool($value)) {
          return $value ? 'yes' : 'no';
      }
      if ($value === null) {
          return 'not set';
      }
      if (is_array($value)) {
          return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
      }
      $value = trim((string) $value);

      return $value === '' ? 'empty' : $value;
  };
?>

<div class="audit-log-page">
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-primary mb-1">
        <i class="bi <?= esc($actionD['icon']) ?> me-2"></i><?= esc($actionD['label']) ?>
      </h1>
      <p class="text-muted mb-0 small"><?= esc($readable) ?></p>
    </div>
    <a href="<?= base_url('admin/audit-log') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to activity log
    </a>
  </div>
  <div class="blue-divider"></div>
</div>

<div class="audit-note mb-3">
  <i class="bi <?= esc($actionD['icon']) ?>" aria-hidden="true"></i>
  <div class="small text-muted"><?= esc($actionD['help']) ?></div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white">
    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-info-circle me-1 text-primary"></i>Summary of this entry</h2>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi bi-calendar3 me-1"></i>When it happened</div>
          <div class="audit-field-value">
            <?= esc($date !== false ? $date->format('F j, Y') : (string) ($entry['created_at'] ?? '')) ?>
          </div>
          <small class="text-muted"><?= esc($date !== false ? $date->format('g:i:s A') : '') ?></small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi bi-person me-1"></i>Who did it</div>
          <?php if ($actorNm !== ''): ?>
            <div class="audit-field-value"><?= esc($actorNm) ?></div>
            <div class="d-flex flex-wrap gap-1 mt-1">
              <span class="audit-pill is-muted"><i class="bi <?= esc($actorD['icon']) ?>"></i><?= esc($actorD['label']) ?></span>
            </div>
          <?php else: ?>
            <div class="audit-field-value text-muted">Not signed in</div>
            <small class="text-muted">The person was not logged in when this happened.</small>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi bi-check2-circle me-1"></i>Result</div>
          <span class="audit-pill is-<?= esc($statusD['tone']) ?>" title="<?= esc($statusD['help']) ?>">
            <i class="bi <?= esc($statusD['icon']) ?>"></i><?= esc($statusD['label']) ?>
          </span>
          <small class="text-muted d-block mt-1"><?= esc($statusD['help']) ?></small>
        </div>
      </div>
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi bi-lightning-charge me-1"></i>What happened</div>
          <div class="audit-field-value"><?= esc($actionD['label']) ?></div>
          <div class="d-flex flex-wrap gap-1 mt-1">
            <span class="audit-pill is-muted"><i class="bi <?= esc($catD['icon']) ?>"></i><?= esc($catD['label']) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi bi-box me-1"></i>Item affected</div>
          <?php if ($resD !== null): ?>
            <div class="audit-field-value">
              <i class="bi <?= esc($resD['icon']) ?> text-muted me-1"></i><?= esc($resD['label']) ?>
            </div>
            <?php if (! empty($entry['resource_id'])): ?>
              <small class="text-muted">Reference number <?= esc((string) $entry['resource_id']) ?></small>
            <?php endif; ?>
          <?php else: ?>
            <div class="audit-field-value text-muted">Nothing specific</div>
            <small class="text-muted">This action was not tied to one record.</small>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-4">
        <div class="audit-field">
          <div class="audit-field-label"><i class="bi <?= esc($ipD['icon']) ?> me-1"></i>Where it came from</div>
          <div class="audit-field-value"><?= esc($ipD['label']) ?></div>
          <small class="text-muted"><?= esc($ipD['help']) ?></small>
          <div class="mt-1"><small class="audit-code"><?= esc((string) ($entry['ip_address'] ?? '—')) ?></small></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($before !== [] || $after !== []): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-left-right me-1 text-primary"></i>What changed</h2>
      <small class="text-muted d-block mt-1">
        The value in each field before the action, and the value it was changed to. Fields that were not touched are not listed.
      </small>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th class="border-0" style="width: 22%;">Information</th>
              <th class="border-0" style="width: 39%;">
                <i class="bi bi-dash-circle text-danger me-1"></i>Before the change
              </th>
              <th class="border-0" style="width: 39%;">
                <i class="bi bi-check-circle text-success me-1"></i>After the change
              </th>
            </tr>
          </thead>
          <tbody>
            <?php
              $fields = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
              sort($fields);
            ?>
            <?php if ($fields === []): ?>
              <tr><td colspan="3" class="text-muted text-center py-4">No individual field values were recorded.</td></tr>
            <?php endif; ?>
            <?php foreach ($fields as $field): ?>
              <tr>
                <td class="audit-field-name"><?= esc(str_replace(['_', '-'], ' ', (string) $field)) ?></td>
                <td class="small text-break">
                  <?php if (array_key_exists($field, $before)): ?>
                    <span class="audit-diff-before"><?= esc($formatValue($before[$field])) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Did not exist</span>
                  <?php endif; ?>
                </td>
                <td class="small text-break">
                  <?php if (array_key_exists($field, $after)): ?>
                    <span class="audit-diff-after"><?= esc($formatValue($after[$field])) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Removed</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($metadata !== []): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0 fw-semibold">
        <i class="bi bi-tools me-1 text-primary"></i>Technical details
        <small class="text-muted fw-normal ms-1">(for support use only)</small>
      </h2>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
              data-bs-target="#auditMeta" aria-expanded="false" aria-controls="auditMeta">
        <i class="bi bi-eye me-1"></i>Show
      </button>
    </div>
    <div class="collapse" id="auditMeta">
      <div class="card-body">
        <dl class="row small mb-3">
          <?php if (! empty($entry['http_method']) || ! empty($entry['route'])): ?>
            <dt class="col-sm-3 text-muted">Screen used</dt>
            <dd class="col-sm-9 mb-2">
              <span class="audit-code"><?= esc((string) ($entry['http_method'] ?? '')) ?></span>
              <span class="audit-code"><?= esc(audit_display_route((string) ($entry['route'] ?? ''))) ?></span>
            </dd>
          <?php endif; ?>
          <?php if (! empty($entry['request_id'])): ?>
            <dt class="col-sm-3 text-muted">Request ID</dt>
            <dd class="col-sm-9 mb-2"><span class="audit-code"><?= esc((string) $entry['request_id']) ?></span></dd>
          <?php endif; ?>
          <dt class="col-sm-3 text-muted">Internal action code</dt>
          <dd class="col-sm-9 mb-2"><span class="audit-code"><?= esc((string) ($entry['action'] ?? '')) ?></span></dd>
          <?php if (! empty($entry['user_agent'])): ?>
            <dt class="col-sm-3 text-muted">Browser</dt>
            <dd class="col-sm-9 mb-2 text-break"><?= esc((string) $entry['user_agent']) ?></dd>
          <?php endif; ?>
        </dl>
        <div class="audit-field-label">Extra information recorded</div>
        <pre class="mb-0 small bg-light p-3 rounded border" style="max-height: 320px; overflow:auto;"><?= esc((string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h2 class="h6 fw-semibold"><i class="bi bi-shield-check me-1 text-primary"></i>Proof this entry has not been changed</h2>
    <p class="text-muted small mb-2">
      Every entry carries a digital fingerprint that is built from the entry itself and the one before it. If anyone
      edited or deleted this record, the fingerprint would stop matching and the &ldquo;Verify integrity&rdquo; button on
      the activity log page would report it.
    </p>
    <div class="small text-break">
      <div class="mb-1">
        <span class="text-muted">Linked to previous entry:</span>
        <span class="audit-code"><?= esc((string) (($entry['prev_hash'] ?? '') !== '' && $entry['prev_hash'] !== null ? $entry['prev_hash'] : 'This is the first entry')) ?></span>
      </div>
      <div>
        <span class="text-muted">This entry&rsquo;s fingerprint:</span>
        <span class="audit-code"><?= esc((string) ($entry['hash'] ?? '')) ?></span>
      </div>
    </div>
  </div>
</div>

</div><!-- /.audit-log-page -->

<?= $this->endSection() ?>
