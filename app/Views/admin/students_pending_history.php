<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history text-info"></i> 
                        Application History
                        <span class="badge bg-info ms-2"><?= $totalRecords ?></span>
                    </h5>
                    <div>
                        <a href="<?= base_url('admin/students/pending') ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Pending
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($processedStudents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-archive text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No Application History</h4>
                            <p class="text-muted">No applications have been processed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade Level</th>
                                        <th>Applied Date</th>
                                        <th>Processed Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($processedStudents as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-<?= $student['enrollment_status'] === 'enrolled' ? 'success' : 'danger' ?> text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= esc(trim($student['first_name'] . ' ' . (!empty($student['middle_name']) ? strtoupper(substr($student['middle_name'], 0, 1)) . '. ' : '') . $student['last_name'] . (!empty($student['suffix']) ? ' ' . $student['suffix'] : ''))) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= esc($student['email'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $createdDate = new DateTime($student['created_at']);
                                                $createdDate->setTimezone(new DateTimeZone('Asia/Manila'));
                                                ?>
                                                <?= $createdDate->format('M j, Y') ?>
                                                <br><small class="text-muted"><?= $createdDate->format('g:i A') ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $updatedDate = new DateTime($student['updated_at']);
                                                $updatedDate->setTimezone(new DateTimeZone('Asia/Manila'));
                                                ?>
                                                <?= $updatedDate->format('M j, Y') ?>
                                                <br><small class="text-muted"><?= $updatedDate->format('g:i A') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($student['enrollment_status'] === 'enrolled'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-outline-info btn-sm" 
                                                        onclick="viewStudentDetails(<?= $student['id'] ?>)">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php
                            $firstShown = ($currentPage - 1) * $perPage + 1;
                            $lastShown  = min($currentPage * $perPage, $totalRecords);

                            // Windowed page list: 1 … 4 5 6 … 12
                            $window    = 1; // pages shown on each side of current
                            $pageLinks = [];
                            for ($i = 1; $i <= $totalPages; $i++) {
                                if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $window) {
                                    $pageLinks[] = $i;
                                }
                            }
                            // Re-add ellipsis markers where numbers were skipped.
                            $paged = [];
                            $prev  = 0;
                            foreach ($pageLinks as $p) {
                                if ($prev && $p - $prev > 1) {
                                    $paged[] = '…';
                                }
                                $paged[] = $p;
                                $prev = $p;
                            }
                        ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
                            <span class="text-muted small">
                                Showing <strong><?= $firstShown ?></strong>&ndash;<strong><?= $lastShown ?></strong>
                                of <strong><?= $totalRecords ?></strong> application<?= $totalRecords === 1 ? '' : 's' ?>
                            </span>
                            <nav aria-label="Application history pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= max(1, $currentPage - 1) ?>" aria-label="Previous page">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                    <?php foreach ($paged as $p): ?>
                                        <?php if ($p === '…'): ?>
                                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                        <?php else: ?>
                                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                                                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= min($totalPages, $currentPage + 1) ?>" aria-label="Next page">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewStudentDetails(studentId) {
    window.location.href = `<?= base_url('admin/students/view/') ?>${studentId}`;
}
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 16px;
    font-weight: bold;
}
</style>

<?= $this->endSection() ?>