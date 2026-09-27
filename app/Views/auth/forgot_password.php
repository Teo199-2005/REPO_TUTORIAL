<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<style>
.login-container {
  height: calc(100vh - 80px);
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 1rem;
  position: relative;
  margin: -2rem -15px 0 -15px;
}

.login-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%233b82f6" stroke-width="0.5" opacity="0.2"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 1;
}

.login-card {
  background: rgba(30, 64, 175, 0.95);
  backdrop-filter: blur(25px);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 20px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(59, 130, 246, 0.2);
  width: 100%;
  max-width: 440px;
  overflow: hidden;
  position: relative;
  z-index: 1;
}

.login-header {
  text-align: center;
  padding: 3rem 2.5rem 2rem;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
  border-bottom: 1px solid rgba(59, 130, 246, 0.1);
}

.login-title {
  font-family: 'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif;
  font-size: 2.25rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
  letter-spacing: 0;
  line-height: 1.2;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ffffff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  color: transparent;
}

.login-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 1rem;
  font-weight: 400;
  margin: 0;
}

.login-form {
  padding: 2rem 2.5rem 2.5rem;
}

.form-control {
  border: var(--hairline);
  border-radius: 14px;
  padding: 1.25rem 1rem;
  font-size: 1rem;
  background: #f8fafc;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-weight: 400;
  height: auto;
}

.form-control:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
  background: white;
  transform: translateY(-1px);
  outline: none;
}

.login-btn {
  width: 100%;
  padding: 1rem;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
  border: none;
  border-radius: 14px;
  color: white;
  font-weight: 700;
  font-size: 1.05rem;
  letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  min-height: 52px;
  -webkit-tap-highlight-color: transparent;
}

.login-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 15px 35px rgba(251, 191, 36, 0.4);
  background: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #dc2626 100%);
}

.register-section {
  text-align: center;
  padding-top: 2rem;
  border-top: 1px solid rgba(59, 130, 246, 0.15);
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.02) 0%, rgba(147, 197, 253, 0.02) 100%);
  margin: 0 -2.5rem -2.5rem;
  padding-left: 2.5rem;
  padding-right: 2.5rem;
  padding-bottom: 2.5rem;
}

.register-text {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.95rem;
  font-weight: 400;
  margin: 0;
}

.register-link {
  color: #fbbf24;
  text-decoration: none;
  font-weight: 700;
  transition: all 0.2s ease;
  position: relative;
}

