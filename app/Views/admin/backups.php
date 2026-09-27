<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
  /**
   * Backup & Restore — admin dashboard.
   *
   * Server renders every state (registry, availability, schedule, running
   * operation); the page script only enhances it: AJAX actions, the live
   * progress panel fed by GET admin/backups/status, search/filter/sort and the
   * confirm modals. Every action is also a real form POST, so the page keeps
   * working without JavaScript.
   */
  use App\Libraries\DatabaseBackupService;

  $registryOk   = $registry_ok ?? true;
  $rows         = $rows ?? [];
  $availability = $availability ?? [];
  $issues       = $availability['issues'] ?? [];
  $warnings     = $availability['warnings'] ?? [];
  $retention    = (int) ($retention ?? 30);
  $scheduleHour = (int) ($schedule_hour ?? 2);
  $scheduleOn   = ! empty($schedule_enabled);
  $isMaster     = ! empty($is_master_admin);
  $confirmText  = (string) ($confirm_phrase ?? 'RESTORE');
  $canBackup    = ! empty($availability['canBackup']);
  $canRestore   = ! empty($availability['canRestore']);
  $operation    = $operation ?? null;
  $diskFree     = $disk_free ?? null;
  $problemCount = (int) ($problem_count ?? 0);

  $fmt = static fn (int $bytes): string => DatabaseBackupService::formatBytes($bytes);

  $rel = static function (?string $datetime): string {
      if ($datetime === null || $datetime === '') {
          return '—';
      }
      $ts = strtotime($datetime);
      if ($ts === false) {
          return $datetime;
      }
      $diff = time() - $ts;
      if ($diff < 45)    { return 'just now'; }
      if ($diff < 3600)  { return $diff < 90 ? '1 min ago' : floor($diff / 60) . ' min ago'; }
      if ($diff < 86400) { return floor($diff / 3600) . ' h ago'; }
      if ($diff < 604800) { return floor($diff / 86400) . ' d ago'; }
      return date('M j, Y', $ts);
  };

  $kindMeta = static fn (string $kind): array => match ($kind) {
      'auto'   => ['Automatic', 'backups-kind--auto', 'bi-calendar-check'],
      'pre'    => ['Pre-restore safety', 'backups-kind--pre', 'bi-shield-check'],
      default  => ['Manual', 'backups-kind--manual', 'bi-hand-index'],
  };

  $verifyMeta = static function (?string $result): array {
      return match ($result) {
          'ok'                => ['Verified', 'success', 'bi-patch-check-fill'],
          'checksum_mismatch' => ['Checksum mismatch', 'danger', 'bi-exclamation-octagon-fill'],
          'invalid_file'      => ['Invalid file', 'danger', 'bi-file-earmark-x-fill'],
          'file_missing'      => ['File missing', 'danger', 'bi-file-earmark-x-fill'],
          default             => ['Not checked', 'secondary', 'bi-question-circle'],
      };
  };

  $schedule        = $schedule ?? [];
  $scheduleEnabled = (bool) ($schedule['enabled'] ?? ($schedule_enabled ?? false));
  $scheduleFreq    = (string) ($schedule['frequency'] ?? 'daily');
  $scheduleTime    = (string) ($schedule['time'] ?? '02:00');
  $scheduleWeekday = (int) ($schedule['weekday'] ?? 0);
  $scheduleSource  = (string) ($schedule['source'] ?? 'config');
  $scheduleLabel   = (string) ($schedule['label'] ?? '');
  $scheduleDue     = (bool) ($schedule_due ?? false);

  $nextRunAt     = (string) ($next_run_at ?? '');
  $nextRunTs     = $nextRunAt !== '' ? strtotime($nextRunAt) : false;
  $nextRunLabel  = $nextRunTs !== false ? date('D H:i', $nextRunTs) : '';
  $nextRunShort  = $nextRunTs !== false
      ? date($scheduleFreq === 'weekly' ? 'D H:i' : 'H:i', $nextRunTs)
      : '—';
  $nextRunSoon   = $scheduleEnabled && $scheduleDue;
  $lastAuto      = $last_auto ?? null;
  $todayAuto     = $today_auto ?? null;
  $verifiedCount = (int) ($verified_count ?? 0);
  $kindCounts    = $kind_counts ?? ['auto' => 0, 'manual' => 0, 'pre' => 0];
  $totalRegistered = (int) ($total_registered ?? count($rows));
?>

