<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc($title ?? 'CSCS Tap n Track') ?></title>
  <?= view('partials/site_head_meta', [
    'headMetaTitle' => $title ?? 'CSCS Tap n Track',
    'headMetaDescription' => $headMetaDescription ?? null,
  ]) ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- No webfont request: the platform renders in Times New Roman throughout, which
       every target OS ships, so nothing is downloaded and there is no flash of
       unstyled text. The family itself is declared in css/app.css. -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="<?= asset_url('css/app.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/responsive.css') ?>" rel="stylesheet" />
  <?php
    $locCss   = asset_file_path('css/location-selector.css');
    $mascotCss = asset_file_path('css/mascot.css');
    $depthCss  = asset_file_path('css/ui-depth.css');
  ?>
  <link href="<?= asset_url('css/location-selector.css') ?>?v=<?= $locCss !== null ? filemtime($locCss) : '1' ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/mascot.css') ?>?v=<?= $mascotCss !== null ? filemtime($mascotCss) : '1' ?>" rel="stylesheet" />
  <!-- Surface-quality layer: glass panels, white/gray table stripes and
       colour-tinted button shadows. MUST stay last among the local
       stylesheets so it wins the cascade over the `!important` flat-surface
       rules in app.css and responsive.css. -->
  <link href="<?= asset_url('css/ui-depth.css') ?>?v=<?= $depthCss !== null ? filemtime($depthCss) : '1' ?>" rel="stylesheet" />
</head>
<!-- One <body> only. The old markup opened a second <body> straight after this
     one to start the header; browsers silently merge the stray tag, so it looked
     harmless, but it meant the body attributes above (data-mascot-base) belonged
     to an element the parser had already closed. -->
