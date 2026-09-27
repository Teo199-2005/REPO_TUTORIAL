/*
 * Shared styles for the login form and its arithmetic CAPTCHA (identifier /
 * password / CAPTCHA question / remember-me / submit). Included by the login
 * page (auth/login.php) via partials/login_form.php, which also pulls in the
 * CAPTCHA modal that uses the .captcha-* rules.
 *
 * The selectors mirror the existing login card: white fields on the dark blue
 * panel, amber accents, 42px control heights.
 */
.form-control, .custom-field, input[type="text"], input[type="password"], input[type="email"],
input[type="tel"], input[type="date"], input[type="number"], select.form-select, select {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0.5rem 0.875rem;
  font-size: 0.92rem;
  background: #ffffff;
  color: #1e293b;
  font-weight: 400;
  height: 42px;
  line-height: 1.4;
  transition: all 0.2s ease;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
  box-sizing: border-box;
}

.form-control:focus, .custom-field:focus, input[type="text"]:focus, input[type="password"]:focus,
input[type="email"]:focus, input[type="tel"]:focus, input[type="date"]:focus,
input[type="number"]:focus, select.form-select:focus, select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  background: white;
}

.form-control::placeholder, .custom-field::placeholder {
  color: #94a3b8; font-weight: 400;
}

