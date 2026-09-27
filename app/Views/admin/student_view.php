<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style><?= view('partials/password_requirements_style') ?></style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle dash-icon-inline"></i> 
                        <?= isset($isArchived) && $isArchived ? 'Archived ' : '' ?>Student Application Details
                        <?php if (isset($isArchived) && $isArchived): ?>
                            <span class="badge bg-secondary ms-2">Archived</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?= view('admin/partials/student_details_modal', ['student' => $student, 'documents' => $documents]) ?>
                    
                    <?php if (isset($passwordReset) && $passwordReset): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="student-info-section">
                                <div class="student-info-title">
                                    <i class="bi bi-key"></i>
                                    Password Reset
                                </div>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    Password reset request approved. Set a new password for this student.
                                </div>
                                <form id="passwordResetForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password" class="form-label">New Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="password" name="password"
                                                           minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                                                           autocomplete="new-password" required data-password-indicator>
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                        <i class="bi bi-eye" id="password-icon"></i>
                                                    </button>
                                                </div>
                                                <?= view('partials/password_requirements') ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                        <i class="bi bi-eye" id="confirm_password-icon"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Update Password
                                        </button>
                                        <a href="<?= base_url('admin/password-resets') ?>" class="btn btn-dark">
                                            <i class="bi bi-arrow-left"></i> Back to Password Resets
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle password reset form
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordResetForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                return;
            }
            
            const studentId = <?= $student['id'] ?>;
            
            fetch(`<?= base_url('admin/students/update-password') ?>/${studentId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    password: password,
                    confirm_password: confirmPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = '<?= base_url('admin/password-resets') ?>';
                    }, 2000);
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to update password', 'error');
            });
        });
    }
});

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

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification-toast`;
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
</script>

<style>
/* Additional styles for student info sections */
.student-info-section {
    margin-bottom: 2rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
}

.student-info-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e40af;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.student-info-title i {
    font-size: 1.3rem;
    color: #3b82f6;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.notification-toast.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-toast i {
    margin-right: 8px;
}
</style>

<?= $this->endSection() ?>