<body data-mascot-base="<?= esc(asset_url('assets/mascot/')) ?>"
      data-mascot-basepath="<?= esc(mascot_base_path()) ?>">
  <header class="site-header py-2">
    <div class="container-fluid d-flex justify-content-between align-items-center px-4" style="height:64px">
      <?php
        $brandHref = base_url();
        if (!empty($loggedIn) && $loggedIn) {
          $brandHref = base_url('dashboard');
        }
      ?>
      <a class="site-brand flex-shrink-0" href="<?= $brandHref ?>" aria-label="CSCS Tap n Track Home" style="margin-right: 2rem;">
          <img src="<?= asset_url('LPHS2.png') ?>" alt="CSCS" width="64" height="64" />
        <span class="site-brand-title-text">Cauayan South Central School<span style="position: absolute; bottom: 0px; left: 0; right: 0; height: 1px; background: white;"></span><span style="position: absolute; bottom: -2px; left: 0; right: 0; height: 1px; background: white;"></span></span>
      </a>
      <button class="nav-toggle d-md-none" type="button" aria-label="Toggle navigation" id="navToggle" aria-controls="siteNav" aria-expanded="false">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <nav class="site-nav ms-auto" id="siteNav" role="navigation" aria-label="Main navigation">
        <?php
          $loggedIn = false;
          $user = null;
          try {
            $authService = auth();
            $loggedIn = $authService->loggedIn();
            if ($loggedIn) {
              $user = $authService->user();
            }
          } catch (\Throwable $e) {
            $loggedIn = false; // DB not configured yet, fall back to public nav
          }

          // Get current path for active navigation highlighting
          $currentPath = rtrim(parse_url(current_url(), PHP_URL_PATH) ?: '', '/');
          $currentSegment = trim(str_replace(rtrim(parse_url(base_url(), PHP_URL_PATH) ?: '', '/'), '', $currentPath), '/');
          if (empty($currentSegment)) {
            $currentSegment = 'home';
          }
        ?>
        <?php if ($loggedIn): ?>
          <?php /* $user->email is deliberately NOT used for the greeting. On
                   Shield's User entity ->email resolves to the identity secret,
                   and legacy rows of this app stored the password hash in that
                   column, so it printed a bcrypt string into the page source for
                   every signed-in visitor. user_display_name() checks each
                   candidate by shape, so a bad row degrades to a role label
                   instead of leaking. See AuditLogger::describeActor(), which
                   documents the same hazard for the audit trail.

                   The role label is the last link in that chain: without it a user
                   with no usable name column would render a bare "Welcome" with
                   nothing after it, which reads as a bug in its own right. */ ?>
          <?php
            $greetingName = function_exists('user_display_name') ? user_display_name($user) : '';
            if ($greetingName === '' && function_exists('user_role_label')) {
                $greetingName = user_role_label($user);
            }
          ?>
          <span class="text-white nav-user-greeting">Welcome<?= $greetingName !== '' ? ', ' . esc($greetingName) : '' ?></span>

          <?php if ($user && function_exists('user_is_any_admin') && user_is_any_admin($user)): ?>
            <a href="<?= base_url('admin/dashboard') ?>" class="text-white text-decoration-none me-3" title="Admin Dashboard"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>
          <?php elseif ($user && $user->inGroup('teacher')): ?>
            <a href="<?= base_url('teacher/dashboard') ?>" class="text-white text-decoration-none me-3" title="Teacher Dashboard"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>
          <?php elseif ($user && $user->inGroup('student')): ?>
            <a href="<?= base_url('student/dashboard') ?>" class="text-white text-decoration-none me-3" title="Student Dashboard"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>
          <?php elseif ($user && $user->inGroup('parent')): ?>
            <a href="<?= base_url('parent/dashboard') ?>" class="text-white text-decoration-none me-3" title="Parent Dashboard"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard</a>
          <?php endif; ?>

          <a href="<?= base_url('announcements') ?>" class="text-white text-decoration-none me-3" title="View Announcements"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>Announcements</a>
          <a href="#" class="text-white text-decoration-none me-3" data-bs-toggle="modal" data-bs-target="#schoolMaterialsModal" role="button" title="School Materials"><i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Materials</a>
          <div class="nav-item-dropdown">
            <a href="#" class="text-white text-decoration-none me-3" id="transparencyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Transparency <i class="bi bi-chevron-down" style="font-size: 0.7rem;" aria-hidden="true"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="transparencyDropdown">
              <li>
                <a class="dropdown-item text-white" href="<?= base_url('about') ?>">
                  <i class="bi bi-building me-2" aria-hidden="true"></i>About
                </a>
              </li>
              <li>
                <a class="dropdown-item text-white" href="#" data-bs-toggle="modal" data-bs-target="#citizensCharterModal">
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Citizens Charter
                </a>
              </li>
              <li>
                <a class="dropdown-item text-white" href="<?= base_url('programs') ?>">
                  <i class="bi bi-gear me-2" aria-hidden="true"></i>Programs and Projects
                </a>
              </li>
            </ul>
          </div>
          <a href="<?= base_url('logout') ?>" class="text-white text-decoration-none" title="Sign Out"><i class="bi bi-box-arrow-left me-1" aria-hidden="true"></i>Logout</a>
        <?php else: ?>
          <a href="<?= base_url() ?>" class="text-white text-decoration-none me-3<?= $currentSegment === 'home' ? ' active' : '' ?>" title="Home Page"><i class="bi bi-house me-1" aria-hidden="true"></i>Home</a>
          <div class="nav-item-dropdown">
            <a href="#" class="text-white text-decoration-none me-3" id="transparencyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-building me-1" aria-hidden="true"></i>Transparency <i class="bi bi-chevron-down" style="font-size: 0.7rem;" aria-hidden="true"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="transparencyDropdown">
              <li>
                <a class="dropdown-item text-white" href="<?= base_url('about') ?>">
                  <i class="bi bi-building me-2" aria-hidden="true"></i>About
                </a>
              </li>
              <li>
                <a class="dropdown-item text-white" href="#" data-bs-toggle="modal" data-bs-target="#citizensCharterModal">
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Citizens Charter
                </a>
              </li>
              <li>
                <a class="dropdown-item text-white" href="<?= base_url('programs') ?>">
                  <i class="bi bi-gear me-2" aria-hidden="true"></i>Programs and Projects
                </a>
              </li>
            </ul>
          </div>
          <a href="#" class="text-white text-decoration-none me-3" data-bs-toggle="modal" data-bs-target="#schoolMaterialsModal" role="button" title="School Materials"><i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Materials</a>
          <a href="<?= base_url('childpro') ?>" class="text-white text-decoration-none me-3<?= str_starts_with($currentSegment, 'childpro') ? ' active' : '' ?>" title="CHILDPRO Programs">
            <i class="bi bi-people me-1" aria-hidden="true"></i>CHILDPRO
          </a>
          <a href="<?= base_url('gad') ?>" class="text-white text-decoration-none me-3<?= str_starts_with($currentSegment, 'gad') ? ' active' : '' ?>" title="GAD Programs">
            <i class="bi bi-gender-ambiguous me-1" aria-hidden="true"></i>GAD
          </a>
          <a href="<?= base_url('login') ?>" class="text-white text-decoration-none me-3<?= $currentSegment === 'login' ? ' active' : '' ?>" title="Login"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Login</a>
          <?php
          try {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
            if ($registrationSetting === null) {
              $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1); // backward compatibility
            }
            $registrationEnabled = (bool) $registrationSetting;
          } catch (\Throwable $e) {
            $registrationEnabled = true;
          }
          ?>
          <?php if ($registrationEnabled): ?>
          <a href="<?= base_url('register') ?>" class="text-white text-decoration-none btn btn-accent<?= $currentSegment === 'register' ? ' active' : '' ?>" title="Create Account">Register</a>
          <?php else: ?>
          <a href="#" class="text-white text-decoration-none btn btn-secondary reg-closed-trigger" data-bs-toggle="modal" data-bs-target="#registrationClosedModal" role="button">Register</a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <?= $this->renderSection('content') ?>
  </main>

  <!-- Footer Divider -->
  <div style="height: 1px; background: rgba(255, 255, 255, 0.14); margin-top: 0;"></div>
  
  <footer class="site-footer" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); color: white; padding: 25px 0; box-shadow: 0 -4px 20px rgba(0,0,0,0.1);">

    <?php /* Tappy standing on the footer's top edge. Decorative and not focusable, so he
             never sits between a keyboard user and the links below. */ ?>
    <?php $peekArt = mascot_img(['name' => 'footer-peek', 'alt' => '', 'class' => 'mascot-footer-peek', 'loading' => 'lazy']); ?>
    <?php if ($peekArt !== ''): ?>
      <div class="mascot-footer-peek-wrap" aria-hidden="true"><?= $peekArt ?></div>
    <?php endif; ?>

    <div class="footer-container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
      <div class="footer-grid" style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 30px; align-items: center;">
        
        <!-- Left: Brand -->
        <div class="footer-brand" style="display: flex; align-items: center; gap: 15px;">
          <img src="<?= asset_url('LPHS2.png') ?>" alt="CSCS" style="width: 60px; height: 60px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);" />
          <div>
            <h4 style="margin: 0; font-weight: 700; color: #fbbf24;">CSCS Tap n Track</h4>
            <p style="margin: 0; font-size: 12px; color: white;">Modern Education Management</p>
          </div>
        </div>
        
        <!-- Center: Contact Grid -->
        <div class="footer-contact" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; font-size: 13px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-geo-alt" style="color: #fbbf24;"></i>
            <a href="https://www.google.com/maps/search/?api=1&query=WQJC%2BMM7%2C+Cauayan+City" target="_blank" style="color: white; text-decoration: none; opacity: 0.9;">Mabini Street, District I, Cauayan City, Isabela, Philippines</a>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-envelope" style="color: #fbbf24;"></i>
            <span style="opacity: 0.9;">Principal: <?= esc(school_principal_name()) ?></span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-clock" style="color: #10b981;"></i>
            <span style="opacity: 0.9;">24/7 System Access</span>
          </div>
        </div>
        
        <!-- Right: Social & Copyright -->
        <div class="footer-right" style="text-align: right;">
          <div style="margin-bottom: 15px;">
            <a href="https://www.facebook.com/cauayan.south.central" target="_blank" class="footer-facebook" style="display: inline-flex; align-items: center; justify-content: center; width: 50px; height: 50px; background: #1877f2; border-radius: 12px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(24,119,242,0.3);" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
              <i class="bi bi-facebook" style="font-size: 24px; color: white;"></i>
            </a>
          </div>
          <div class="footer-meta" style="font-size: 0.8125rem; opacity: 0.8; line-height: 1.4;">
            <div>&copy; 2026 CSCS Tap n Track</div>
            <div>Version 2.1.0</div>
          </div>
        </div>
        
      </div>
    </div>
  </footer>

  <?= view('partials/citizens_charter_modal') ?>
  <?= view('partials/public_materials_modal') ?>
  <?= view('partials/registration_closed_modal') ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php
    $locV = static function (string $file): string {
      $path = asset_file_path('js/' . $file);

      return $path !== null ? (string) filemtime($path) : '1';
    };
  ?>
  <script src="<?= asset_url('js/psgc-data.js') ?>?v=<?= $locV('psgc-data.js') ?>"></script>
  <script src="<?= asset_url('js/location-selector.js') ?>?v=<?= $locV('location-selector.js') ?>"></script>
  <script src="<?= asset_url('js/phone-input.js') ?>?v=<?= $locV('phone-input.js') ?>"></script>
  <script src="<?= asset_url('js/mascot.js') ?>?v=<?= $locV('mascot.js') ?>"></script>
  <script>
    window.gradeLevelLabels = <?= json_encode(grade_level_js_labels()) ?>;
    window.formatGradeLevel = function(level) {
      if (level === null || level === undefined || level === '') return 'N/A';
      const key = String(level);
      return window.gradeLevelLabels[key] ?? ('Grade ' + key);
    };
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var navToggle = document.getElementById('navToggle');
      var siteNav = document.getElementById('siteNav');
      if (navToggle && siteNav) {
        navToggle.addEventListener('click', function() {
          var isOpen = siteNav.classList.toggle('open');
          navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
      }
    });
  </script>

  <!-- Navigation Slide Down Effect -->
  <style>
    html { scroll-behavior: smooth; }
    .site-nav a:not(.btn):not([href*="dashboard"]):not([href*="logout"]):not([href*="announcements"]):not([href*="faq"]) {
      position: relative;
      transition: transform 0.3s ease;
    }
    .site-nav a:not(.btn):not([href*="dashboard"]):not([href*="logout"]):not([href*="announcements"]):not([href*="faq"]):hover {
      transform: translateY(3px);
    }
    .nav-item-dropdown {
      position: relative;
      display: inline-block;
    }
    .dropdown-menu {
      background: rgba(30, 58, 138, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      margin-top: 8px;
    }
    .dropdown-item {
      font-size: 0.9rem;
    }
    .nav-user-greeting {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 150px;
      display: inline-block;
      vertical-align: middle;
    }
  </style>

  <style>
    .site-footer {
      padding: 25px 0 18px;
    }

    .site-footer .footer-grid {
      display: grid;
      grid-template-columns: 1fr 2fr 1fr;
      gap: 30px;
      align-items: center;
    }

    .site-footer .footer-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
    }

    .site-footer .footer-brand h4 {
      margin: 0;
      font-size: 1.1rem;
      line-height: 1.2;
    }

    .site-footer .footer-brand p {
      margin: 0;
      font-size: 0.85rem;
      opacity: 0.95;
    }

    .site-footer .footer-contact {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
      font-size: 0.92rem;
    }

    .site-footer .footer-contact div {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }

    .site-footer .footer-contact a,
    .site-footer .footer-contact span {
      color: white;
      opacity: 0.9;
      font-size: 0.92rem;
      line-height: 1.35;
    }

    .site-footer .footer-right {
      text-align: right;
    }

    .site-footer .footer-right .footer-facebook {
      display: inline-flex;
      width: 44px;
      height: 44px;
      align-items: center;
      justify-content: center;
      border-radius: 12px;
    }

    .site-footer .footer-meta {
      font-size: 0.85rem;
      opacity: 0.8;
      line-height: 1.35;
    }

    @media (max-width: 768px) {
      .site-footer {
        padding: 18px 0 12px;
      }

      .site-footer .footer-container {
        padding: 0 14px;
      }

      .site-footer .footer-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
      }

      .site-footer .footer-brand {
        flex: 1 1 160px;
        min-width: 120px;
      }

      .site-footer .footer-contact {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        flex: 1 1 200px;
        min-width: 140px;
      }

      .site-footer .footer-contact div {
        flex: 1 1 120px;
        min-width: 120px;
      }

      .site-footer .footer-right {
        flex: 0 0 auto;
        text-align: right;
        min-width: 80px;
      }

      .site-footer .footer-right .footer-facebook {
        width: 36px;
        height: 36px;
      }

      .site-footer .footer-meta {
        font-size: 0.78rem;
      }
    }

    /* ===== Registration Closed Modal (branded) ===== */
    .reg-closed-trigger {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .reg-closed-modal__content {
      border: 0;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10), 0 24px 56px -8px rgba(15, 23, 42, 0.16);
    }

    .reg-closed-modal__header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px 20px;
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
      color: #fff;
    }

    .reg-closed-modal__logo {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
      background: #fff;
      object-fit: contain;
      flex-shrink: 0;
    }

    .reg-closed-modal__brand {
      display: flex;
      flex-direction: column;
      line-height: 1.25;
      min-width: 0;
    }

    .reg-closed-modal__brand-title {
      font-weight: 700;
      font-size: 1rem;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .reg-closed-modal__brand-subtitle {
      font-size: 0.78rem;
      color: rgba(255, 255, 255, 0.85);
      opacity: 0.85;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .reg-closed-modal__body {
      padding: 28px 28px 18px;
      text-align: center;
    }

    .reg-closed-modal__icon-wrap {
      width: 72px;
      height: 72px;
      margin: 0 auto 16px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
      box-shadow: 0 10px 24px rgba(245, 158, 11, 0.35);
    }

    .reg-closed-modal__icon-wrap i {
      font-size: 2rem;
      color: #fff;
    }

    .reg-closed-modal__title {
      font-family: var(--font-serif);
      font-weight: 700;
      font-size: 1.25rem;
      color: #1e3a8a;
      margin: 0 0 14px;
    }

    .reg-closed-modal__text {
      font-size: 0.92rem;
      line-height: 1.65;
      color: #334155;
      text-align: left;
      margin: 0 0 12px;
    }

    .reg-closed-modal__link {
      color: #1d4ed8;
      font-weight: 700;
      text-decoration: none;
      border-bottom: 1px solid rgba(29, 78, 216, 0.35);
    }

    .reg-closed-modal__link:hover {
      color: #1e40af;
      border-bottom-color: #1e40af;
    }

    .reg-closed-modal__note {
      display: flex;
      align-items: flex-start;
      gap: 4px;
      text-align: left;
      font-size: 0.85rem;
      line-height: 1.5;
      color: #1e3a8a;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 10px;
      padding: 10px 14px;
      margin-top: 16px;
    }

    .reg-closed-modal__footer {
      border-top: 1px solid #e2e8f0;
      background: #f8fafc;
      gap: 10px;
      padding: 14px 24px;
    }

    .reg-closed-modal__btn-secondary {
      background: transparent;
      color: #475569;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-weight: 700;
      padding: 8px 18px;
    }

    .reg-closed-modal__btn-secondary:hover {
      background: #e2e8f0;
      color: #334155;
    }

    @media (max-width: 576px) {
      .reg-closed-modal__body {
        padding: 22px 18px 14px;
      }

      .reg-closed-modal__text {
        font-size: 0.88rem;
      }

      .reg-closed-modal__brand-title {
        font-size: 0.9rem;
      }

      .reg-closed-modal__brand-subtitle {
        font-size: 0.8125rem;
      }

      .reg-closed-modal__footer .btn {
        width: 100%;
      }
    }
  </style>
  <?php
    // Tappy floats in front of the page, so this partial must be a direct child
    // of <body> — nested inside a container it would be clipped by that
    // container's overflow. The sign-in card and the About hero each show Tappy
    // at full size in a speech bubble of their own, so those two pages skip the
    // dock rather than stacking two of him on one screen.
    $mascotDockSkip = in_array($currentSegment, ['login', 'about'], true);
  ?>
<?php if (! $mascotDockSkip): ?>
  <?= view('partials/mascot_dock', ['dockContext' => 'public']) ?>
<?php endif; ?>

</body>
</html>