.register-link:hover {
  color: #f59e0b;
  transform: translateY(-1px);
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

.alert-success {
  background: #f0fdf4;
  color: #16a34a;
}

/* ===== MOBILE RESPONSIVE STYLES ===== */

/* Large tablets and below */
@media (max-width: 991.98px) {
  .login-header {
    padding: 2.5rem 2rem 1.5rem;
  }
  
  .login-title {
    font-size: 2rem;
  }
  
  .login-form {
    padding: 1.5rem 2rem 2rem;
  }
  
  .register-section {
    margin: 0 -2rem -2rem;
    padding-left: 2rem;
    padding-right: 2rem;
    padding-bottom: 2rem;
  }
}

/* Small tablets and large phones */
@media (max-width: 767.98px) {
  .login-container {
    padding: 2rem 0.75rem;
    margin: -2rem -15px 0 -15px;
  }
  
  .login-card {
    max-width: 400px;
    border-radius: 16px;
  }
  
  .login-header {
    padding: 2rem 1.5rem 1.25rem;
  }
  
  .login-title {
    font-size: 1.6rem;
    margin-bottom: 0.5rem;
  }
  
  .login-subtitle {
    font-size: 0.9rem;
  }
  
  .login-form {
    padding: 1.25rem 1.5rem 1.5rem;
  }
  
  .form-control {
    padding: 1rem 0.875rem;
    font-size: 16px; /* prevents zoom on iOS */
    border-radius: 12px;
  }
  
  .login-btn {
    padding: 0.875rem;
    font-size: 1rem;
    min-height: 48px;
    border-radius: 12px;
    margin-bottom: 1.5rem;
  }
  
  .register-section {
    padding-top: 1.25rem;
    margin: 0 -1.5rem -1.5rem;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
    padding-bottom: 1.5rem;
  }
  
  .register-text {
    font-size: 0.9rem;
  }
  
  .alert {
    padding: 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    border-radius: 10px;
  }
}

/* Small phones */
@media (max-width: 480px) {
  .login-container {
    padding: 1.5rem 0.5rem;
  }
  
  .login-card {
    border-radius: 14px;
    max-width: 100%;
  }
  
  .login-header {
    padding: 1.5rem 1rem 1rem;
  }
  
  .login-title {
    font-size: 1.3rem;
  }
  
  .login-subtitle {
    font-size: 0.8rem;
  }
  
  .login-form {
    padding: 1rem 1rem 1.25rem;
  }
  
  .form-control {
    padding: 0.875rem 0.75rem;
    font-size: 16px;
    border-radius: 10px;
  }
  
  .login-btn {
    padding: 0.75rem;
    font-size: 0.9rem;
    min-height: 44px;
    border-radius: 10px;
    margin-bottom: 1rem;
  }
  
  .register-section {
    padding-top: 1rem;
    margin: 0 -1rem -1.25rem;
    padding-left: 1rem;
    padding-right: 1rem;
    padding-bottom: 1.25rem;
  }
  
  .register-text {
    font-size: 0.85rem;
  }
  
  .alert {
    padding: 0.625rem;
    font-size: 0.8rem;
    border-radius: 8px;
  }
  
  .form-text.text-white-50 {
    font-size: 0.78rem;
    margin-top: 0.5rem !important;
  }
}

/* Very small phones (less than 360px) */
@media (max-width: 359px) {
  .login-header {
    padding: 1rem 0.75rem 0.75rem;
  }
  
  .login-title {
    font-size: 1.1rem;
  }
  
  .login-form {
    padding: 0.75rem 0.75rem 1rem;
  }
  
  .form-control {
    padding: 0.75rem 0.625rem;
    font-size: 16px;
  }
  
  .login-btn {
    padding: 0.625rem;
    font-size: 0.85rem;
    min-height: 40px;
  }
  
  .register-section {
    margin: 0 -0.75rem -1rem;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
    padding-bottom: 1rem;
  }
}

/* Landscape mode on small phones */
@media (max-height: 500px) and (orientation: landscape) {
  .login-container {
    height: auto;
    min-height: auto;
    padding: 1rem 0.5rem;
  }
  
  .login-header {
    padding: 1rem 1rem 0.75rem;
  }
  
  .login-title {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
  }
  
  .login-subtitle {
    font-size: 0.8125rem;
  }
  
  .login-form {
    padding: 0.75rem 1rem 1rem;
  }
  
  .form-control {
    padding: 0.625rem 0.75rem;
  }
  
  .login-btn {
    padding: 0.5rem;
    min-height: 38px;
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
  }
  
  .register-section {
    padding-top: 0.75rem;
    margin: 0 -1rem -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
    padding-bottom: 1rem;
  }
}
</style>
<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <h1 class="login-title">Cauayan South Central School</h1>
      <p class="login-subtitle">Reset Password</p>
      <div class="mascot-chip mascot-chip--center" style="margin-top: 0.5rem;">
        <?php /* A padlock reads "this account is protected" faster than the
                   sentence underneath it. Decorative: the subtitle already says
                   what the page is. */ ?>
        <?= mascot_sticker_for('locked', ['loading' => 'eager']) ?>
      </div>
      <?php /* The envelope tells the second half of the story: prove who you are,
                 and the reset link arrives in your inbox. */ ?>
      <div class="mascot-chip mascot-chip--center" style="margin-top: 0.5rem;">
        <?= mascot_img(['name' => 'envelope', 'alt' => '', 'size' => 96, 'loading' => 'eager']) ?>
      </div>
      <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.8rem; margin: 0.5rem 0 0 0;">Enter your PRC License Number (teachers) or LRN (students) to verify your identity.</p>
    </div>

    <div class="login-form">
                <?php if ($success = session('success')): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?= esc($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error = session('error')): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?>
                    </div>
                <?php endif; ?>

      <form method="post" action="<?= base_url('forgot-password/verify') ?>">
        <?= csrf_field() ?>
        
        <div class="mb-3">
          <input type="text" class="form-control" id="identifier" name="identifier"
                 placeholder="PRC License Number or LRN" required>
          <div class="form-text text-white-50 mt-2">
            Teachers: Enter your PRC License Number<br>
            Students: Enter your LRN (Learning Reference Number)
          </div>
        </div>

        <button type="submit" class="login-btn">
          <i class="bi bi-shield-check me-2"></i>VERIFY IDENTITY
        </button>
      </form>

      <div class="register-section">
        <p class="register-text">
          Remember your password? 
          <a href="<?= base_url('login') ?>" class="register-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
          </a>
        </p>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>