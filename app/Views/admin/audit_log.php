<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
  helper('audit_display');

  $filters        = $filters ?? [];
  $rows           = $rows ?? [];
  $summary        = $summary ?? ['total' => 0, 'failures' => 0, 'actors' => 0];
  $categoryLabels = $category_labels ?? [];
  $perPageChoices = $per_page_choices ?? [25, 50, 100, 200];

  // Query string used by sort/pagination links: current filters, minus page.
  $queryParams = array_filter([
    'q'             => $filters['q'] ?? '',
    'action'        => $filters['action'] ?? '',
    'category'      => $filters['category'] ?? '',
    'status'        => $filters['status'] ?? '',
    'resource_type' => $filters['resource_type'] ?? '',
    'actor'         => ! empty($filters['actor']) ? (int) $filters['actor'] : '',
    'date_from'     => $filters['date_from'] ?? '',
    'date_to'       => $filters['date_to'] ?? '',
    'sort'          => $filters['sort'] ?? 'created_at',
    'dir'           => $filters['dir'] ?? 'desc',
    'per_page'      => $per_page ?? 50,
  ], static fn ($value) => $value !== '' && $value !== null);

  $pageUrl   = static fn (int $page): string => base_url('admin/audit-log') . '?' . http_build_query(array_merge($queryParams, ['page' => $page]));
  $exportUrl = base_url('admin/audit-log/export') . '?' . http_build_query($queryParams);
  $sortUrl   = static function (string $column) use ($queryParams, $filters): string {
      $dir = (($filters['sort'] ?? '') === $column && ($filters['dir'] ?? 'desc') === 'asc') ? 'desc' : 'asc';
      return base_url('admin/audit-log') . '?' . http_build_query(array_merge($queryParams, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
  };
  $sortIcon = static function (string $column) use ($filters): string {
      if (($filters['sort'] ?? '') !== $column) {
          return '<i class="bi bi-arrow-down-up text-muted ms-1"></i>';
      }
      return ($filters['dir'] ?? 'desc') === 'asc'
          ? '<i class="bi bi-sort-up ms-1"></i>'
          : '<i class="bi bi-sort-down ms-1"></i>';
  };

  // The action dropdown keeps the raw code as the option value (so the query is
  // unchanged) but shows administrators a plain-English label.
  $groupedActions = audit_display_grouped_actions($actions ?? []);

  $auditFilterValues = $filters;
  $auditFilterNames = ['q', 'action', 'category', 'status', 'resource_type', 'actor', 'date_from', 'date_to'];
  $auditActive      = admin_filter_count_active($auditFilterValues, $auditFilterNames);

  $resourceChoiceList = [admin_filter_option('', 'All items')];
  foreach (($resource_types ?? []) as $type) {
    $resourceChoiceList[] = admin_filter_option((string) $type, audit_display_resource((string) $type)['label']);
  }

  $statusChoiceList = [admin_filter_option('', 'Any result')];
  foreach (['success', 'failure', 'blocked'] as $st) {
    $statusChoiceList[] = admin_filter_option($st, audit_display_status($st)['label']);
  }

  echo view('admin/partials/page_header', ['pageHeader' => [
    'icon'     => 'bi-shield-check',
    'title'    => 'Activity log',
    'subtitle' => 'Every important action in the system is recorded here automatically — who did it, what they did, and when. Entries cannot be edited or deleted from the app.',
    'actions'  => '<a class="btn btn-outline-primary" href="' . esc($exportUrl) . '">'
      . '<i class="bi bi-download"></i> Export CSV</a>'
      . (! empty($is_master_admin)
          ? '<form method="post" action="' . base_url('admin/audit-log/verify') . '" class="d-inline"'
            . ' onsubmit="return confirm(\'Re-check every entry to prove none were secretly changed or deleted?\');">'
            . csrf_field()
            . '<button type="submit" class="btn btn-outline-secondary">'
            . '<i class="bi bi-fingerprint"></i> Verify integrity</button></form>'
          : ''),
  ]]);
?>

<div class="audit-log-page">


<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="audit-stat">
        <div class="audit-stat-icon is-info"><i class="bi bi-activity"></i></div>
        <div>
          <div class="audit-stat-label">Actions today</div>
          <div class="audit-stat-value"><?= number_format((int) ($summary['total'] ?? 0)) ?></div>
          <div class="audit-stat-hint">Recorded since midnight</div>
        </div>
      </div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="audit-stat">
        <div class="audit-stat-icon <?= ((int) ($summary['failures'] ?? 0)) > 0 ? 'is-danger' : 'is-success' ?>">
          <i class="bi <?= ((int) ($summary['failures'] ?? 0)) > 0 ? 'bi-exclamation-triangle' : 'bi-shield-check' ?>"></i>
        </div>
        <div>
          <div class="audit-stat-label">Needs attention</div>
          <div class="audit-stat-value <?= ((int) ($summary['failures'] ?? 0)) > 0 ? 'text-danger' : '' ?>">
            <?= number_format((int) ($summary['failures'] ?? 0)) ?>
          </div>
          <div class="audit-stat-hint">Failed or blocked actions today</div>
        </div>
      </div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="audit-stat">
        <div class="audit-stat-icon is-success"><i class="bi bi-people"></i></div>
        <div>
          <div class="audit-stat-label">People active</div>
          <div class="audit-stat-value"><?= number_format((int) ($summary['actors'] ?? 0)) ?></div>
          <div class="audit-stat-hint">Signed in during the last 24 hours</div>
        </div>
      </div>
    </div></div>
  </div>
</div>

<?php
  $actorChoiceList = [admin_filter_option('', 'Anyone')];
  foreach (($actors ?? []) as $actor) {
    $actorChoiceList[] = admin_filter_option(
      (string) $actor['actor_user_id'],
      ($actor['actor_name'] !== '' ? $actor['actor_name'] : 'User #' . $actor['actor_user_id'])
        . ' (' . (int) $actor['entries'] . ')'
    );
  }

  $actionChoiceGroups = [];
  foreach ($groupedActions as $groupKey => $groupItems) {
    $actionChoiceGroups[] = [
      'label'   => ucfirst(str_replace('_', ' ', (string) $groupKey)),
      'options' => array_map(
        static fn (array $item): array => admin_filter_option((string) $item['value'], (string) $item['label']),
        $groupItems
      ),
    ];
  }

  $perPageChoiceList = [];
  foreach ($perPageChoices as $choice) {
    $perPageChoiceList[] = admin_filter_option((string) $choice, (string) $choice);
  }

  echo view('admin/partials/filter_bar', ['filterBar' => [
    'action'      => base_url('admin/audit-log'),
    'id'          => 'auditFilter',
    'label'       => 'Filter the activity log',
    'resetUrl'    => base_url('admin/audit-log'),
    'activeCount' => admin_filter_count_active(
      $auditFilterValues,
      ['category', 'status', 'resource_type', 'date_from', 'date_to', 'per_page']
    )['total'],
    'totalCount'  => $auditActive['total'],
    'primary'     => [
      [
        'name' => 'q', 'label' => 'Search', 'icon' => 'bi-search', 'type' => 'search',
        'value' => admin_filter_value($auditFilterValues, 'q'), 'placeholder' => 'Name, action or address',
      ],
      [
        'name' => 'action', 'label' => 'What happened', 'icon' => 'bi-lightning-charge',
        'value'  => admin_filter_value($auditFilterValues, 'action'),
        'groups' => $actionChoiceGroups,
      ],
      [
        'name' => 'actor', 'label' => 'Who did it', 'icon' => 'bi-person',
        'value'   => admin_filter_value($auditFilterValues, 'actor'),
        'options' => $actorChoiceList,
      ],
      [
        'name' => 'category', 'label' => 'Type of activity', 'icon' => 'bi-collection',
        'value'   => admin_filter_value($auditFilterValues, 'category'),
        'options' => array_map(
          static fn (string $key): array => admin_filter_option($key, (string) $categoryLabels[$key]),
            array_keys($categoryLabels)
        ),
      ],
    ],
    'advanced'    => [
      [
        'name' => 'status', 'label' => 'Result', 'icon' => 'bi-check2-circle',
        'value'   => admin_filter_value($auditFilterValues, 'status'),
        'options' => $statusChoiceList,
      ],
      [
        'name' => 'resource_type', 'label' => 'Item affected', 'icon' => 'bi-box',
        'value'   => admin_filter_value($auditFilterValues, 'resource_type'),
        'options' => $resourceChoiceList,
      ],
      [
        'name' => 'date_from', 'label' => 'From date', 'icon' => 'bi-calendar3', 'type' => 'date',
        'value' => admin_filter_value($auditFilterValues, 'date_from'),
      ],
      [
        'name' => 'date_to', 'label' => 'To date', 'icon' => 'bi-calendar3', 'type' => 'date',
        'value' => admin_filter_value($auditFilterValues, 'date_to'),
      ],
      [
        'name' => 'per_page', 'label' => 'Rows per page', 'icon' => 'bi-list-ul',
        'value'   => admin_filter_value($auditFilterValues, 'per_page', '50'),
        'options' => $perPageChoiceList,
      ],
    ],
  ]]);
?>

<div class="card bg-white border-0 shadow-sm rounded-3">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 admin-table audit-table">
        <thead>
          <tr>
            <th class="border-0" style="width: 150px;">
              <a href="<?= esc($sortUrl('created_at')) ?>" class="text-decoration-none text-dark">When<?= $sortIcon('created_at') ?></a>
            </th>
            <th class="border-0" style="width: 190px;">
              <a href="<?= esc($sortUrl('actor_name')) ?>" class="text-decoration-none text-dark">Who<?= $sortIcon('actor_name') ?></a>
            </th>
            <th class="border-0" style="width: 230px;">
              <a href="<?= esc($sortUrl('action')) ?>" class="text-decoration-none text-dark">What happened<?= $sortIcon('action') ?></a>
            </th>
            <th class="border-0" style="width: 165px;">Item affected</th>
            <th class="border-0" style="width: 110px;">
              <a href="<?= esc($sortUrl('status')) ?>" class="text-decoration-none text-dark">Result<?= $sortIcon('status') ?></a>
            </th>
            <th class="border-0">Details</th>
            <th class="border-0" style="width: 140px;">Where from</th>
            <th class="border-0 text-end" style="width: 90px;">
              <span class="visually-hidden">Open</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows !== []): ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', (string) $row['created_at']);
                if ($date !== false) {
                    $date->setTimezone(new \DateTimeZone('Asia/Manila'));
                }

                $actionD  = audit_display_action((string) ($row['action'] ?? ''));
                $statusD  = audit_display_status((string) ($row['status'] ?? ''));
                $catD     = audit_display_category((string) ($row['category'] ?? ''));
                $actorD   = audit_display_role((string) ($row['actor_role'] ?? ''));
                $actorNm  = trim((string) ($row['actor_name'] ?? ''));
                $hasRes   = ! empty($row['resource_type']);
                $resD     = $hasRes ? audit_display_resource((string) $row['resource_type']) : null;
                $ipD      = audit_display_ip((string) ($row['ip_address'] ?? ''));
                $readable = audit_display_description($row);
                $hasDiff  = ($row['before_json'] ?? null) !== null || ($row['after_json'] ?? null) !== null;
                $initials = $actorNm !== '' ? mb_strtoupper(mb_substr($actorNm, 0, 1)) : '?';
              ?>
              <tr>
                <td class="audit-cell-when">
                  <div class="fw-semibold"><?= esc($date !== false ? $date->format('M j, Y') : (string) $row['created_at']) ?></div>
                  <small class="text-muted"><?= esc($date !== false ? $date->format('g:i:s A') : '') ?></small>
                </td>
                <td>
                  <?php if ($actorNm !== ''): ?>
                    <div class="audit-actor-name">
                      <span class="audit-actor-avatar" aria-hidden="true"><?= esc($initials) ?></span>
                      <span><?= esc($actorNm) ?></span>
                    </div>
                    <?php if (($row['actor_role'] ?? '') !== ''): ?>
                      <span class="audit-pill is-muted mt-1">
                        <i class="bi <?= esc($actorD['icon']) ?>" aria-hidden="true"></i><?= esc($actorD['label']) ?>
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="audit-actor-name text-muted">
                      <span class="audit-actor-avatar" style="background:#f1f5f9;color:#64748b;" aria-hidden="true">
                        <i class="bi bi-incognito"></i>
                      </span>
                      <span>Not signed in</span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="audit-cell-action">
                  <div class="audit-action-label" title="<?= esc($actionD['help']) ?>">
                    <i class="bi <?= esc($actionD['icon']) ?>" aria-hidden="true"></i>
                    <span><?= esc($actionD['label']) ?></span>
                  </div>
                  <span class="audit-pill is-muted mt-1">
                    <i class="bi <?= esc($catD['icon']) ?>" aria-hidden="true"></i><?= esc($catD['label']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($resD !== null): ?>
                    <div class="fw-semibold">
                      <i class="bi <?= esc($resD['icon']) ?> text-muted me-1" aria-hidden="true"></i><?= esc($resD['label']) ?>
                    </div>
                    <?php if (! empty($row['resource_id'])): ?>
                      <small class="text-muted">Reference number <?= esc((string) $row['resource_id']) ?></small>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                </td>
                <td class="audit-cell-status">
                  <span class="audit-pill is-<?= esc($statusD['tone']) ?>" title="<?= esc($statusD['help']) ?>">
                    <i class="bi <?= esc($statusD['icon']) ?>" aria-hidden="true"></i><?= esc($statusD['label']) ?>
                  </span>
                </td>
                <td>
                  <div class="audit-detail-text small"><?= esc($readable) ?></div>
                  <div class="d-flex flex-wrap gap-1 mt-1">
                    <?php if ($hasDiff): ?>
                      <span class="audit-pill is-info">
                        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>Values changed
                      </span>
                    <?php endif; ?>
                    <?php if (($row['action'] ?? '') === 'http.request'): ?>
                      <span class="audit-pill is-muted">
                        <i class="bi bi-cpu" aria-hidden="true"></i>Automatic entry
                      </span>
                    <?php endif; ?>
                  </div>
                  <small class="audit-code mt-1"><?= esc((string) ($row['action'] ?? '')) ?></small>
                </td>
                <td>
                  <div class="fw-semibold small" title="<?= esc($ipD['help']) ?>">
                    <i class="bi <?= esc($ipD['icon']) ?> text-muted me-1" aria-hidden="true"></i><?= esc($ipD['label']) ?>
                  </div>
                  <small class="text-muted"><?= esc((string) ($row['ip_address'] ?? '—')) ?></small>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/audit-log/show/' . (int) $row['id']) ?>"
                     title="Open the full details of this entry">
                    <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Open details</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p class="mb-1 fw-semibold">No activity matches the current filters.</p>
                <p class="mb-0 small">Try widening the date range, or clear the filters to see everything.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted small">
      <?php if (($total ?? 0) > 0): ?>
        Showing <?= (int) $showing_from ?>&ndash;<?= (int) $showing_to ?> of <?= (int) $total ?> entries
      <?php else: ?>
        No entries found
      <?php endif; ?>
    </div>
    <?php if (($total_pages ?? 1) > 1): ?>
      <nav aria-label="Activity log pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= ($current_page ?? 1) <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= esc($pageUrl(max(1, (int) $current_page - 1))) ?>"
               title="Previous page of results" rel="prev">
              <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Previous</span>
            </a>
          </li>
          <?php
            $startPage = max(1, (int) $current_page - 3);
            $endPage   = min((int) $total_pages, $startPage + 6);
          ?>
          <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <li class="page-item <?= $p === (int) $current_page ? 'active' : '' ?>">
              <a class="page-link" href="<?= esc($pageUrl($p)) ?>"
                 <?= $p === (int) $current_page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($current_page ?? 1) >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= esc($pageUrl(min((int) $total_pages, (int) $current_page + 1))) ?>"
               title="Next page of results" rel="next">
              <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Next</span>
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<div class="audit-note mt-3">
  <i class="bi bi-shield-lock" aria-hidden="true"></i>
  <div class="small text-muted">
    <strong class="text-dark">This log cannot be altered.</strong>
    Every entry is digitally signed and linked to the one before it, so if anyone tried to change or remove a record,
    the &ldquo;Verify integrity&rdquo; button above would report it. Entries older than
    <?= (int) ($retention_days ?? 365) ?> days are removed automatically by a scheduled maintenance task.
    <?php if (! empty($oldest_entry)): ?>
      The oldest entry currently stored is from <?= esc((string) $oldest_entry) ?>.
    <?php endif; ?>
    <br>
    <span class="audit-code">php spark audit:prune</span>
    is the only command that can remove old entries, and it can only be run by the server administrator.
  </div>
</div>

<div class="card border-0 shadow-sm mt-3 audit-legend">
  <div class="card-header bg-white">
    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-question-circle me-1 text-primary"></i>How to read this page</h2>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <div class="audit-legend-item">
          <i class="bi bi-lightning-charge"></i>
          <div class="audit-legend-text"><strong>What happened</strong> &mdash; a plain-English description of the action, such as
            &ldquo;Signed in&rdquo; or &ldquo;Student record added&rdquo;. Hover over it for a longer explanation.</div>
        </div>
        <div class="audit-legend-item">
          <i class="bi bi-collection"></i>
          <div class="audit-legend-text"><strong>Type of activity</strong> &mdash; the part of the system the action belongs to,
            such as Students, Settings, or System &amp; backups.</div>
        </div>
        <div class="audit-legend-item">
          <i class="bi bi-box"></i>
          <div class="audit-legend-text"><strong>Item affected</strong> &mdash; what was acted on, for example a Student,
            a Section, or a Database backup.</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="audit-legend-item">
          <i class="bi bi-check2-circle"></i>
          <div class="audit-legend-text"><strong>Result</strong> &mdash; green <em>Success</em>, red <em>Failed</em>, or
            orange <em>Blocked</em>. Only the red and orange ones usually need your attention.</div>
        </div>
        <div class="audit-legend-item">
          <i class="bi bi-globe2"></i>
          <div class="audit-legend-text"><strong>Where from</strong> &mdash; whether the action came from
            <em>This device</em>, the <em>School network</em>, or an <em>Internet connection</em>.</div>
        </div>
        <div class="audit-legend-item">
          <i class="bi bi-cpu"></i>
          <div class="audit-legend-text"><strong>Automatic entry</strong> &mdash; the system recorded that a button or form
            was used. These are normal and appear whenever someone saves something.</div>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /.audit-log-page -->

<?= $this->endSection() ?>