<style>
  /* ------------------------------------------------------------------
     Backups page — page-local styles. KPI tiles reuse the shared
     `.sections-summary-stats .stat-tile` component from dashboard.css
     (same markup and accents as Sections / Audit log) but are scaled
     down here: the extra meta line (kind counts, free space + directory,
     timestamp + size, schedule) wrapped inside the narrow tiles and made
     the whole row ~250px tall.
     ------------------------------------------------------------------ */
  /* The page header hosts a real POST <form> for "Create backup now". That
     form is an inline action wrapper, not a filter panel: the shared panel
     styling (dashboard.css: `.main-content form`) is neutralised for page
     headers globally (`.main-content .dashboard-header form`). */

  /* `.dashboard-header .btn` (app.css) compresses buttons to .75rem text with
     .375rem/.75rem padding (~26px tall); keep this page's header actions at
     the app's standard button size like the other admin lists. */
  .backups-page .dashboard-header .btn {
    font-size: 1rem !important;
    padding: 1rem 2rem !important;
  }

  .backups-page .backups-table { font-size: .875rem; }
  .backups-page .backups-table th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    white-space: nowrap;
  }
  .backups-page .backups-table th.backups-sortable { cursor: pointer; user-select: none; }
  .backups-page .backups-table th.backups-sortable:after {
    content: '\F282';                     /* bi-arrow-down-up */
    font-family: 'bootstrap-icons';
    font-size: .7rem;
    margin-left: .3rem;
    opacity: .45;
  }
  .backups-page .backups-table th.backups-sort-asc:after  { content: '\F235'; opacity: 1; }
  .backups-page .backups-table th.backups-sort-desc:after { content: '\F229'; opacity: 1; }

  .backups-page .backups-file-name {
    font-family: 'Times New Roman', Times, serif;
    font-size: .8rem;
    word-break: break-all;
  }
  .backups-page .backups-file-meta { font-size: .72rem; }

  /* Kebab menus: the shared `.main-content form` panel styling would box the
     "Verify integrity" <form> and bulge the menu — the menu itself is the
     panel here. (The dropdown button carries
     data-bs-popper-config='{"strategy":"fixed"}' so the menu escapes the
     `.table-responsive` scroll container instead of being clipped by it.) */
  .backups-page .dropdown-menu form {
    background: none;
    border: 0;
    border-radius: 0;
    padding: 0;
    margin: 0;
    box-shadow: none;
  }

  .backups-kind {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .15rem .55rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
  }
  .backups-kind--auto   { background: rgba(100, 116, 139, .12); color: #475569; }
  .backups-kind--manual { background: rgba(37, 99, 235, .1);    color: #1d4ed8; }
  .backups-kind--pre    { background: rgba(217, 119, 6, .14);   color: #b45309; }

  .backups-integrity { font-size: .75rem; }

  /* ------------------------------------------------------------------
     Compact KPI tiles.
     The shared `.stat-tile` sizing in dashboard.css assumes a value +
     label; the backup tiles carry a third meta line whose long values
     wrapped inside the narrow columns and pushed the row to ~250px tall.
     Scale the tiles down and clamp the meta line to two lines — the full
     text stays in the DOM and in the `title` tooltip.
     ------------------------------------------------------------------ */
  .backups-page .sections-summary-stats { gap: .75rem; }

  .backups-page .sections-summary-stats .stat-tile {
    min-height: 88px;
    padding: .85rem .95rem;
    gap: .7rem;
    border-radius: 14px;
  }

  .backups-page .sections-summary-stats .stat-tile__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 11px;
    --stat-icon-size: 1.2rem;
  }

  .backups-page .sections-summary-stats .stat-tile__value {
    font-size: 1.4rem;
    letter-spacing: 0;
  }
  .backups-page .sections-summary-stats .stat-tile__value .fs-6 { font-size: .7em !important; }

  .backups-page .sections-summary-stats .stat-tile__label {
    margin-top: .15rem;
    font-size: .65rem;
    letter-spacing: .04em;
  }

  .backups-page .sections-summary-stats .stat-tile__body .small {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: .72rem;
    line-height: 1.3;
  }

  /* Operation progress panel */
  .backups-op .backups-op-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(37, 99, 235, .1);
    color: #1d4ed8;
    font-size: 1.25rem;
    flex: 0 0 auto;
  }
  .backups-op.is-failed .backups-op-icon  { background: rgba(220, 38, 38, .1);   color: #b91c1c; }
  .backups-op.is-success .backups-op-icon { background: rgba(5, 150, 105, .12);  color: #047857; }
  .backups-op-elapsed { font-variant-numeric: tabular-nums; font-weight: 700; font-size: 1.1rem; }

  .backups-op .backups-stage {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .3rem 0;
    font-size: .85rem;
    color: #94a3b8;
  }
  .backups-op .backups-stage .backups-stage-dot {
    width: 1.05rem;
    height: 1.05rem;
    border-radius: 50%;
    border: var(--hairline);
    display: grid;
    place-items: center;
    font-size: .6rem;
    flex: 0 0 auto;
  }
  .backups-op .backups-stage.is-done    { color: #047857; }
  .backups-op .backups-stage.is-done .backups-stage-dot    { border-color: #059669; background: #059669; color: #fff; }
  .backups-op .backups-stage.is-current { color: #0f172a; font-weight: 700; }
  .backups-op .backups-stage.is-current .backups-stage-dot { border-color: #2563eb; }

  .backups-empty-icon { font-size: 3rem; color: #cbd5e1; }

  .backups-copy-btn { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .45rem; --bs-btn-font-size: .72rem; }

  .backups-count-pill {
    font-variant-numeric: tabular-nums;
    background: rgba(100, 116, 139, .12);
    color: #475569;
    border-radius: 999px;
    padding: .15rem .6rem;
    font-size: .75rem;
    font-weight: 700;
  }

  .backups-row.is-hidden-by-filter { display: none; }
  .backups-row.is-removing { opacity: .35; }

  @media (max-width: 575.98px) {
    .backups-page .sections-summary-stats { gap: .6rem; }

    .backups-page .sections-summary-stats .stat-tile {
      min-height: 78px;
      padding: .7rem .75rem;
      gap: .55rem;
      border-radius: 12px;
    }

    .backups-page .sections-summary-stats .stat-tile__icon {
      width: 2.1rem;
      height: 2.1rem;
      border-radius: 9px;
      --stat-icon-size: 1.05rem;
    }

    .backups-page .sections-summary-stats .stat-tile__value { font-size: 1.12rem; }
    .backups-page .sections-summary-stats .stat-tile__value .fs-6 { font-size: .8em !important; }
    .backups-page .sections-summary-stats .stat-tile__label { font-size: .58rem; }
    .backups-page .sections-summary-stats .stat-tile__body .small { font-size: .66rem; }
  }
</style>


<div class="backups-page">

  <!-- ── Page header ─────────────────────────────────────────────────── -->
  <div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
      <div>
        <h1 class="h3 fw-bold text-primary mb-1"><i class="bi bi-database-check me-2"></i>Backup &amp; Restore</h1>
        <p class="text-muted mb-0 small">
          Daily automatic dumps of <strong><?= esc((string) ($availability['database'] ?? '')) ?></strong>, kept in a rolling
          window of <strong><?= esc((string) $retention) ?></strong> backups outside the web root.
          Every restore is integrity-checked and preceded by an automatic safety backup.
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap align-items-center ms-auto">
        <a href="<?= base_url('admin/audit-log?q=Database%20backup') ?>" class="btn btn-outline-secondary">
          <i class="bi bi-shield-check me-1"></i>Backup activity log
        </a>
        <form method="post" action="<?= base_url('admin/backups/create') ?>" class="d-inline"
              data-backups-action="create" data-backups-confirm="create">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary js-backups-create"
                  <?= $canBackup ? '' : 'disabled' ?>
                  <?= $canBackup ? '' : 'title="Backups are unavailable on this server — see the status section below."' ?>>
            <i class="bi bi-plus-circle me-1"></i>Create backup now
          </button>
        </form>
      </div>
    </div>
    <div class="blue-divider"></div>
  </div>

  <!-- ── Flash messages ──────────────────────────────────────────────── -->
  <?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
    </div>
  <?php endif; ?>

  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
    </div>
  <?php endif; ?>

  <!-- ── Availability issues / warnings ──────────────────────────────── -->
  <?php if (! $registryOk): ?>
    <div class="card border-danger border-opacity-50 shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 fw-bold text-danger mb-1"><i class="bi bi-database-exclamation me-2"></i>Backup registry unavailable</h2>
        <p class="small mb-1">The <code>database_backups</code> table could not be read. Run the pending migrations
          (<code>php spark migrate</code>) and reload this page.</p>
        <p class="small text-muted mb-0">Restoring and deleting stay disabled until the registry is readable.</p>
      </div>
    </div>
  <?php endif; ?>


  <?php if ($issues !== []): ?>
    <div class="card border-danger border-opacity-50 shadow-sm mb-4" id="backupsIssuesCard">
      <div class="card-body">
        <h2 class="h6 fw-bold text-danger mb-2"><i class="bi bi-exclamation-octagon me-2"></i>Backups are unavailable on this server</h2>
        <ul class="small mb-2 ps-3">
          <?php foreach ($issues as $issue): ?>
            <li><?= esc((string) $issue) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="small text-muted mb-0">
          Typical fixes: set <code>backup.mysqldumpPath</code> in <code>.env</code> to the absolute path of the client,
          make <code>writable/backups</code> writable by the web server, and confirm the active database driver is MySQL/MariaDB.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($warnings as $warning): ?>
    <div class="alert alert-warning d-flex gap-2" role="alert">
      <i class="bi bi-exclamation-triangle mt-1"></i>
      <div class="small"><?= esc((string) $warning) ?></div>
    </div>
  <?php endforeach; ?>

  <?php if ($problemCount > 0): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert" id="backupsProblemNotice">
      <i class="bi bi-life-preserver mt-1"></i>
      <div class="small">
        <strong><?= esc((string) $problemCount) ?> backup<?= $problemCount === 1 ? '' : 's' ?> need<?= $problemCount === 1 ? 's' : '' ?> attention</strong>
        — the file is missing from disk, the sidecar checksum file is gone, or the last integrity check failed.
        Use the integrity filter below to review them; a backup that fails verification can never be restored.
      </div>
    </div>
  <?php endif; ?>

  <!-- ── Live operation panel (progress + result + recovery) ─────────── -->
  <section id="backupsOpPanel" class="card border-0 shadow-sm mb-4 backups-op d-none"
           aria-live="polite" aria-atomic="true">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3 flex-wrap">
        <div class="backups-op-icon" id="backupsOpIcon"><i class="bi bi-hourglass-split"></i></div>
        <div class="flex-grow-1" style="min-width: 220px;">
          <h2 class="h6 fw-bold mb-1" id="backupsOpTitle">Database operation</h2>
          <div class="small text-muted" id="backupsOpMeta"></div>
        </div>
        <div class="text-end d-none" id="backupsOpTimerBox">
          <div class="backups-op-elapsed" id="backupsOpElapsed">0:00</div>
          <div class="small text-muted">elapsed</div>
        </div>
      </div>

      <div class="progress mt-3 d-none" id="backupsOpProgress" style="height: 6px;"
           role="progressbar" aria-label="Operation in progress" aria-valuetext="In progress">
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
      </div>

      <ol class="list-unstyled mt-3 mb-0" id="backupsOpStages"></ol>

      <div id="backupsOpResult" class="d-none mt-3"></div>

      <p class="small text-muted mt-3 mb-0" id="backupsOpHint">
        Large databases can take a few minutes. Keep this tab open — the operation keeps running on the server
        even if you navigate away, and this panel re-attaches automatically.
      </p>
    </div>
  </section>


  <!-- ── KPI tiles (shared stat-tile component) ──────────────────────── -->
  <div class="sections-summary-stats mb-4" data-role="backups-kpis">
    <div class="stat-tile stat-tile--primary">
      <div class="stat-tile__icon"><i class="bi bi-archive"></i></div>
      <div class="stat-tile__body">
        <div class="stat-tile__value" id="kpiStoredCount">
          <?= esc((string) count($rows)) ?><span class="fs-6 text-muted fw-normal"> / <?= esc((string) $retention) ?></span>
        </div>
        <div class="stat-tile__label">Backups stored</div>
        <?php $storedMeta = ($kindCounts['auto'] ?? 0) . ' automatic · ' . ($kindCounts['manual'] ?? 0)
            . ' manual · ' . ($kindCounts['pre'] ?? 0) . ' safety'; ?>
        <div class="small text-muted" title="<?= esc($storedMeta, 'attr') ?>"><?= esc($storedMeta) ?></div>
      </div>
    </div>

    <div class="stat-tile stat-tile--info">
      <div class="stat-tile__icon"><i class="bi bi-hdd"></i></div>
      <div class="stat-tile__body">
        <div class="stat-tile__value" id="kpiDiskUsage" data-bytes="<?= esc((string) ((int) ($total_size ?? 0))) ?>">
          <?= esc($fmt((int) ($total_size ?? 0))) ?>
        </div>
        <div class="stat-tile__label">Backup disk usage</div>
        <?php $usageMeta = $diskFree !== null
            ? $fmt((int) $diskFree) . ' free · ' . (string) ($availability['directory'] ?? '')
            : (string) ($availability['directory'] ?? 'writable/backups'); ?>
        <div class="small text-muted text-break" title="<?= esc($usageMeta, 'attr') ?>"><?= esc($usageMeta) ?></div>
      </div>
    </div>

    <div class="stat-tile stat-tile--success">
      <div class="stat-tile__icon"><i class="bi bi-clock-history"></i></div>
      <div class="stat-tile__body">
        <div class="stat-tile__value">
          <?= $newest !== null ? esc($rel((string) ($newest['created_at'] ?? ''))) : '—' ?>
        </div>
        <div class="stat-tile__label">Newest backup</div>
        <?php $newestMeta = $newest !== null
            ? (string) ($newest['created_at'] ?? '') . ' · ' . $fmt((int) ($newest['size_bytes'] ?? 0))
            : 'No backups yet — create one now'; ?>
        <div class="small text-muted" title="<?= esc($newestMeta, 'attr') ?>"><?= esc($newestMeta) ?></div>
      </div>
    </div>

    <div class="stat-tile <?= ! $scheduleEnabled ? 'stat-tile--warning' : ($scheduleDue ? 'stat-tile--warning' : 'stat-tile--success') ?>">
      <div class="stat-tile__icon">
        <i class="bi <?= ! $scheduleEnabled ? 'bi-pause-circle' : ($scheduleDue ? 'bi-hourglass-split' : 'bi-calendar-check') ?>"></i>
      </div>
      <div class="stat-tile__body">
        <div class="stat-tile__value">
          <?php if (! $scheduleEnabled): ?>Paused
          <?php elseif ($scheduleDue): ?><?= $scheduleFreq === 'daily' ? 'Pending' : 'Due now' ?>
          <?php elseif ($scheduleFreq === 'daily'): ?>Done
          <?php else: ?><?= esc($nextRunShort) ?><?php endif; ?>
        </div>
        <div class="stat-tile__label"><?= $scheduleFreq === 'daily' ? "Today's automatic backup" : 'Next automatic backup' ?></div>
        <?php
          if (! $scheduleEnabled) {
              $scheduleMeta = 'Schedule paused — enable it in Schedule & maintenance below.';
          } else {
              $scheduleMeta = (string) $scheduleLabel;
              if ($nextRunSoon) {
                  $scheduleMeta .= ' · waiting for the scheduler';
              } elseif ($nextRunLabel !== '') {
                  $scheduleMeta .= ' · next run ' . $nextRunLabel;
              }
              if ($lastAuto !== null) {
                  $scheduleMeta .= ' · last automatic ' . $rel((string) ($lastAuto['created_at'] ?? ''));
              }
          }
        ?>
        <div class="small text-muted" title="<?= esc($scheduleMeta, 'attr') ?>"><?= esc($scheduleMeta) ?></div>
      </div>
    </div>
  </div>


  <!-- ── Schedule & maintenance ──────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Schedule &amp; maintenance</h2>
      <span class="badge <?= $scheduleOn ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?>">
        <i class="bi <?= $scheduleOn ? 'bi-play-circle' : 'bi-pause-circle' ?> me-1"></i><?= $scheduleOn ? 'Schedule enabled' : 'Schedule paused' ?>
      </span>
      <span class="backups-count-pill ms-auto">
        <?= esc($scheduleLabel) ?> · Last automatic: <?= $lastAuto !== null ? esc($rel((string) ($lastAuto['created_at'] ?? ''))) : 'never' ?>
        · Next: <?= ! $scheduleEnabled ? 'paused' : ($nextRunSoon ? 'any moment' : esc($nextRunLabel)) ?>
      </span>
    </div>
    <div class="card-body pt-2">
      <p class="small text-muted mb-3">
        Choose how often and at what time the automatic backup runs. The scheduler runs the command below (interval
        plans are served best by an hourly run — the command is idempotent and creates at most one automatic backup per
        window), so a missed run (server reboot, deployment) is caught up on the next one. After every successful dump
        the oldest backups are removed until at most <strong><?= esc((string) $retention) ?></strong> remain; a failed
        dump never deletes an existing backup.
        <?php if ($scheduleSource === 'config'): ?>
          <span class="d-block mt-1">No schedule has been saved here yet — these values come from
            <code>backup.enabled</code> / <code>backup.scheduleHour</code> in <code>.env</code>.</span>
        <?php endif; ?>
      </p>

      <?php if ($isMaster): ?>
        <form method="post" action="<?= base_url('admin/backups/schedule') ?>" class="row g-2 align-items-end mb-3"
              id="backupsScheduleForm" data-backups-action="schedule">
          <?= csrf_field() ?>

          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small fw-semibold mb-1" for="backupsScheduleEnabled">Automatic backups</label>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="backupsScheduleEnabled"
                     name="schedule_enabled" value="1" <?= $scheduleEnabled ? 'checked' : '' ?>>
              <label class="form-check-label small" for="backupsScheduleEnabled" id="backupsScheduleEnabledLabel">
                <?= $scheduleEnabled ? 'Enabled' : 'Paused' ?>
              </label>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small fw-semibold mb-1" for="backupsScheduleFrequency">Frequency</label>
            <select class="form-select form-select-sm" id="backupsScheduleFrequency" name="schedule_frequency">
              <option value="daily"    <?= $scheduleFreq === 'daily' ? 'selected' : '' ?>>Once a day</option>
              <option value="every12h" <?= $scheduleFreq === 'every12h' ? 'selected' : '' ?>>Every 12 hours</option>
              <option value="every8h"  <?= $scheduleFreq === 'every8h' ? 'selected' : '' ?>>Every 8 hours</option>
              <option value="every6h"  <?= $scheduleFreq === 'every6h' ? 'selected' : '' ?>>Every 6 hours</option>
              <option value="hourly"   <?= $scheduleFreq === 'hourly' ? 'selected' : '' ?>>Every hour</option>
              <option value="weekly"   <?= $scheduleFreq === 'weekly' ? 'selected' : '' ?>>Once a week</option>
            </select>
          </div>

          <div class="col-6 col-sm-6 col-lg-2">
            <label class="form-label small fw-semibold mb-1" for="backupsScheduleTime">Time of day</label>
            <input type="time" class="form-control form-control-sm" id="backupsScheduleTime" name="schedule_time"
                   value="<?= esc($scheduleTime) ?>" required>
          </div>

          <div class="col-6 col-sm-6 col-lg-2 <?= $scheduleFreq === 'weekly' ? '' : 'd-none' ?>"
               id="backupsScheduleWeekdayWrap">
            <label class="form-label small fw-semibold mb-1" for="backupsScheduleWeekday">Day (weekly)</label>
            <select class="form-select form-select-sm" id="backupsScheduleWeekday" name="schedule_weekday">
              <?php foreach (DatabaseBackupService::SCHEDULE_WEEKDAYS as $index => $weekdayName): ?>
                <option value="<?= $index ?>" <?= $scheduleWeekday === $index ? 'selected' : '' ?>><?= esc($weekdayName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i>Save schedule
            </button>
          </div>
        </form>
      <?php else: ?>
        <div class="alert alert-light border small mb-3">
          <i class="bi bi-info-circle me-1"></i>Only master administrators can change the schedule. Current plan:
          <strong><?= esc($scheduleLabel) ?></strong><?= $scheduleEnabled ? '' : ' (paused)' ?>.
        </div>
      <?php endif; ?>

      <label class="form-label small fw-semibold mb-1" for="backupsCronLine">Scheduled command (cron / Task Scheduler)</label>
      <div class="input-group input-group-sm mb-2">
        <input type="text" class="form-control font-monospace" id="backupsCronLine" value="<?= esc((string) ($cron_line ?? '')) ?>" readonly>
        <button class="btn btn-outline-secondary js-backups-copy" type="button" data-copy-target="backupsCronLine"
                title="Copy the command"><i class="bi bi-clipboard"></i><span class="d-none d-lg-inline ms-1">Copy</span></button>
      </div>

      <div class="row g-2">
        <div class="col-lg-6">
          <label class="form-label small fw-semibold mb-1" for="backupsVerifyCmd">Weekly integrity re-check</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" id="backupsVerifyCmd" readonly
                   value="php <?= esc(ROOTPATH . 'spark') ?> backup:verify">
            <button class="btn btn-outline-secondary js-backups-copy" type="button" data-copy-target="backupsVerifyCmd"
                    title="Copy the command"><i class="bi bi-clipboard"></i></button>
          </div>
        </div>
        <div class="col-lg-6">
          <label class="form-label small fw-semibold mb-1" for="backupsRestoreCmd">Disaster recovery (command line)</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control font-monospace" id="backupsRestoreCmd" readonly
                   value="php <?= esc(ROOTPATH . 'spark') ?> backup:restore --id=&lt;n&gt; --confirm=RESTORE">
            <button class="btn btn-outline-secondary js-backups-copy" type="button" data-copy-target="backupsRestoreCmd"
                    title="Copy the command"><i class="bi bi-clipboard"></i></button>
          </div>
        </div>
      </div>

      <hr class="my-3">

      <div class="row g-3 small text-muted">
        <div class="col-md-6">
          <div class="fw-semibold text-body mb-1"><i class="bi bi-terminal me-1"></i>Database clients</div>
          <div>mysqldump:
            <?php if (! empty($availability['mysqldump'])): ?>
              <code class="text-break"><?= esc((string) $availability['mysqldump']) ?></code>
            <?php else: ?>
              <span class="text-danger fw-semibold">not found</span>
            <?php endif; ?>
          </div>
          <div>mysql:
            <?php if (! empty($availability['mysql'])): ?>
              <code class="text-break"><?= esc((string) $availability['mysql']) ?></code>
            <?php else: ?>
              <span class="text-danger fw-semibold">not found — restoring is unavailable</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-6">
          <div class="fw-semibold text-body mb-1"><i class="bi bi-hdd-stack me-1"></i>Storage</div>
          <div>Directory: <code class="text-break"><?= esc((string) ($availability['directory'] ?? 'writable/backups')) ?></code></div>
          <div>Kept: newest <?= esc((string) $retention) ?> backups ·
            <?= $verifiedCount ?> verified ·
            <?= $problemCount > 0 ? '<span class="text-warning-emphasis fw-semibold">' . $problemCount . ' need attention</span>' : 'all healthy' ?>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- ── Filters (labelled panel, same pattern as the other admin lists) ── -->
  <form class="row g-2 mb-3" id="backupsFilterForm" method="get" action="<?= base_url('admin/backups') ?>">
    <div class="col-md-3">
      <label class="form-label" for="backupsSearch"><i class="bi bi-search me-1 text-muted"></i>Search</label>
      <div class="input-group">
        <input type="search" class="form-control" id="backupsSearch" autocomplete="off"
               placeholder="File, id or creator…">
        <button class="btn btn-outline-secondary d-none" type="button" id="backupsSearchClear" aria-label="Clear search">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    <div class="col-md-3">
      <label class="form-label" for="backupsKindFilter">Backup type</label>
      <select class="form-select" id="backupsKindFilter">
        <option value="">All types</option>
        <option value="auto">Automatic</option>
        <option value="manual">Manual</option>
        <option value="pre">Pre-restore safety</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label" for="backupsIntegrityFilter">Integrity</label>
      <select class="form-select" id="backupsIntegrityFilter">
        <option value="">Any integrity state</option>
        <option value="ok">Verified</option>
        <option value="issues">Needs attention</option>
        <option value="unchecked">Not checked</option>
      </select>
    </div>

    <div class="col-auto align-self-end">
      <button type="button" class="btn btn-outline-secondary d-none" id="backupsFilterReset">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
      </button>
    </div>

    <div class="col-12 small text-muted d-none" id="backupsFilterNote" aria-live="polite"></div>
  </form>

  <!-- ── Backup inventory ────────────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-4" id="backupsInventory">
    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0"><i class="bi bi-collection me-2"></i>Stored backups</h2>
      <span class="backups-count-pill" id="backupsShownCount"
            data-total="<?= esc((string) count($rows)) ?>">
        Showing <?= esc((string) count($rows)) ?> of <?= esc((string) count($rows)) ?>
      </span>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" id="backupsVerifyAll" disabled
                title="Recompute the checksum and structure of every listed backup, one by one">
          <i class="bi bi-fingerprint me-1"></i>Verify all
        </button>
        <button type="button" class="btn btn-outline-secondary" id="backupsRefreshBtn"
                title="Reload the page">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 backups-table" id="backupsTable" data-no-enhance="1">
        <thead class="table-light">
          <tr>
            <th style="width: 48px;">#</th>
            <th style="min-width: 220px;">Backup</th>
            <th class="backups-sortable" data-sort-key="kind" style="width: 130px;">Type</th>
            <th class="text-end backups-sortable" data-sort-key="size" style="width: 96px;">Size</th>
            <th class="backups-sortable" data-sort-key="created" style="width: 134px;">Created</th>
            <th style="width: 134px;">Created by</th>
            <th class="backups-sortable" data-sort-key="integrity" style="width: 120px;">Integrity</th>
            <th class="text-end" style="width: 118px;">Actions</th>
          </tr>
        </thead>
        <tbody id="backupsTableBody">
          <?php if ($rows === []): ?>
            <tr>
              <td colspan="8" class="text-center py-5">
                <i class="bi bi-inbox backups-empty-icon d-block mb-2"></i>
                <div class="fw-semibold text-body">No backups stored yet</div>
                <div class="small text-muted mb-3">Create one now, or wait for the daily automatic backup.</div>
                <form method="post" action="<?= base_url('admin/backups/create') ?>" class="d-inline"
                      data-backups-action="create" data-backups-confirm="create">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-primary" <?= $canBackup ? '' : 'disabled' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create the first backup
                  </button>
                </form>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($rows as $row): ?>
          <?php
            $rowId      = (int) $row['id'];
            $kind       = (string) ($row['kind'] ?? 'manual');
            [$kindLabel, $kindClass, $kindIcon] = $kindMeta($kind);
            $fileExists = ! empty($row['file_exists']);
            $metaExists = ! empty($row['meta_exists']);
            $verifyRaw  = isset($row['verify_result']) && $row['verify_result'] !== '' ? (string) $row['verify_result'] : null;
            [$verifyLabel, $verifyTone, $verifyIcon] = $verifyMeta($verifyRaw);
            $verifyKey  = $verifyRaw === 'ok' ? 'ok' : ($verifyRaw === null ? 'unchecked' : 'issues');
            $sortRank   = $verifyKey === 'ok' ? 2 : ($verifyKey === 'unchecked' ? 1 : 0);
            $restorable = $fileExists && $verifyKey === 'ok' && $canRestore;
            $blockedReason = '';
            if (! $fileExists) {
                $blockedReason = 'The dump file is missing from disk, so it cannot be restored.';
            } elseif ($verifyKey === 'issues') {
                $blockedReason = 'The last integrity check for this dump failed. The server will refuse the restore.';
            } elseif (! $canRestore) {
                $blockedReason = 'Restoring is unavailable on this server (the mysql client was not found).';
            }
            $search    = strtolower(implode(' ', [
                (string) $row['filename'],
                (string) $rowId,
                (string) ($row['created_by_name'] ?? ''),
                $kindLabel,
            ]));
            $createdTs = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
          ?>
          <tr class="backups-row" id="backupRow<?= esc((string) $rowId) ?>"
              data-backup-id="<?= esc((string) $rowId) ?>"
              data-kind="<?= esc($kind) ?>"
              data-integrity="<?= esc($verifyKey) ?>"
              data-file-exists="<?= $fileExists ? '1' : '0' ?>"
              data-meta-exists="<?= $metaExists ? '1' : '0' ?>"
              data-restorable="<?= $restorable ? '1' : '0' ?>"
              data-size="<?= esc((string) (int) ($row['size_bytes'] ?? 0)) ?>"
              data-created-ts="<?= esc((string) $createdTs) ?>"
              data-sort-kind="<?= esc($kindLabel) ?>"
              data-sort-size="<?= esc((string) (int) ($row['size_bytes'] ?? 0)) ?>"
              data-sort-created="<?= esc((string) $createdTs) ?>"
              data-sort-integrity="<?= esc((string) $sortRank) ?>"
              data-search="<?= esc($search) ?>">
            <td class="text-muted small" data-col-label="#"><?= esc((string) $rowId) ?></td>
            <td data-col-label="Backup">
              <div class="fw-semibold backups-file-name"><?= esc((string) $row['filename']) ?></div>
              <div class="text-muted backups-file-meta">
                <?= esc((string) ($row['tables_count'] ?? 0)) ?> table(s)
                <?php if (! empty($row['dump_profile'])): ?> · <?= esc((string) $row['dump_profile']) ?> profile<?php endif; ?>
                <?php if (! $fileExists): ?>
                  · <span class="text-danger fw-semibold"><i class="bi bi-x-octagon me-1"></i>file missing on disk</span>
                <?php elseif (! $metaExists): ?>
                  · <span class="text-warning-emphasis fw-semibold"><i class="bi bi-shield-slash me-1"></i>no sidecar checksum</span>
                <?php endif; ?>
              </div>
            </td>
            <td data-col-label="Type">
              <span class="backups-kind <?= esc($kindClass) ?>"><i class="bi <?= esc($kindIcon) ?>"></i><?= esc($kindLabel) ?></span>
            </td>
            <td class="text-end small" data-col-label="Size" data-size-bytes="<?= esc((string) (int) ($row['size_bytes'] ?? 0)) ?>">
              <?= esc($fmt((int) ($row['size_bytes'] ?? 0))) ?>
            </td>
            <td class="small" data-col-label="Created">
              <span title="<?= esc((string) ($row['created_at'] ?? '')) ?>"><?= esc($rel((string) ($row['created_at'] ?? ''))) ?></span>
              <div class="text-muted backups-file-meta"><?= esc((string) ($row['created_at'] ?? '')) ?></div>
            </td>
            <td class="small" data-col-label="Created by">
              <?= esc((string) ($row['created_by_name'] ?? '—')) ?>
            </td>
            <td class="backups-integrity" data-col-label="Integrity">
              <span class="badge bg-<?= esc($verifyTone) ?>-subtle text-<?= esc($verifyTone) ?>-emphasis js-verify-badge">
                <i class="bi <?= esc($verifyIcon) ?> me-1"></i><?= esc($verifyLabel) ?>
              </span>
              <?php if (! empty($row['verified_at'])): ?>
                <div class="text-muted backups-file-meta js-verified-at">checked <?= esc($rel((string) $row['verified_at'])) ?></div>
              <?php endif; ?>
              <?php if (! empty($row['restored_at'])): ?>
                <div class="text-muted backups-file-meta">
                  restored <?= esc($rel((string) $row['restored_at'])) ?> (×<?= esc((string) ((int) ($row['restore_count'] ?? 0))) ?>)
                </div>
              <?php endif; ?>
            </td>
            <td class="text-end" data-col-label="Actions">
              <div class="d-inline-flex align-items-center gap-1">
                <?php if ($fileExists): ?>
                  <a href="<?= base_url('admin/backups/download/' . $rowId) ?>" class="btn btn-outline-primary btn-sm"
                     title="Download this dump (<?= esc($fmt((int) ($row['size_bytes'] ?? 0)), 'attr') ?>)" download>
                    <i class="bi bi-download"></i><span class="d-none d-lg-inline ms-1">Download</span>
                  </a>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled
                          title="The file is missing from disk — nothing to download">
                    <i class="bi bi-download"></i>
                  </button>
                <?php endif; ?>

                <div class="dropdown">
                  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown"
                          data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}'
                          aria-expanded="false"
                          aria-label="More actions for <?= esc((string) $row['filename'], 'attr') ?>">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <form method="post" action="<?= base_url('admin/backups/verify/' . $rowId) ?>"
                            data-backups-action="verify" data-backup-id="<?= esc((string) $rowId) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item">
                          <i class="bi bi-fingerprint me-2 text-secondary"></i>Verify integrity
                        </button>
                      </form>
                    </li>

                    <?php if ($isMaster): ?>
                      <li>
                        <button type="button" class="dropdown-item js-backups-restore" data-bs-toggle="modal"
                                data-bs-target="#backupsRestoreModal"
                                data-backup-id="<?= esc((string) $rowId) ?>"
                                data-backup-file="<?= esc((string) $row['filename'], 'attr') ?>"
                                data-backup-size="<?= esc($fmt((int) ($row['size_bytes'] ?? 0)), 'attr') ?>"
                                data-backup-created="<?= esc((string) ($row['created_at'] ?? ''), 'attr') ?>"
                                data-backup-tables="<?= esc((string) ((int) ($row['tables_count'] ?? 0)), 'attr') ?>"
                                data-backup-integrity="<?= esc($verifyLabel, 'attr') ?>"
                                data-backup-integrity-key="<?= esc($verifyKey, 'attr') ?>"
                                data-backup-restores="<?= esc((string) ((int) ($row['restore_count'] ?? 0)), 'attr') ?>"
                                data-backup-restorable="<?= $restorable ? '1' : '0' ?>"
                                data-backup-blocked-reason="<?= esc($blockedReason, 'attr') ?>">
                          <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Restore database…
                        </button>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger js-backups-delete" data-bs-toggle="modal"
                                data-bs-target="#backupsDeleteModal"
                                data-backup-id="<?= esc((string) $rowId) ?>"
                                data-backup-file="<?= esc((string) $row['filename'], 'attr') ?>"
                                data-backup-size="<?= esc($fmt((int) ($row['size_bytes'] ?? 0)), 'attr') ?>"
                                data-backup-created="<?= esc((string) ($row['created_at'] ?? ''), 'attr') ?>"
                                data-backup-kind="<?= esc($kindLabel, 'attr') ?>"
                                data-backup-file-exists="<?= $fileExists ? '1' : '0' ?>">
                          <i class="bi bi-trash me-2"></i>Delete backup…
                        </button>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white border-0 small text-muted d-flex flex-wrap gap-2 justify-content-between">
      <span>
        <i class="bi bi-shield-lock me-1"></i>Dumps are stored outside the web root; downloads are permission-checked and audited.
      </span>
      <span class="js-backups-empty-filter d-none">No backup matches the current search / filters.</span>
      <span>
        <?php if (! $registryOk): ?>
          Registry unavailable — actions are disabled.
        <?php elseif ($totalRegistered > count($rows)): ?>
          Showing the newest <?= esc((string) count($rows)) ?> of <?= esc((string) $totalRegistered) ?> registered backups.
        <?php else: ?>
          Retention: newest <?= esc((string) $retention) ?>, oldest removed first.
        <?php endif; ?>
      </span>
    </div>
  </div>


  <?php if ($isMaster): ?>
  <!-- ── Restore confirmation modal ───────────────────────────────────── -->
  <div class="modal fade" id="backupsRestoreModal" tabindex="-1" aria-labelledby="backupsRestoreTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <form method="post" action="<?= base_url('admin/backups/restore') ?>" class="modal-content"
            id="backupsRestoreForm" data-backups-action="restore">
        <?= csrf_field() ?>
        <input type="hidden" name="backup_id" id="backupsRestoreId" value="">
        <div class="modal-header bg-danger-subtle">
          <h5 class="modal-title" id="backupsRestoreTitle">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Restore the database
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none" id="backupsRestoreBlocked" role="alert">
            <i class="bi bi-slash-circle me-2"></i><span id="backupsRestoreBlockedText"></span>
          </div>

          <div class="card bg-body-tertiary border-0 mb-3">
            <div class="card-body py-3">
              <div class="row g-3 small">
                <div class="col-md-7">
                  <div class="text-muted">Backup file</div>
                  <div class="fw-semibold backups-file-name" id="backupsRestoreFile">—</div>
                </div>
                <div class="col-md-5">
                  <div class="row g-2">
                    <div class="col-6 col-md-12">
                      <div class="text-muted">Created</div>
                      <div class="fw-semibold" id="backupsRestoreCreated">—</div>
                    </div>
                    <div class="col-6 col-md-12">
                      <div class="text-muted">Size · tables · integrity</div>
                      <div class="fw-semibold" id="backupsRestoreDetails">—</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <h6 class="fw-bold small text-uppercase text-muted mb-2">What will happen</h6>
          <ol class="small mb-3 ps-3">
            <li>The dump is re-verified against its SHA-256 checksum and structure (a failed check blocks the restore).</li>
            <li>A <strong>pre-restore safety backup of the current database is created first</strong> — if that fails, nothing is restored.</li>
            <li>The dump is streamed into the database. <strong>Everything written after this backup was taken is lost.</strong></li>
            <li>The operation is recorded in the activity log; it cannot be undone from this page (the safety backup can be restored instead).</li>
          </ol>

          <label for="backupsRestoreConfirm" class="form-label small fw-semibold">
            Type <code><?= esc($confirmText) ?></code> to confirm
          </label>
          <input type="text" class="form-control" id="backupsRestoreConfirm" name="confirm" value=""
                 autocomplete="off" spellcheck="false" required aria-describedby="backupsRestoreConfirmHelp">
          <div class="form-text" id="backupsRestoreConfirmHelp">
            The confirmation is checked again on the server. Keep this tab open while the restore runs.
          </div>
        </div>
        <div class="modal-footer justify-content-between flex-wrap gap-2">
          <span class="small text-muted">
            <i class="bi bi-shield-lock me-1"></i>Master administrators only
            <span class="d-none" id="backupsRestorePrevious">· restored before ×<span id="backupsRestorePreviousCount">0</span></span>
          </span>
          <span class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="backupsRestoreSubmit" disabled>
              <span class="spinner-border spinner-border-sm me-1 d-none" id="backupsRestoreSpinner" aria-hidden="true"></span>
              <i class="bi bi-arrow-counterclockwise me-1"></i>Verify, back up and restore
            </button>
          </span>
        </div>
      </form>
    </div>
  </div>


  <!-- ── Delete confirmation modal ────────────────────────────────────── -->
  <div class="modal fade" id="backupsDeleteModal" tabindex="-1" aria-labelledby="backupsDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="" class="modal-content" id="backupsDeleteForm" data-backups-action="delete">
        <?= csrf_field() ?>
        <div class="modal-header bg-danger-subtle">
          <h5 class="modal-title" id="backupsDeleteTitle">
            <i class="bi bi-trash me-2 text-danger"></i>Delete backup
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2 small">This permanently deletes the dump file and its registry entry:</p>
          <div class="card bg-body-tertiary border-0 mb-3">
            <div class="card-body py-3 small">
              <div class="fw-semibold backups-file-name" id="backupsDeleteFile">—</div>
              <div class="text-muted" id="backupsDeleteMeta">—</div>
            </div>
          </div>
          <div class="alert alert-warning small mb-0 d-none" id="backupsDeleteMissingNote">
            <i class="bi bi-info-circle me-1"></i>The file is already missing from disk; deleting removes only the registry entry.
          </div>
          <div class="alert alert-danger small mb-0 d-none" id="backupsDeleteWarning">
            <i class="bi bi-exclamation-triangle me-1"></i>This cannot be undone. The dump will not be recoverable from this server.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="backupsDeleteSubmit">
            <span class="spinner-border spinner-border-sm me-1 d-none" id="backupsDeleteSpinner" aria-hidden="true"></span>
            <i class="bi bi-trash me-1"></i>Delete permanently
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.backups-page -->


<script id="backupsConfig" type="application/json"><?= json_encode([
    'statusUrl'     => base_url('admin/backups/status'),
    'createUrl'     => base_url('admin/backups/create'),
    'verifyUrl'     => base_url('admin/backups/verify/'),
    'deleteUrl'     => base_url('admin/backups/delete/'),
    'downloadUrl'   => base_url('admin/backups/download/'),
    'restoreUrl'    => base_url('admin/backups/restore'),
    'activityUrl'   => base_url('admin/audit-log?q=Database%20backup'),
    'csrfName'      => csrf_token(),
    'csrfHash'      => csrf_hash(),
    'confirmPhrase' => $confirmText,
    'canRestore'    => $canRestore,
    'isMaster'      => $isMaster,
    'initialOperation' => $operation,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script>
(function () {
  'use strict';

  var cfgEl = document.getElementById('backupsConfig');
  var cfg   = cfgEl ? JSON.parse(cfgEl.textContent || '{}') : null;

  if (!cfg) {
    return;
  }

  var csrfHash = cfg.csrfHash || '';

  var panel   = document.getElementById('backupsOpPanel');
  var tableBody = document.getElementById('backupsTableBody');

  // -------------------------------------------------------------------
  // Small helpers
  // -------------------------------------------------------------------

  function applyCsrf(data) {
    if (data && typeof data.csrf_hash === 'string' && data.csrf_hash !== '') {
      csrfHash = data.csrf_hash;
    }
  }

  function formBody(extra) {
    var body = new URLSearchParams();
    body.append(cfg.csrfName, csrfHash);
    if (extra) {
      Object.keys(extra).forEach(function (key) { body.append(key, extra[key]); });
    }
    return body;
  }

  function postForm(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (response) {
      var contentType = response.headers.get('content-type') || '';

      if (contentType.indexOf('application/json') === -1) {
        // CSRF redirect / HTML error page: the session likely expired.
        return { sessionExpired: true, data: null };
      }

      return response.json().then(function (data) {
        return { sessionExpired: false, data: data };
      });
    });
  }

  function toast(type, message) {
    var container = document.getElementById('backupsToasts');

    if (!container) {
      container = document.createElement('div');
      container.id = 'backupsToasts';
      container.className = 'position-fixed end-0 p-3';
      container.style.cssText = 'top: 4.5rem; z-index: 2000; max-width: 420px;';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }

    var tone = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'danger'));
    var icon = type === 'success' ? 'bi-check-circle' : (type === 'warning' ? 'bi-exclamation-triangle' : (type === 'info' ? 'bi-info-circle' : 'bi-exclamation-octagon'));

    var node = document.createElement('div');
    node.className = 'alert alert-' + tone + ' alert-dismissible fade show shadow-sm mb-2';
    node.setAttribute('role', 'alert');

    var iconEl = document.createElement('i');
    iconEl.className = 'bi ' + icon + ' me-2';
    node.appendChild(iconEl);
    node.appendChild(document.createTextNode(message));

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'alert');
    close.setAttribute('aria-label', 'Dismiss');
    node.appendChild(close);

    container.appendChild(node);

    setTimeout(function () {
      if (node.parentNode) {
        node.remove();
      }
    }, 7000);
  }

  function sessionExpiredToast() {
    toast('danger', 'Your session expired or the request was rejected. Reloading the page…');
    setTimeout(function () { location.reload(); }, 1500);
  }

  function flashAndReload(type, message) {
    try {
      sessionStorage.setItem('backups.flash', JSON.stringify({ type: type, message: message }));
    } catch (e) { /* private mode */ }

    setTimeout(function () { location.reload(); }, 900);
  }

  function formatBytes(bytes) {
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var value = Math.max(0, Number(bytes) || 0);
    var index = 0;

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index++;
    }

    return (index === 0 ? String(Math.round(value)) : value.toFixed(value < 10 ? 2 : 1)) + ' ' + units[index];
  }

  function formatDuration(seconds) {
    var total = Math.max(0, Math.round(Number(seconds) || 0));
    var mins  = Math.floor(total / 60);
    var secs  = total % 60;

    return mins + ':' + (secs < 10 ? '0' : '') + secs;
  }

  function esc(text) {
    var div = document.createElement('div');
    div.textContent = text === null || text === undefined ? '' : String(text);
    return div.innerHTML;
  }


  // -------------------------------------------------------------------
  // Live operation panel (real stages from GET admin/backups/status)
  // -------------------------------------------------------------------

  var STAGE_LISTS = {
    backup: [
      { stage: 'dump',     label: 'Dumping the database (mysqldump)' },
      { stage: 'verify',   label: 'Verifying the dump (structure + SHA-256)' },
      { stage: 'register', label: 'Registering the backup' },
      { stage: 'rotate',   label: 'Applying the retention window' }
    ],
    restore: [
      { stage: 'verify',  label: 'Verifying the selected dump' },
      { stage: 'safety',  label: 'Creating the pre-restore safety backup' },
      { stage: 'restore', label: 'Restoring the database' }
    ]
  };

  var opIcon     = document.getElementById('backupsOpIcon');
  var opTitle    = document.getElementById('backupsOpTitle');
  var opMeta     = document.getElementById('backupsOpMeta');
  var opTimerBox = document.getElementById('backupsOpTimerBox');
  var opElapsed  = document.getElementById('backupsOpElapsed');
  var opProgress = document.getElementById('backupsOpProgress');
  var opStages   = document.getElementById('backupsOpStages');
  var opResult   = document.getElementById('backupsOpResult');
  var opHint     = document.getElementById('backupsOpHint');

  var op = { kind: null, timer: null, poll: null, startedAt: 0, attached: false, stage: null };

  function panelShow() {
    if (panel) {
      panel.classList.remove('d-none');
    }
  }

  function renderStages(kind) {
    if (!opStages) { return; }

    opStages.innerHTML = '';
    (STAGE_LISTS[kind] || []).forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'backups-stage';
      li.setAttribute('data-stage', item.stage);
      li.innerHTML = '<span class="backups-stage-dot"></span>';
      li.appendChild(document.createTextNode(item.label));
      opStages.appendChild(li);
    });
  }

  function markStages(kind, currentStage) {
    if (!opStages) { return; }

    var order = (STAGE_LISTS[kind] || []).map(function (item) { return item.stage; });
    var currentIndex = order.indexOf(currentStage);

    Array.prototype.forEach.call(opStages.children, function (li) {
      var index = order.indexOf(li.getAttribute('data-stage'));
      li.classList.remove('is-done', 'is-current');

      if (currentIndex === -1) {
        return; // 'starting' (nothing reached yet) or terminal (handled by finish)
      }

      if (index < currentIndex) {
        li.classList.add('is-done');
        var dot = li.querySelector('.backups-stage-dot');
        if (dot) { dot.innerHTML = '<i class="bi bi-check-lg"></i>'; }
      } else if (index === currentIndex) {
        li.classList.add('is-current');
      }
    });
  }

  function startElapsed(secondsAgo) {
    stopElapsed();
    op.startedAt = Date.now() - (Number(secondsAgo || 0) * 1000);

    if (opTimerBox) {
      opTimerBox.classList.remove('d-none');
    }

    function tick() {
      if (opElapsed) {
        opElapsed.textContent = formatDuration((Date.now() - op.startedAt) / 1000);
      }
    }

    tick();
    op.timer = setInterval(tick, 1000);
  }

  function stopElapsed() {
    if (op.timer) {
      clearInterval(op.timer);
      op.timer = null;
    }
  }

  function describeState(state) {
    if (!opMeta || !state) { return; }

    var parts = [];
    if (state.actor_name)  { parts.push('started by ' + state.actor_name); }
    if (state.started_at)  { parts.push('at ' + state.started_at); }
    if (state.stage_label) { parts.push(state.stage_label); }

    opMeta.textContent = parts.join(' · ');
  }

  function beginOperation(kind, state) {
    op.kind     = kind;
    op.attached = false;
    op.stage    = null;

    panelShow();

    if (panel) {
      panel.classList.remove('is-failed', 'is-success');
    }
    if (opIcon) {
      opIcon.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    }
    if (opTitle) {
      opTitle.textContent = (state && state.operation_label)
        || (kind === 'restore' ? 'Restoring the database' : 'Creating a database backup');
    }
    if (opProgress) { opProgress.classList.remove('d-none'); }
    if (opResult)   { opResult.classList.add('d-none'); opResult.innerHTML = ''; }
    if (opHint)     { opHint.classList.remove('d-none'); }

    describeState(state);
    renderStages(kind);
    markStages(kind, state ? state.stage : 'starting');
    startElapsed(state ? state.age_seconds : 0);
    startPolling();

    window.addEventListener('beforeunload', beforeUnloadGuard);
  }

  function attachToRunningOperation(state) {
    if (!state || !state.operation) { return; }

    beginOperation(state.operation, state);
    op.attached = true;

    if (opMeta) {
      opMeta.textContent += ' · this page updates automatically';
    }
  }

  function beforeUnloadGuard(event) {
    if (op.timer || op.poll) {
      event.preventDefault();
      event.returnValue = '';
    }
  }

  function startPolling() {
    stopPolling();
    op.poll = setInterval(pollStatus, 2000);
  }

  function stopPolling() {
    if (op.poll) {
      clearInterval(op.poll);
      op.poll = null;
    }
  }


  function pollStatus() {
    fetch(cfg.statusUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      applyCsrf(data);

      var state = data && data.operation ? data.operation : null;

      if (!op.kind) {
        if (state && state.running) {
          attachToRunningOperation(state);
        }
        return;
      }

      if (!state) {
        return; // nothing recorded yet
      }

      op.stage = state.stage;
      describeState(state);
      markStages(op.kind, state.stage);

      if (state.finished) {
        finishOperation(state.result === 'ok' ? 'success' : 'error', state.detail || '', state);
      } else if (state.stale) {
        finishOperation('error', 'The operation has not reported progress for several minutes and may have been interrupted. Reload the page to check the list before retrying.', state);
      }
    }).catch(function () {
      /* transient network problem: keep polling */
    });
  }

  function finishOperation(tone, message, state) {
    stopElapsed();
    stopPolling();
    window.removeEventListener('beforeunload', beforeUnloadGuard);

    if (opProgress) { opProgress.classList.add('d-none'); }
    if (opHint)     { opHint.classList.add('d-none'); }

    if (panel) {
      panel.classList.remove('is-failed', 'is-success');
      panel.classList.add(tone === 'success' ? 'is-success' : 'is-failed');
    }

    if (opIcon) {
      opIcon.innerHTML = tone === 'success'
        ? '<i class="bi bi-check-circle-fill"></i>'
        : '<i class="bi bi-exclamation-triangle-fill"></i>';
    }

    if (opTitle) {
      opTitle.textContent = tone === 'success'
        ? (op.kind === 'restore' ? 'Database restored' : 'Backup completed')
        : (op.kind === 'restore' ? 'Restore failed' : 'Backup failed');
    }

    renderResult(tone, message, state);
  }

  function resultButton(label, icon, handler, primary) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn ' + (primary ? 'btn-primary' : 'btn-outline-secondary');
    button.innerHTML = '<i class="bi ' + icon + ' me-1"></i>';
    button.appendChild(document.createTextNode(label));
    button.addEventListener('click', handler);

    return button;
  }

  function renderResult(tone, message, state) {
    if (!opResult) { return; }

    opResult.innerHTML = '';
    opResult.classList.remove('d-none');

    if (tone === 'success' && op.kind === 'restore') {
      var warn = document.createElement('div');
      warn.className = 'alert alert-warning small mb-2';
      warn.innerHTML = '<i class="bi bi-info-circle me-1"></i><strong>The database has been replaced.</strong> '
        + 'The backup registry — and possibly your own account row — now comes from the restored dump. '
        + 'Reload the page and sign in again if you are asked to.';
      opResult.appendChild(warn);
    }

    var text = document.createElement('p');
    text.className = 'small mb-2 ' + (tone === 'error' ? 'text-danger fw-semibold' : 'text-muted');
    text.textContent = message || (tone === 'success' ? 'Completed.' : 'The operation did not complete.');
    opResult.appendChild(text);

    var actions = document.createElement('div');
    actions.className = 'd-flex gap-2 flex-wrap';

    actions.appendChild(resultButton('Reload page', 'bi-arrow-clockwise', function () { location.reload(); }, true));

    if (tone === 'success' && op.kind === 'backup' && state && state.filename) {
      var row = findRowByName(state.filename);
      var rowId = row ? row.getAttribute('data-backup-id') : null;

      if (rowId) {
        actions.appendChild(resultButton('Download backup', 'bi-download', function () {
          window.location.href = cfg.downloadUrl + rowId;
        }, false));
      }
    }

    var logLink = document.createElement('a');
    logLink.className = 'btn btn-outline-secondary';
    logLink.href = cfg.activityUrl;
    logLink.innerHTML = '<i class="bi bi-shield-check me-1"></i>Activity log';
    actions.appendChild(logLink);

    opResult.appendChild(actions);
  }

  function findRowByName(filename) {
    if (!tableBody || !filename) { return null; }

    var rows = tableBody.querySelectorAll('.backups-row');

    for (var i = 0; i < rows.length; i++) {
      var nameEl = rows[i].querySelector('.backups-file-name');

      if (nameEl && nameEl.textContent.trim() === filename) {
        return rows[i];
      }
    }

    return null;
  }


  // -------------------------------------------------------------------
  // Action handlers (AJAX with a real-form fallback)
  // -------------------------------------------------------------------

  var VERIFY_TONES = {
    ok:                { label: 'Verified',           tone: 'success', icon: 'bi-patch-check-fill' },
    checksum_mismatch: { label: 'Checksum mismatch',  tone: 'danger',  icon: 'bi-exclamation-octagon-fill' },
    invalid_file:      { label: 'Invalid file',       tone: 'danger',  icon: 'bi-file-earmark-x-fill' },
    file_missing:      { label: 'File missing',       tone: 'danger',  icon: 'bi-file-earmark-x-fill' },
    failed:            { label: 'Verification failed', tone: 'danger', icon: 'bi-exclamation-octagon-fill' }
  };

  function updateRowIntegrity(row, status, verifiedAt) {
    if (!row) { return; }

    var meta  = VERIFY_TONES[status] || VERIFY_TONES.failed;
    var badge = row.querySelector('.js-verify-badge');

    if (badge) {
      badge.className = 'badge bg-' + meta.tone + '-subtle text-' + meta.tone + '-emphasis js-verify-badge';
      badge.innerHTML = '<i class="bi ' + meta.icon + ' me-1"></i>' + esc(meta.label);
    }

    var key      = status === 'ok' ? 'ok' : 'issues';
    var restorable = key === 'ok'
      && row.getAttribute('data-file-exists') === '1'
      && cfg.canRestore;

    row.setAttribute('data-integrity', key);
    row.setAttribute('data-restorable', restorable ? '1' : '0');
    row.setAttribute('data-sort-integrity', key === 'ok' ? '2' : '0');

    if (verifiedAt) {
      var checked = row.querySelector('.js-verified-at');

      if (!checked) {
        checked = document.createElement('div');
        checked.className = 'text-muted backups-file-meta js-verified-at';
        var cell = row.querySelector('.backups-integrity');
        if (cell) { cell.appendChild(checked); }
      }

      checked.textContent = 'checked just now';
    }
  }

  function verifyRow(row, silent) {
    var id = row ? row.getAttribute('data-backup-id') : null;

    if (!id) {
      return Promise.resolve(false);
    }

    return postForm(cfg.verifyUrl + id, formBody()).then(function (result) {
      if (result.sessionExpired) {
        sessionExpiredToast();
        return false;
      }

      var data = result.data || {};
      applyCsrf(data);

      if (data.success) {
        updateRowIntegrity(row, 'ok', (data.verify && data.verify.verified_at) || true);

        if (!silent) {
          toast('success', data.message || 'Integrity verified.');
        }

        return true;
      }

      var status = data.verify && data.verify.status ? data.verify.status : 'failed';
      updateRowIntegrity(row, status, true);

      if (!silent) {
        toast('danger', data.error || 'Verification failed.');
      }

      return false;
    }).catch(function () {
      if (!silent) {
        toast('danger', 'Verification failed: the request could not be completed.');
      }

      return false;
    });
  }

  function confirmDialog(message, title) {
    if (typeof window.customConfirm === 'function') {
      return window.customConfirm(message, title || 'Confirm action');
    }

    return Promise.resolve(window.confirm(message));
  }

  function handleCreate(form) {
    return confirmDialog(
      'Create a database backup now?\nLarge databases can take a while — the page shows live progress, and you can keep working in other tabs.',
      'Create backup'
    ).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      beginOperation('backup', null);

      return postForm(form.action, formBody()).then(function (result) {
        if (result.sessionExpired) {
          finishOperation('error', 'Your session expired before the backup could be created.');
          sessionExpiredToast();
          return;
        }

        var data = result.data || {};
        applyCsrf(data);

        if (data.success) {
          var backup = data.backup || {};

          finishOperation('success', data.message || 'Backup created.', {
            operation: 'backup',
            filename: backup.filename || '',
            result: 'ok'
          });
          flashAndReload('success', data.message || 'Backup created.');
        } else {
          finishOperation('error', data.error || 'The backup could not be created.');
          toast('danger', data.error || 'The backup could not be created.');
        }
      }).catch(function () {
        finishOperation('error', 'The request failed before the server replied. Check the activity log before retrying.');
      });
    });
  }

  function handleSchedule(form) {
    var button   = form.querySelector('button[type="submit"]');
    var original = button ? button.innerHTML : '';

    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving…';
    }

    var body = new URLSearchParams(new FormData(form));
    body.set(cfg.csrfName, csrfHash);

    return postForm(form.action, body).then(function (result) {
      if (result.sessionExpired) {
        sessionExpiredToast();
        return;
      }

      var data = result.data || {};
      applyCsrf(data);

      if (data.success) {
        toast('success', data.message || 'Backup schedule saved.');
        flashAndReload('success', data.message || 'Backup schedule saved.');
      } else {
        toast('danger', data.error || 'The schedule could not be saved.');
      }
    }).catch(function () {
      toast('danger', 'The schedule could not be saved.');
    }).finally(function () {
      if (button) {
        button.disabled = false;
        button.innerHTML = original;
      }
    });
  }

  function wireScheduleFields() {
    var form      = document.getElementById('backupsScheduleForm');
    var frequency = document.getElementById('backupsScheduleFrequency');
    var weekday   = document.getElementById('backupsScheduleWeekdayWrap');
    var enabled   = document.getElementById('backupsScheduleEnabled');
    var label     = document.getElementById('backupsScheduleEnabledLabel');

    if (frequency && weekday) {
      var syncWeekday = function () {
        weekday.classList.toggle('d-none', frequency.value !== 'weekly');
      };

      frequency.addEventListener('change', syncWeekday);
      syncWeekday();
    }

    if (enabled && label) {
      enabled.addEventListener('change', function () {
        label.textContent = enabled.checked ? 'Enabled' : 'Paused';
      });
    }

    if (form) {
      form.addEventListener('submit', function () {
        var token = form.querySelector('input[name="' + cfg.csrfName + '"]');

        if (token) {
          token.value = csrfHash;
        }
      });
    }
  }

  function handleVerify(form) {
    var row = form.closest('.backups-row');

    if (!row) {
      return Promise.resolve();
    }

    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; }

    return verifyRow(row, false).then(function () {
      if (button) { button.disabled = false; }
      applyFilters();
    });
  }


  function handleDelete(form) {
    var modal   = document.getElementById('backupsDeleteModal');
    var spinner = document.getElementById('backupsDeleteSpinner');
    var submit  = document.getElementById('backupsDeleteSubmit');

    if (spinner) { spinner.classList.remove('d-none'); }
    if (submit)  { submit.disabled = true; }

    return postForm(form.action, formBody()).then(function (result) {
      if (result.sessionExpired) {
        sessionExpiredToast();
        return;
      }

      var data = result.data || {};
      applyCsrf(data);

      if (data.success) {
        var deleted = data.deleted || {};
        var id  = String(deleted.id || '');
        var row = id !== '' ? document.getElementById('backupRow' + id) : null;
        var size = row ? Number(row.getAttribute('data-size') || 0) : 0;

        if (row && row.parentNode) {
          row.parentNode.removeChild(row);
        }

        bumpCounts(-1, -size);
        applyFilters();

        if (modal && window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }

        toast('success', data.message || 'Backup deleted.');
        flashAndReload('success', data.message || 'Backup deleted.');
      } else {
        toast('danger', data.error || 'The backup could not be deleted.');
        if (submit) { submit.disabled = false; }
      }
    }).catch(function () {
      toast('danger', 'The delete request failed. Please try again.');
      if (submit) { submit.disabled = false; }
    }).finally(function () {
      if (spinner) { spinner.classList.add('d-none'); }
    });
  }

  function handleRestore(form) {
    var spinner      = document.getElementById('backupsRestoreSpinner');
    var submit       = document.getElementById('backupsRestoreSubmit');
    var confirmField = document.getElementById('backupsRestoreConfirm');
    var idField      = document.getElementById('backupsRestoreId');
    var modal        = document.getElementById('backupsRestoreModal');

    if (spinner) { spinner.classList.remove('d-none'); }
    if (submit)  { submit.disabled = true; }

    beginOperation('restore', null);

    if (modal && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(modal).hide();
    }

    return postForm(form.action, formBody({
      backup_id: idField ? idField.value : '',
      confirm: confirmField ? confirmField.value.trim() : ''
    })).then(function (result) {
      if (result.sessionExpired) {
        finishOperation('error', 'Your session expired before the restore could start.');
        sessionExpiredToast();
        return;
      }

      var data = result.data || {};
      applyCsrf(data);

      if (data.success) {
        finishOperation('success', data.message || 'Database restored.', { operation: 'restore', result: 'ok' });
      } else {
        finishOperation('error', data.error || 'The restore did not complete.', { operation: 'restore', result: 'failed' });
      }
    }).catch(function () {
      finishOperation('error', 'The restore request failed before the server replied. Check the activity log and the stored backups before retrying.');
    }).finally(function () {
      if (spinner) { spinner.classList.add('d-none'); }
      if (submit)  { submit.disabled = false; }
    });
  }

  // -------------------------------------------------------------------
  // Verify all (serial, real i/n progress)
  // -------------------------------------------------------------------

  function handleVerifyAll() {
    var rows = visibleRows();

    if (!rows.length) {
      toast('info', 'There is nothing to verify.');
      return;
    }

    confirmDialog(
      'Verify the integrity of ' + rows.length + ' backup(s)?\nEach dump is re-read and its SHA-256 checksum is compared with the value recorded when it was created.',
      'Verify all backups'
    ).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      var button = document.getElementById('backupsVerifyAll');
      var index  = 0;
      var failed = 0;
      var original = button ? button.innerHTML : '';

      function next() {
        if (index >= rows.length) {
          if (button) { button.innerHTML = original; button.disabled = false; }
          applyFilters();
          toast(failed === 0 ? 'success' : 'warning',
            failed === 0
              ? 'All ' + rows.length + ' backup(s) passed the integrity check.'
              : 'Integrity check finished: ' + (rows.length - failed) + ' healthy, ' + failed + ' need attention.');
          return;
        }

        var row = rows[index];
        index++;

        if (button) {
          button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Verifying ' + index + '/' + rows.length + '…';
        }

        verifyRow(row, true).then(function (ok) {
          if (!ok) { failed++; }
          next();
        });
      }

      if (button) { button.disabled = true; }
      next();
    });
  }


  // -------------------------------------------------------------------
  // Search / filters / sorting / counters
  // -------------------------------------------------------------------

  var searchInput  = document.getElementById('backupsSearch');
  var searchClear  = document.getElementById('backupsSearchClear');
  var kindFilter   = document.getElementById('backupsKindFilter');
  var integrFilter = document.getElementById('backupsIntegrityFilter');
  var filterReset  = document.getElementById('backupsFilterReset');
  var filterForm   = document.getElementById('backupsFilterForm');
  var filterNote   = document.getElementById('backupsFilterNote');
  var shownCount   = document.getElementById('backupsShownCount');
  var emptyFilter  = document.querySelector('.js-backups-empty-filter');

  function allRows() {
    return tableBody ? Array.prototype.slice.call(tableBody.querySelectorAll('.backups-row')) : [];
  }

  function visibleRows() {
    return allRows().filter(function (row) {
      return !row.classList.contains('is-hidden-by-filter');
    });
  }

  function currentFilters() {
    return {
      term: searchInput ? searchInput.value.trim().toLowerCase() : '',
      kind: kindFilter ? kindFilter.value : '',
      integrity: integrFilter ? integrFilter.value : ''
    };
  }

  function applyFilters() {
    var filters = currentFilters();
    var visible = 0;

    allRows().forEach(function (row) {
      var matches = true;

      if (filters.term !== '') {
        var haystack = (row.getAttribute('data-search') || '') + ' ' + row.getAttribute('data-backup-id');
        matches = haystack.indexOf(filters.term) !== -1;
      }

      if (matches && filters.kind !== '') {
        matches = row.getAttribute('data-kind') === filters.kind;
      }

      if (matches && filters.integrity !== '') {
        matches = row.getAttribute('data-integrity') === filters.integrity;
      }

      row.classList.toggle('is-hidden-by-filter', !matches);

      if (matches) { visible++; }
    });

    var total = allRows().length;
    var active = filters.term !== '' || filters.kind !== '' || filters.integrity !== '';

    if (shownCount) {
      shownCount.textContent = 'Showing ' + visible + ' of ' + total;
    }
    if (filterReset) {
      filterReset.classList.toggle('d-none', !active);
    }
    if (searchClear) {
      searchClear.classList.toggle('d-none', filters.term === '');
    }
    if (filterNote) {
      filterNote.classList.toggle('d-none', !active);
      filterNote.textContent = active ? visible + ' match' + (visible === 1 ? '' : 'es') : '';
    }
    if (emptyFilter) {
      emptyFilter.classList.toggle('d-none', !(active && visible === 0 && total > 0));
    }
  }

  function bumpCounts(delta, sizeDelta) {
    var stored = document.getElementById('kpiStoredCount');

    if (stored) {
      var match = stored.textContent.match(/^(\d+)/);

      if (match) {
        var current = parseInt(match[1], 10) + delta;
        stored.innerHTML = Math.max(0, current) + '<span class="fs-6 text-muted fw-normal"> / ' + <?= json_encode((string) $retention) ?> + '</span>';
      }
    }

    var disk = document.getElementById('kpiDiskUsage');

    if (disk) {
      var bytes = Math.max(0, Number(disk.getAttribute('data-bytes') || 0) + sizeDelta);
      disk.setAttribute('data-bytes', String(bytes));
      disk.textContent = formatBytes(bytes);
    }
  }

  function sortRows(key, dir) {
    var rows = allRows();

    rows.sort(function (a, b) {
      var left  = a.getAttribute('data-sort-' + key) || '';
      var right = b.getAttribute('data-sort-' + key) || '';

      var leftNum  = Number(left);
      var rightNum = Number(right);

      var comparison;
      if (!isNaN(leftNum) && !isNaN(rightNum) && left !== '' && right !== '') {
        comparison = leftNum - rightNum;
      } else {
        comparison = left.localeCompare(right);
      }

      return dir === 'asc' ? comparison : -comparison;
    });

    rows.forEach(function (row) {
      tableBody.appendChild(row);
    });
  }

  function wireSorting() {
    var headers = document.querySelectorAll('#backupsTable th.backups-sortable');

    Array.prototype.forEach.call(headers, function (th) {
      th.setAttribute('role', 'button');
      th.setAttribute('tabindex', '0');
      th.setAttribute('title', 'Sort by this column');

      function activate() {
        var key = th.getAttribute('data-sort-key');
        var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';

        Array.prototype.forEach.call(headers, function (other) {
          other.classList.remove('backups-sort-asc', 'backups-sort-desc');
          other.removeAttribute('data-sort-dir');
        });

        th.setAttribute('data-sort-dir', dir);
        th.classList.add(dir === 'asc' ? 'backups-sort-asc' : 'backups-sort-desc');
        sortRows(key, dir);
      }

      th.addEventListener('click', activate);
      th.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          activate();
        }
      });
    });
  }


  // -------------------------------------------------------------------
  // Modals (restore / delete)
  // -------------------------------------------------------------------

  function wireRestoreModal() {
    var modal = document.getElementById('backupsRestoreModal');

    if (!modal) { return; }

    var idField     = document.getElementById('backupsRestoreId');
    var fileEl      = document.getElementById('backupsRestoreFile');
    var createdEl   = document.getElementById('backupsRestoreCreated');
    var detailsEl   = document.getElementById('backupsRestoreDetails');
    var confirmEl   = document.getElementById('backupsRestoreConfirm');
    var submit      = document.getElementById('backupsRestoreSubmit');
    var blockedBox  = document.getElementById('backupsRestoreBlocked');
    var blockedText = document.getElementById('backupsRestoreBlockedText');
    var previous    = document.getElementById('backupsRestorePrevious');
    var prevCount   = document.getElementById('backupsRestorePreviousCount');
    var restorable  = false;

    function syncSubmit() {
      if (!submit) { return; }

      var typed = confirmEl ? confirmEl.value.trim().toUpperCase() : '';
      submit.disabled = !restorable || typed !== String(cfg.confirmPhrase || 'RESTORE').toUpperCase();
    }

    modal.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget;

      if (!trigger) { return; }

      restorable = trigger.getAttribute('data-backup-restorable') === '1';

      if (idField)   { idField.value = trigger.getAttribute('data-backup-id') || ''; }
      if (fileEl)    { fileEl.textContent = trigger.getAttribute('data-backup-file') || '—'; }
      if (createdEl) { createdEl.textContent = trigger.getAttribute('data-backup-created') || '—'; }

      if (detailsEl) {
        detailsEl.textContent = (trigger.getAttribute('data-backup-size') || '—')
          + ' · ' + (trigger.getAttribute('data-backup-tables') || '0') + ' table(s)'
          + ' · ' + (trigger.getAttribute('data-backup-integrity') || '—');
      }

      if (previous && prevCount) {
        var restores = parseInt(trigger.getAttribute('data-backup-restores') || '0', 10);
        prevCount.textContent = String(restores);
        previous.classList.toggle('d-none', !(restores > 0));
      }

      var blockedReason = trigger.getAttribute('data-backup-blocked-reason') || '';

      if (blockedBox && blockedText) {
        blockedBox.classList.toggle('d-none', restorable || blockedReason === '');
        blockedText.textContent = blockedReason;
      }

      if (confirmEl) { confirmEl.value = ''; }
      syncSubmit();

      if (confirmEl && restorable) {
        setTimeout(function () { confirmEl.focus(); }, 200);
      }
    });

    if (confirmEl) {
      confirmEl.addEventListener('input', syncSubmit);
    }

    modal.addEventListener('hidden.bs.modal', function () {
      if (confirmEl) { confirmEl.value = ''; }
      syncSubmit();
    });
  }

  function wireDeleteModal() {
    var modal = document.getElementById('backupsDeleteModal');

    if (!modal) { return; }

    var form    = document.getElementById('backupsDeleteForm');
    var fileEl  = document.getElementById('backupsDeleteFile');
    var metaEl  = document.getElementById('backupsDeleteMeta');
    var missing = document.getElementById('backupsDeleteMissingNote');
    var warning = document.getElementById('backupsDeleteWarning');
    var submit  = document.getElementById('backupsDeleteSubmit');
    var spinner = document.getElementById('backupsDeleteSpinner');

    modal.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget;

      if (!trigger) { return; }

      if (form) {
        form.action = cfg.deleteUrl + (trigger.getAttribute('data-backup-id') || '');
      }

      if (fileEl) { fileEl.textContent = trigger.getAttribute('data-backup-file') || '—'; }
      if (metaEl) {
        metaEl.textContent = (trigger.getAttribute('data-backup-kind') || 'Backup')
          + ' · ' + (trigger.getAttribute('data-backup-size') || '—')
          + ' · created ' + (trigger.getAttribute('data-backup-created') || '—');
      }

      var fileExists = trigger.getAttribute('data-backup-file-exists') === '1';

      if (missing) { missing.classList.toggle('d-none', fileExists); }
      if (warning) { warning.classList.toggle('d-none', !fileExists); }
      if (submit)  { submit.disabled = false; }
      if (spinner) { spinner.classList.add('d-none'); }
    });
  }


  // -------------------------------------------------------------------
  // Clipboard, session flash and form interception
  // -------------------------------------------------------------------

  function legacyCopy(text, done) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', 'readonly');
    area.style.cssText = 'position: absolute; left: -9999px;';
    document.body.appendChild(area);
    area.select();

    try {
      document.execCommand('copy');
      done();
    } catch (e) {
      toast('warning', 'Copying is not supported by this browser — select the text manually.');
    }

    document.body.removeChild(area);
  }

  function wireCopyButtons() {
    Array.prototype.forEach.call(document.querySelectorAll('.js-backups-copy'), function (button) {
      button.addEventListener('click', function () {
        var target = document.getElementById(button.getAttribute('data-copy-target') || '');
        var text   = target ? (target.value !== undefined ? target.value : target.textContent) : '';

        if (!text) { return; }

        function done() {
          toast('success', 'Copied to the clipboard.');
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { legacyCopy(text, done); });
        } else {
          legacyCopy(text, done);
        }
      });
    });
  }

  function wireFormInterception() {
    document.addEventListener('submit', function (event) {
      var form = event.target;

      if (!form || !form.getAttribute('data-backups-action')) {
        return;
      }

      var action = form.getAttribute('data-backups-action');

      if (action === 'create') {
        event.preventDefault();
        handleCreate(form);
      } else if (action === 'verify') {
        event.preventDefault();
        handleVerify(form);
      } else if (action === 'delete') {
        event.preventDefault();
        handleDelete(form);
      } else if (action === 'restore') {
        event.preventDefault();
        handleRestore(form);
      } else if (action === 'schedule') {
        event.preventDefault();
        handleSchedule(form);
      }
    });
  }

  function restoreFlashFromSession() {
    var raw = null;

    try {
      raw = sessionStorage.getItem('backups.flash');
      sessionStorage.removeItem('backups.flash');
    } catch (e) {
      return;
    }

    if (!raw) { return; }

    try {
      var flash = JSON.parse(raw);

      if (flash && flash.message) {
        toast(flash.type === 'success' ? 'success' : 'danger', flash.message);
      }
    } catch (e) { /* ignore a corrupt flash */ }
  }

  // -------------------------------------------------------------------
  // Init
  // -------------------------------------------------------------------

  function init() {
    wireSorting();
    wireRestoreModal();
    wireDeleteModal();
    wireCopyButtons();
    wireFormInterception();
    wireScheduleFields();
    restoreFlashFromSession();
    applyFilters();

    var verifyAll = document.getElementById('backupsVerifyAll');

    if (verifyAll && allRows().length > 0) {
      verifyAll.disabled = false;
      verifyAll.addEventListener('click', handleVerifyAll);
    }

    var refresh = document.getElementById('backupsRefreshBtn');

    if (refresh) {
      refresh.addEventListener('click', function () { location.reload(); });
    }

    if (searchInput) {
      var debounce = null;
      searchInput.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilters, 150);
      });
    }

    if (searchClear) {
      searchClear.addEventListener('click', function () {
        if (searchInput) { searchInput.value = ''; }
        applyFilters();
        if (searchInput) { searchInput.focus(); }
      });
    }

    if (kindFilter)   { kindFilter.addEventListener('change', applyFilters); }
    if (integrFilter) { integrFilter.addEventListener('change', applyFilters); }

    if (filterReset) {
      filterReset.addEventListener('click', function () {
        if (searchInput)  { searchInput.value = ''; }
        if (kindFilter)   { kindFilter.value = ''; }
        if (integrFilter) { integrFilter.value = ''; }
        applyFilters();
      });
    }

    if (filterForm) {
      // The filters run client-side, so a submit (Enter in the search box)
      // must not reload the page and discard them.
      filterForm.addEventListener('submit', function (event) { event.preventDefault(); });
    }

    // Re-attach to an operation that is still running (any browser session).
    if (cfg.initialOperation && cfg.initialOperation.running) {
      attachToRunningOperation(cfg.initialOperation);
    } else {
      pollStatus();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<?= $this->endSection() ?>