.custom-input-group { position: relative; margin-bottom: 0.875rem; }
.custom-input-group .input-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: #94a3b8; font-size: 0.95rem; z-index: 2; pointer-events: none;
}
.custom-input-group .custom-field { padding-left: 2.4rem !important; height: 42px; }
.custom-input-group .custom-field:focus ~ .input-icon { color: #3b82f6; }

.password-toggle-btn {
  position: absolute !important; right: 4px !important; top: 50% !important;
  transform: translateY(-50%) !important; border: none !important;
  background: none !important; color: #6b7280 !important; z-index: 5 !important;
  padding: 0 !important; width: 36px !important; height: 36px !important;
  display: flex !important; align-items: center !important;
  justify-content: center !important; cursor: pointer !important;
  -webkit-tap-highlight-color: transparent !important; border-radius: 6px !important;
}
.password-toggle-btn:hover, .password-toggle-btn:active {
  background: rgba(107, 114, 128, 0.1) !important; color: #3b82f6 !important;
}
.password-input-wrapper .custom-field { padding-right: 44px !important; }

/* Arithmetic CAPTCHA block */
.captcha-section { margin-bottom: 0.875rem; }
.captcha-label {
  display: block; color: rgba(255, 255, 255, 0.95); font-size: 0.85rem;
  font-weight: 400; margin-bottom: 0.5rem; cursor: pointer; user-select: none;
}
.captcha-row {
  display: flex; align-items: stretch; gap: 0.5rem; margin-bottom: 0.5rem;
}
.captcha-question {
  flex: 1; display: flex; align-items: center; justify-content: center;
  background: rgba(15, 23, 42, 0.35);
  border: 1px solid rgba(251, 191, 36, 0.45);
  border-radius: 8px; color: #fbbf24;
  font-size: 1.05rem; font-weight: 700; letter-spacing: 0.06em;
  height: 42px; padding: 0 0.75rem; user-select: all;
  font-variant-numeric: tabular-nums;
}
.captcha-refresh-btn {
  flex-shrink: 0; width: 42px; height: 42px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(107, 114, 128, 0.15); border: 1px solid rgba(251, 191, 36, 0.35);
  border-radius: 8px; color: #fbbf24; font-size: 0.95rem; cursor: pointer;
  transition: all 0.2s ease; -webkit-tap-highlight-color: transparent;
}
.captcha-refresh-btn:hover, .captcha-refresh-btn:active {
  background: rgba(251, 191, 36, 0.2); color: #f59e0b;
}
.captcha-refresh-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.captcha-refresh-btn.is-spinning i { animation: captcha-spin 0.7s linear; }
@keyframes captcha-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.captcha-field { height: 42px; }
.captcha-section.has-error .captcha-question { border-color: #ef4444; }

.remember-section {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.25rem; margin-top: 0.25rem;
}
.form-check { display: flex; align-items: center; gap: 0.5rem; }
.form-check-input {
  width: 0.95rem; height: 0.95rem; border-radius: 4px; border: 1.5px solid #cbd5e1;
  transition: all 0.2s ease; flex-shrink: 0; cursor: pointer;
}
.form-check-input:checked { background-color: #3b82f6; border-color: #3b82f6; }
.form-check-label {
  color: rgba(255, 255, 255, 0.95); font-size: 0.85rem; font-weight: 400;
  margin: 0; cursor: pointer; user-select: none;
}
.forgot-link {
  color: #fbbf24; text-decoration: none; font-size: 0.85rem; font-weight: 400;
  transition: color 0.2s ease; white-space: nowrap;
}

.login-btn {
  width: 100%; padding: 0.625rem 1rem;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
  border: none; border-radius: 8px; color: white; font-weight: 700;
  font-size: 0.9rem; letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  margin-bottom: 1rem; position: relative; overflow: hidden; height: 42px;
  -webkit-tap-highlight-color: transparent;
  box-shadow: 0 4px 14px rgba(251, 191, 36, 0.35);
}
.login-btn:hover {
  transform: translateY(-1px); box-shadow: 0 6px 20px rgba(251, 191, 36, 0.5);
  background: linear-gradient(135deg, #f59e0b 0%, #ea580c 50%, #dc2626 100%);
}
.login-btn:active { transform: translateY(0); }

.register-section {
  text-align: center; padding-top: 1rem;
  border-top: 1px solid rgba(59, 130, 246, 0.15);
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.02) 0%, rgba(147, 197, 253, 0.02) 100%);
  margin: 0 -2.25rem -2.25rem;
  padding-left: 2.25rem; padding-right: 2.25rem; padding-bottom: 1.5rem;
}
.register-text { color: rgba(255, 255, 255, 0.85); font-size: 0.85rem; font-weight: 400; margin: 0; }
.register-link {
  color: #fbbf24; text-decoration: none; font-weight: 700;
  transition: color 0.2s ease; position: relative;
}
.register-link::after {
  content: ''; position: absolute; bottom: -2px; left: 0;
  width: 0; height: 1.5px;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  transition: width 0.3s ease;
}
.register-link:hover { color: #f59e0b; transform: translateY(-1px); }

.alert {
  border: 1px solid; border-radius: 8px;
  padding: 0.5rem 0.875rem; margin-bottom: 0.875rem; font-size: 0.82rem;
}
.alert-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.alert-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }

@media (max-width: 767.98px) {
  .remember-section { flex-direction: column; gap: 0.5rem; align-items: flex-start; margin-bottom: 1rem; }
  .login-btn { padding: 0.55rem 1rem; font-size: 0.85rem; min-height: 40px; margin-bottom: 0.875rem; }
  .alert { padding: 0.45rem 0.7rem; font-size: 0.78rem; }
  .form-control, .custom-field, input[type="text"], input[type="password"], input[type="email"] {
    font-size: 16px; height: 40px; padding: 0.45rem 0.75rem;
  }
  .captcha-question, .captcha-refresh-btn, .captcha-field { height: 40px; }
}

.forgot-link:hover { color: #f59e0b; text-decoration: underline; }

/*
 * Breakpoint overrides. These live here (not in the page stylesheet) because
 * the shared <style> is emitted where the form renders - i.e. AFTER the host
 * page's own CSS. Without them, the base rules above would win on small
 * screens and undo the compact mobile sizing.
 */
@media (max-width: 480px) {
  .form-control, .custom-field, input[type="text"], input[type="password"], input[type="email"] {
    height: 38px; padding: 0.4rem 0.6rem;
  }
  .custom-input-group .custom-field { padding-left: 2rem !important; }
  .custom-input-group .input-icon { left: 10px; font-size: 0.85rem; }
  .password-input-wrapper .custom-field { padding-right: 36px !important; }
  .password-toggle-btn { width: 30px !important; height: 30px !important; right: 3px !important; }
  .captcha-question, .captcha-refresh-btn, .captcha-field { height: 38px; }
  .captcha-question { font-size: 0.95rem; letter-spacing: 0.03em; }
  .captcha-refresh-btn { width: 38px; }
  .login-btn { height: 38px; font-size: 0.8rem; padding: 0.45rem 0.75rem; margin-bottom: 0.75rem; }
  .remember-section { margin-bottom: 0.875rem; }
}

@media (max-width: 359px) {
  .captcha-question { font-size: 0.88rem; letter-spacing: 0.01em; }
}

@media (max-height: 500px) and (orientation: landscape) {
  .form-control, .custom-field { height: 36px; }
  .captcha-question, .captcha-refresh-btn, .captcha-field { height: 36px; }
  .remember-section { margin-bottom: 0.5rem; }
  .login-btn { height: 36px; font-size: 0.8rem; margin-bottom: 0.5rem; }
}
