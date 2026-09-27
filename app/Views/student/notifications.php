<?= $this->extend('dashboard_layout') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Notifications</h3>
                </div>
                <div class="card-body">
                    <?php if (session()->getFlashdata('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= session()->getFlashdata('success') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= session()->getFlashdata('error') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($notifications)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No notifications found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <tr class="<?= $notification['is_read'] ? '' : 'table-warning' ?>">
                                            <td>
                                                <?php if ($notification['is_read']): ?>
                                                    <span class="badge bg-secondary">Read</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Unread</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= esc($notification['title']) ?></td>
                                            <td><?= esc(substr($notification['message'], 0, 100)) ?><?= strlen($notification['message']) > 100 ? '...' : '' ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
