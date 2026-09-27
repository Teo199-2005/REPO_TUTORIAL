<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
/* Helper: build a URL that keeps current query params and overrides some */
function annx_page_url(array $overrides = []) {
    $q = array_merge($_GET, $overrides);
    return current_url() . '?' . http_build_query($q);
}
$reg = $pagination['regular'];
$rep = $pagination['reports'];
?>

<style>
/* --- Announcements page (scoped) --- */
.annx-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}
.annx-card .card-header {
  background: transparent;
  border-bottom: 1px solid #eef2f7;
  padding: 0.85rem 1rem;
}
.annx-card .card-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; }
.annx-table { margin: 0; font-size: 0.85rem; }
.annx-table thead th {
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #eef2f7;
  padding: 0.55rem 0.75rem;
  white-space: nowrap;
}
.annx-table tbody td { padding: 0.65rem 0.75rem; vertical-align: middle; border-color: #f1f5f9; }
.annx-table tbody tr:hover { background: #f8fafc; }
.annx-title { font-weight: 700; color: #0f172a; line-height: 1.3; }
.annx-preview {
  color: #64748b;
  font-size: 0.8rem;
  max-width: 340px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.annx-badge {
  font-size: 0.7rem;
  padding: 0.22rem 0.5rem;
  border-radius: 999px;
  font-weight: 700;
}
.annx-seen {
  border: 0;
  background: #eff6ff;
  color: #1e40af;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  transition: background 0.15s ease;
}
.annx-seen:hover { background: #dbeafe; color: #1e3a8a; }
.annx-seen.muted { background: #f1f5f9; color: #94a3b8; cursor: default; }
.annx-seen.muted:hover { background: #f1f5f9; }
.annx-seen .annx-reach { font-weight: 400; color: #94a3b8; }
.annx-empty { text-align: center; padding: 2.5rem 1rem; color: #94a3b8; }
.annx-empty i { font-size: 2.2rem; display: block; margin-bottom: 0.5rem; }
.annx-report-item {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}
.annx-report-item:last-child { border-bottom: 0; }
.annx-report-item:hover { background: #f8fafc; }
.annx-pager-foot {
  border-top: 1px solid #eef2f7;
  padding: 0.6rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  font-size: 0.78rem;
  color: #64748b;
}
.annx-pager-foot .pagination { margin: 0; }
.annx-pager-foot .page-link {
  font-size: 0.8125rem;
  padding: 0.2rem 0.55rem;
  color: #475569;
  border-color: #e2e8f0;
}
.annx-pager-foot .page-item.active .page-link {
  background: #1e40af;
  border-color: #1e40af;
  color: #fff;
}
.annx-reader-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0.25rem;
  border-bottom: 1px solid #f1f5f9;
}
.annx-reader-row:last-child { border-bottom: 0; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 mb-0"><i class="bi bi-megaphone me-2 text-primary"></i>Announcements &amp; Notifications</h1>
    <small class="text-muted">Manage and publish announcements &amp; notifications to your community</small>
  </div>
  <button class="btn btn-primary" onclick="openCreateAnnouncementModal()">
    <i class="bi bi-plus-circle me-2"></i>New Announcement
  </button>
</div>

<?php if ($errors = session('errors')): ?>
  <div class="alert alert-danger py-2">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php if ($success = session('success')): ?>
  <div class="alert alert-success py-2"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error = session('error')): ?>
  <div class="alert alert-danger py-2"><?= esc($error) ?></div>
<?php endif; ?>

<!-- KPI tiles (same tile language as the Sections page) -->
<div class="sections-summary-stats mb-4">
  <div class="stat-tile stat-tile--primary">
    <div class="stat-tile__icon"><i class="bi bi-megaphone"></i></div>
    <div class="stat-tile__body">
      <div class="stat-tile__value" id="totalAnnouncements"><?= $stats['total'] ?></div>
      <div class="stat-tile__label">Total Posts</div>
    </div>
  </div>
  <div class="stat-tile stat-tile--success">
    <div class="stat-tile__icon"><i class="bi bi-check-circle"></i></div>
    <div class="stat-tile__body">
      <div class="stat-tile__value" id="publishedAnnouncements"><?= $stats['published'] ?></div>
      <div class="stat-tile__label">Published</div>
    </div>
  </div>
  <div class="stat-tile stat-tile--info">
    <div class="stat-tile__icon"><i class="bi bi-eye"></i></div>
    <div class="stat-tile__body">
      <div class="stat-tile__value"><?= $stats['total_reads'] ?></div>
      <div class="stat-tile__label">Total Views</div>
    </div>
  </div>
  <div class="stat-tile stat-tile--warning">
    <div class="stat-tile__icon"><i class="bi bi-people"></i></div>
    <div class="stat-tile__body">
      <div class="stat-tile__value"><?= $stats['unique_readers'] ?></div>
      <div class="stat-tile__label">Unique Readers</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- LEFT: Regular Announcements -->
  <div class="col-lg-7 col-xl-8">
    <div class="annx-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title"><i class="bi bi-list-ul me-2 text-primary"></i>Regular Announcements
          <span class="text-muted fw-normal">(<?= $stats['regular_total'] ?>)</span></h4>
        <form method="get" class="d-flex align-items-center gap-2" aria-label="Rows per page">
          <input type="hidden" name="rep_page" value="<?= (int) $rep['page'] ?>">
          <label class="text-muted" style="font-size: 0.8125rem;"><i class="bi bi-list-ul me-1"></i>Rows</label>
          <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50] as $opt): ?>
              <option value="<?= $opt ?>" <?= $reg['per_page'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <?php if (!empty($regular)): ?>
          <table class="table annx-table table-hover">
            <thead>
              <tr>
                <th><i class="bi bi-type me-1"></i>Announcement</th>
                <th><i class="bi bi-bullseye me-1"></i>Target</th>
                <th><i class="bi bi-eye me-1"></i>Seen</th>
                <th><i class="bi bi-calendar3 me-1"></i>Created</th>
                <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($regular as $a): ?>
                <tr>
                  <td>
                    <div class="annx-title"><?= esc($a['title']) ?></div>
                    <div class="annx-preview"><?= esc(substr(strip_tags($a['body']), 0, 110)) ?><?= strlen($a['body']) > 110 ? '...' : '' ?></div>
                  </td>
                  <td><span class="annx-badge bg-secondary-subtle text-secondary-emphasis"><?= esc($a['target_display']) ?></span></td>
                  <td>
                    <?php if ($a['reads_count'] > 0): ?>
                      <button type="button" class="annx-seen" title="Who saw this" onclick="openReadersModal(<?= (int) $a['id'] ?>)">
                        <i class="bi bi-eye"></i><?= $a['reads_count'] ?>
                        <?php if ($a['reach'] > 0): ?><span class="annx-reach">/ <?= $a['reach'] ?></span><?php endif; ?>
                      </button>
                    <?php else: ?>
                      <span class="annx-seen muted" title="Nobody has seen this yet"><i class="bi bi-eye-slash"></i>0
                        <?php if ($a['reach'] > 0): ?><span class="annx-reach">/ <?= $a['reach'] ?></span><?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><small class="text-muted"><?= date('M j, Y', strtotime($a['created_at'])) ?></small></td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                      <a href="<?= base_url('admin/announcements/show/' . $a['id']) ?>" class="btn btn-outline-info btn-sm" title="View"><i class="bi bi-eye"></i></a>
                      <button class="btn btn-outline-warning btn-sm" title="Edit" onclick="openEditAnnouncementModal(<?= (int) $a['id'] ?>)"><i class="bi bi-pencil"></i></button>
                      <button onclick="deleteAnnouncement(<?= (int) $a['id'] ?>, '<?= esc($a['title']) ?>')" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="annx-empty">
            <i class="bi bi-megaphone"></i>
            <h6 class="mb-1">No regular announcements yet</h6>
            <p class="mb-0 small">Create your first announcement to get started.</p>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($reg['total'] > 0): ?>
        <div class="annx-pager-foot">
          <span>Showing <strong><?= (($reg['page'] - 1) * $reg['per_page']) + 1 ?>-<?= min($reg['page'] * $reg['per_page'], $reg['total']) ?></strong> of <?= $reg['total'] ?> announcements</span>
          <nav aria-label="Regular announcements pages">
            <ul class="pagination">
              <li class="page-item <?= $reg['page'] <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= annx_page_url(['page' => $reg['page'] - 1]) ?>"><i class="bi bi-chevron-left"></i></a>
              </li>
              <?php
                $start = max(1, $reg['page'] - 2);
                $end = min($reg['pages'], $start + 4);
                $start = max(1, $end - 4);
              ?>
              <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $reg['page'] ? 'active' : '' ?>">
                  <a class="page-link" href="<?= annx_page_url(['page' => $p]) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $reg['page'] >= $reg['pages'] ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= annx_page_url(['page' => $reg['page'] + 1]) ?>"><i class="bi bi-chevron-right"></i></a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: Class Analytics Reports -->
  <div class="col-lg-5 col-xl-4">
    <div class="annx-card h-100">
      <div class="card-header">
        <h4 class="card-title"><i class="bi bi-bar-chart me-2 text-success"></i>Class Analytics Reports
          <span class="text-muted fw-normal">(<?= $stats['reports_total'] ?>)</span></h4>
      </div>
      <div>
        <?php if (!empty($reports)): ?>
          <?php foreach ($reports as $a): ?>
            <div class="annx-report-item">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div style="min-width: 0;">
                  <div class="annx-title text-truncate" style="max-width: 250px;" title="<?= esc($a['title']) ?>">
                    <?= esc(preg_replace('/^Class Analytics Report\s*-\s*/i', '', $a['title'])) ?>
                  </div>
                  <div class="annx-preview" style="max-width: 270px;"><?= esc(substr(strip_tags($a['body']), 0, 90)) ?><?= strlen($a['body']) > 90 ? '...' : '' ?></div>
                  <div class="mt-1 d-flex flex-wrap gap-2 align-items-center">
                    <span class="annx-badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i>Published</span>
                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($a['created_at'])) ?></small>
                    <?php if ($a['reads_count'] > 0): ?>
                      <button type="button" class="annx-seen" title="Who saw this" onclick="openReadersModal(<?= (int) $a['id'] ?>)">
                        <i class="bi bi-eye"></i><?= $a['reads_count'] ?>
                        <?php if ($a['reach'] > 0): ?><span class="annx-reach">/ <?= $a['reach'] ?></span><?php endif; ?>
                      </button>
                    <?php else: ?>
                      <span class="annx-seen muted" title="Nobody has seen this yet"><i class="bi bi-eye-slash"></i>0</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="btn-group btn-group-sm flex-shrink-0" role="group">
                  <a href="<?= base_url('admin/announcements/show/' . $a['id']) ?>" class="btn btn-outline-info btn-sm" title="View"><i class="bi bi-eye"></i></a>
                  <a href="<?= base_url('admin/announcements/download-pdf/' . $a['id']) ?>" class="btn btn-outline-success btn-sm" title="Download PDF"><i class="bi bi-download"></i></a>
                  <button onclick="deleteAnnouncement(<?= (int) $a['id'] ?>, '<?= esc($a['title']) ?>')" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="annx-empty">
            <i class="bi bi-bar-chart"></i>
            <h6 class="mb-1">No analytics reports yet</h6>
            <p class="mb-0 small">Reports published by teachers will appear here.</p>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($rep['total'] > 0): ?>
        <div class="annx-pager-foot">
          <span>Page <strong><?= $rep['page'] ?></strong> / <?= $rep['pages'] ?> - <?= $rep['total'] ?> reports</span>
          <nav aria-label="Analytics reports pages">
            <ul class="pagination">
              <li class="page-item <?= $rep['page'] <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= annx_page_url(['rep_page' => $rep['page'] - 1]) ?>"><i class="bi bi-chevron-left"></i></a>
              </li>
              <li class="page-item <?= $rep['page'] >= $rep['pages'] ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= annx_page_url(['rep_page' => $rep['page'] + 1]) ?>"><i class="bi bi-chevron-right"></i></a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- ANNX_APPEND -->

<!-- Who-saw-it modal -->
<div class="modal fade" id="readersModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Who Saw This</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <h6 class="mb-3 text-truncate" id="readersTitle"></h6>
      <div id="readersList"></div>
    </div>
    <div class="modal-footer">
      <span class="me-auto text-muted small" id="readersCount"></span>
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>

<script>
function openReadersModal(id) {
  const listEl = document.getElementById('readersList');
  const countEl = document.getElementById('readersCount');
  const titleEl = document.getElementById('readersTitle');
  listEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
  titleEl.textContent = '';
  countEl.textContent = '';

  fetch('<?= base_url('admin/announcements/readers/') ?>' + id, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.text().then(text => ({ ok: r.ok, status: r.status, text: text })))
  .then(res => {
    // A redirect to the login page or a PHP notice would return HTML here, which
    // used to make r.json() throw and surface as a generic "Error loading readers".
    let data;
    try {
      data = JSON.parse(res.text);
    } catch (e) {
      console.error('Readers endpoint returned non-JSON (HTTP ' + res.status + '):', res.text.slice(0, 300));
      throw new Error('The server did not return valid data (HTTP ' + res.status + ').');
    }
    if (!data.success) {
      throw new Error(data.error || 'Failed to load readers');
    }
    titleEl.textContent = data.title || '';
    const readers = Array.isArray(data.readers) ? data.readers : [];
    countEl.textContent = readers.length + ' reader' + (readers.length === 1 ? '' : 's');
    if (!readers.length) {
      listEl.innerHTML = '<div class="annx-empty"><i class="bi bi-eye-slash"></i><span>Nobody has seen this yet.</span></div>';
      return;
    }
    const roleColors = { Admin: 'danger', Teacher: 'primary', Student: 'success', Parent: 'warning' };
    // Plain-JS HTML escaping (this layout has no jQuery).
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
    let html = '';
    readers.forEach(r => {
      const roleCls = roleColors[r.role] || 'secondary';
      const badge = 'bg-' + roleCls + '-subtle text-' + roleCls + '-emphasis';
      html += '<div class="annx-reader-row">' +
        '<div style="min-width: 0;">' +
          '<div class="fw-semibold text-truncate">' + esc(r.name) + '</div>' +
          (r.email ? '<small class="text-muted text-truncate d-block">' + esc(r.email) + '</small>' : '') +
        '</div>' +
        '<div class="text-end flex-shrink-0">' +
          '<span class="annx-badge ' + badge + '">' + esc(r.role) + '</span>' +
          '<div><small class="text-muted"><i class="bi bi-clock me-1"></i>' + esc(r.read_at) + '</small></div>' +
        '</div>' +
      '</div>';
    });
    listEl.innerHTML = html;
  })
  .catch(err => {
    console.error('Failed to load readers:', err);
    listEl.innerHTML = '<div class="alert alert-danger mb-0 py-2">' + String(err && err.message ? err.message : 'Error loading readers.') + '</div>';
  });

  new bootstrap.Modal(document.getElementById('readersModal')).show();
}
</script>
<script>
// Build Create Announcement Modal
function buildCreateAnnouncementModal() {
  const existing = document.getElementById('createAnnouncementModal');
  if (existing) existing.remove();
  const html = `
  <div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>New Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= base_url('admin/announcements/store') ?>">
        <?= str_replace(["\n","\r"], '', csrf_field()) ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" maxlength="255" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Target Audience</label>
              <select name="target_roles" id="modalTargetRoles" class="form-select" required>
                <option value="">Select target</option>
                <option value="all">All Users</option>
                <option value="admin">Administrators</option>
                <option value="teacher">All Teachers</option>
                <option value="student">All Students</option>
                <option value="specific_grade">Grade Level (Multiple)</option>
                <option value="specific_section">Section</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Priority</label>
              <select name="priority" class="form-select">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="row" id="modalGradeLevelRow" style="display:none;">
            <div class="col-md-6 mb-3">
              <label class="form-label">Grade Level</label>
              <select name="grade_level" id="modalGradeLevel" class="form-select">
                <option value="">Select Grade</option>
                <?php foreach (grade_level_options() as $g): ?>
                  <option value="<?= $g ?>"><?= esc(grade_level_label($g)) ?></option>
                <?php endforeach; ?>
              </select>
              <div id="modalGradeLevelCheckboxes" class="mt-2" style="display:none; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px; background-color: #f8f9fa;">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="0" id="grade0">
                  <label class="form-check-label small" for="grade0">Kindergarten</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="1" id="grade1">
                  <label class="form-check-label small" for="grade1">Grade 1</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="2" id="grade2">
                  <label class="form-check-label small" for="grade2">Grade 2</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="3" id="grade3">
                  <label class="form-check-label small" for="grade3">Grade 3</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="4" id="grade4">
                  <label class="form-check-label small" for="grade4">Grade 4</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="5" id="grade5">
                  <label class="form-check-label small" for="grade5">Grade 5</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="6" id="grade6">
                  <label class="form-check-label small" for="grade6">Grade 6</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="grade_levels[]" value="7" id="grade7">
                  <label class="form-check-label small" for="grade7">SNED (Special Needs Education)</label>
                </div>
              </div>
              <small class="form-text text-muted" id="gradeSelectHint">Select a single grade or use checkboxes for multiple grades</small>
            </div>
            <div class="col-md-6 mb-3" id="modalSectionCol" style="display:none;">
              <label class="form-label">Section</label>
              <select name="section_id" id="modalSectionId" class="form-select">
                <option value="">Select Section</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Content</label>
            <textarea name="body" class="form-control" rows="6" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Publish</button>
        </div>
      </form>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('createAnnouncementModal');
}

function openCreateAnnouncementModal() {
  const el = buildCreateAnnouncementModal();
  const modal = new bootstrap.Modal(el, { backdrop: true, keyboard: true, focus: true });
  modal.show();
  
  // Setup event listeners after modal is shown
  setTimeout(() => {
    const targetRoles = document.getElementById('modalTargetRoles');
    const gradeLevel = document.getElementById('modalGradeLevel');
    
    if (targetRoles) {
      targetRoles.addEventListener('change', handleModalTargetChange);
    }
    if (gradeLevel) {
      gradeLevel.addEventListener('change', loadModalSections);
    }
  }, 100);
}

  function handleModalTargetChange() {
  const target = document.getElementById('modalTargetRoles').value;
  const gradeRow = document.getElementById('modalGradeLevelRow');
  const sectionCol = document.getElementById('modalSectionCol');
  const gradeSelect = document.getElementById('modalGradeLevel');
  const gradeCheckboxes = document.getElementById('modalGradeLevelCheckboxes');
  const gradeHint = document.getElementById('gradeSelectHint');
  const sectionSelect = document.getElementById('modalSectionId');
  
  gradeSelect.value = '';
  // Uncheck all grade checkboxes
  const checkboxes = document.querySelectorAll('input[name="grade_levels[]"]');
  checkboxes.forEach(cb => cb.checked = false);
  sectionSelect.value = '';
  sectionSelect.innerHTML = '<option value="">Select Section</option>';
  
  if (target === 'specific_grade') {
    gradeRow.style.display = 'flex';
    sectionCol.style.display = 'none';
    gradeSelect.required = false;
    gradeCheckboxes.style.display = 'block';
    gradeHint.style.display = 'block';
    sectionSelect.required = false;
  } else if (target === 'specific_section') {
    gradeRow.style.display = 'flex';
    sectionCol.style.display = 'block';
    gradeSelect.required = true;
    gradeCheckboxes.style.display = 'none';
    gradeHint.style.display = 'none';
    sectionSelect.required = true;
  } else {
    gradeRow.style.display = 'none';
    sectionCol.style.display = 'none';
    gradeSelect.required = false;
    gradeCheckboxes.style.display = 'none';
    gradeHint.style.display = 'none';
    sectionSelect.required = false;
  }
}

  function loadModalSections() {
  const gradeLevel = document.getElementById('modalGradeLevel').value;
  const sectionSelect = document.getElementById('modalSectionId');
  
  if (!gradeLevel) {
    sectionSelect.innerHTML = '<option value="">Select Section</option>';
    return;
  }
  
  sectionSelect.innerHTML = '<option value="">Loading...</option>';
  
  fetch(`<?= base_url('admin/announcements/get-sections') ?>?grade_level=${gradeLevel}`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      console.log('Sections data:', data);
      if (data.success) {
        if (data.sections && data.sections.length > 0) {
          let options = '<option value="">Select Section</option>';
          data.sections.forEach(section => {
            options += `<option value="${section.id}">${section.section_name} (${formatGradeLevel(section.grade_level)})</option>`;
          });
          sectionSelect.innerHTML = options;
        } else {
          sectionSelect.innerHTML = '<option value="">No sections found for ' + formatGradeLevel(gradeLevel) + '</option>';
        }
      } else {
        sectionSelect.innerHTML = '<option value="">Error: ' + (data.message || 'Unknown error') + '</option>';
        console.error('API Error:', data.message);
      }
    })
    .catch(error => {
      console.error('Fetch Error:', error);
      sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
    });
}

  function formatGradeLevel(gradeLevel) {
    const key = String(gradeLevel);
    if (window.gradeLevelLabels && window.gradeLevelLabels[key]) return window.gradeLevelLabels[key];
    if (gradeLevel == 0) return 'Kindergarten';
    if (gradeLevel == 7) return 'SNED';
    return 'Grade ' + gradeLevel;
  }

// Build Edit Modal
function buildEditAnnouncementModal(announcement) {
  const existing = document.getElementById('editAnnouncementModal');
  if (existing) existing.remove();
  const html = `
  <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= base_url('admin/announcements/update/') ?>${announcement.id}">
        <?= str_replace(["\n","\r"], '', csrf_field()) ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" maxlength="255" required value="${announcement.title || ''}">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Target Audience</label>
              <select name="target_roles" class="form-select" required>
                ${['all','admin','teacher','student','grade_0','grade_1','grade_2','grade_3','grade_4','grade_5','grade_6'].map(opt => {
                  const label = opt.startsWith('grade_') ? formatGradeLevel(opt.split('_')[1]) : (opt.charAt(0).toUpperCase()+opt.slice(1));
                  return `<option value="${opt}" ${announcement.target_roles===opt?'selected':''}>${label}</option>`;
                }).join('')}
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Published At</label>
              <input type="text" class="form-control" value="${announcement.published_at || ''}" readonly>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Content</label>
            <textarea name="body" class="form-control" rows="6" required>${announcement.body || ''}</textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
        </div>
      </form>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('editAnnouncementModal');
}

function openEditAnnouncementModal(id) {
  fetch('<?= base_url('admin/announcements/get/') ?>' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert(data.message || 'Failed to load announcement'); return; }
      const el = buildEditAnnouncementModal(data.announcement);
      new bootstrap.Modal(el, { backdrop: true, keyboard: true, focus: true }).show();
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>

<script>
// Delete announcement function
async function deleteAnnouncement(id, title) {
  const ok = await customConfirm(
    `Are you sure you want to delete the announcement "${title}"? This action cannot be undone.`,
    'Delete Announcement'
  );
  if (!ok) return;

  // Create a form and submit it
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= base_url('admin/announcements/delete/') ?>' + id;

  // Add CSRF token
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = '<?= csrf_token() ?>';
  csrfInput.value = '<?= csrf_hash() ?>';
  form.appendChild(csrfInput);

  document.body.appendChild(form);
  form.submit();
}



// Refresh statistics
function refreshStats() {
  fetch('<?= base_url('admin/announcements/getStats') ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
    }
  })
  .then(response => response.json())
  .then(data => {
    document.getElementById('totalAnnouncements').textContent = data.total;
    document.getElementById('publishedAnnouncements').textContent = data.published;
  })
  .catch(error => {
    console.error('Error refreshing stats:', error);
  });
}

// Auto-refresh stats every 60 seconds
setInterval(refreshStats, 60000);
</script>

<?= $this->endSection() ?>
