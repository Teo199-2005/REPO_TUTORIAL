<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php
    $docTitle = (string) ($title ?? 'Dashboard - CSCS Tap n Track');
    $docParts = explode(' - ', $docTitle, 2);
    $docSub = trim($docParts[1] ?? '');
    if ($docSub === '' || ctype_digit($docSub)) {
      $docSub = 'CSCS Tap n Track';
    }
    $docTitle = $docParts[0] . ' - ' . $docSub;
  ?>
  <title><?= esc($docTitle) ?></title>
  <?= view('partials/site_head_meta', [
    'headMetaTitle' => $docTitle,
    'headMetaDescription' => $headMetaDescription ?? 'Cauayan South Central School — CSCS Tap n Track portal.',
  ]) ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- No webfont request: the platform renders in Times New Roman throughout, which
       every target OS ships, so nothing is downloaded and there is no flash of
       unstyled text. The family itself is declared in css/app.css. -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <?php
    $cssV = static function (string $file): string {
      $path = asset_file_path('css/' . $file);

      return $path !== null ? (string) filemtime($path) : '1';
    };
    $jsV = static function (string $file): string {
      $path = asset_file_path('js/' . $file);

      return $path !== null ? (string) filemtime($path) : '1';
    };
  ?>
  <link href="<?= asset_url('css/app.css') ?>?v=<?= $cssV('app.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/dashboard.css') ?>?v=<?= $cssV('dashboard.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/featured-poster.css') ?>?v=<?= $cssV('featured-poster.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/responsive.css') ?>?v=<?= $cssV('responsive.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/location-selector.css') ?>?v=<?= $cssV('location-selector.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/admin-table-enhancements.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/modal-system.css') ?>?v=<?= $cssV('modal-system.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/audit-log.css') ?>?v=<?= $cssV('audit-log.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/mascot.css') ?>?v=<?= $cssV('mascot.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/mascot-tour.css') ?>?v=<?= $cssV('mascot-tour.css') ?>" rel="stylesheet" />
<link href="<?= asset_url('css/mascot-welcome.css') ?>?v=<?= $cssV('mascot-welcome.css') ?>" rel="stylesheet" />
  <link href="<?= asset_url('css/admin-ui.css') ?>?v=<?= $cssV('admin-ui.css') ?>" rel="stylesheet" />
<link href="<?= asset_url('css/admin-landing-preview.css') ?>?v=<?= $cssV('admin-landing-preview.css') ?>" rel="stylesheet" />
  <!-- Surface-quality layer: glass panels, white/gray table stripes and
       colour-tinted button shadows. MUST stay last among the local
       stylesheets — it deliberately overrides several `!important` rules in
       dashboard.css (flat white cards, the near-invisible #f9fafb table
       stripes, and the single black box-shadow applied to every .btn). -->
  <link href="<?= asset_url('css/ui-depth.css') ?>?v=<?= $cssV('ui-depth.css') ?>" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet" />
</head>
<?php
  // The guided tour stores its once-only state per role, and mascot-tour.js
  // reads it from here. Same vocabulary as mascot_tour_played(), so the PHP
  // helper and the browser agree on the key. Anything that is not a student
  // (admins, teachers) shares the staff tour.
  $mascotRole = 'staff';
  try {
    if (auth()->loggedIn() && auth()->user()?->inGroup('student')) {
      $mascotRole = 'student';
    }
  } catch (\Throwable $e) {
    $mascotRole = 'staff';
  }
?>
<body
  class="dashboard-app"
  data-mascot-role="<?= esc($mascotRole) ?>"
  data-mascot-base="<?= esc(asset_url('assets/mascot/')) ?>"
  data-mascot-basepath="<?= esc(mascot_base_path()) ?>"
>
  <!-- Sidebar -->
  <div class="app-sidebar-wrapper">
    <aside class="app-sidebar">
      <?php
        helper('portal_nav');
        $authUser = null;
        try {
          if (auth()->loggedIn()) {
            $authUser = auth()->user();
          }
        } catch (\Throwable $e) {
          $authUser = null;
        }

        $portalNav = portal_nav_for_user($authUser);
        $role               = $portalNav['role'];
        $portalLabel        = $portalNav['portal_label'];
        $dashboardUrl       = $portalNav['dashboard_url'];
        $accountPanelHref   = $portalNav['account_panel_href'];
        $notificationsHref  = $portalNav['notifications_href'];
        $navSections        = $portalNav['sections'];

        // Students: recompute sidebar sections with the completion flags so
        // pages stay locked until BMI + profile photo + 2x2 ID photo exist.
        // A locked portal opens on My Profile, so the brand link follows the
        // locked items to that page and every locked link explains in its
        // tooltip exactly which requirements are still missing.
        $studentUnlockReason = '';
        if ($role === 'student' && $authUser !== null) {
          try {
            $portalNavStudent = (new \App\Models\StudentModel())->where('user_id', $authUser->id)->first();
          } catch (\Throwable $e) {
            $portalNavStudent = null;
          }
          $navSections = portal_nav_student_sections($portalNavStudent);

          if ($portalNavStudent !== null && ! student_profile_complete($portalNavStudent)) {
            $dashboardUrl = base_url('student/profile');
            $studentUnlockReason = student_profile_unlock_reason($portalNavStudent);
          }
        }
      ?>
      <!-- Sidebar Header -->
      <div class="app-sidebar-header">
        <a href="<?= $dashboardUrl ?>" class="d-flex align-items-center text-decoration-none app-brand-link">
          <?php
            // School seal in the white medallion styled by .app-brand-logo in
            // dashboard.css. school_logo_url() is the single place that knows
            // which logo file ships with the app, so the sidebar, the ID cards
            // and student profiles can never drift onto different images.
            //
            // If the logo file is ever missing, fall back to the DepEd seal and
            // then to nothing at all, so the header never shows a broken image.
            $sidebarLogo    = school_logo_url();
            $sidebarSeal    = asset_url('DepEd_Official_Seal.png');
            $sidebarTitle   = 'CSCS Tap n Track';
            $sidebarSubtext = (string) $portalLabel;
          ?>
          <span class="app-brand-logo">
            <img src="<?= esc($sidebarLogo) ?>"
                 alt="<?= esc($sidebarTitle . ' — ' . $sidebarSubtext) ?>"
                 width="54" height="54" decoding="async"
                 onerror="this.onerror=null; if (this.src !== <?= json_encode($sidebarSeal) ?>) { this.src = <?= json_encode($sidebarSeal) ?>; } else { this.closest('.app-brand-logo')?.remove(); }">
          </span>
          <div class="app-brand-text">
            <div class="app-brand-title"><?= esc($sidebarTitle) ?></div>
            <div class="app-brand-subtitle"><?= esc($sidebarSubtext) ?></div>
          </div>
        </a>
        <button class="app-sidebar-toggle d-lg-none" type="button" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </button>
      </div>
      
      <!-- User Greeting -->
      <?php if (auth()->loggedIn()): ?>
        <div class="user-greeting">
          <div class="greeting-text">
            <?php 
              $greeting = 'Welcome';
              
              $user = auth()->user();
              $userName = 'User';
              
              if (function_exists('user_is_any_admin') && user_is_any_admin($user)) {
                $userName = $user->inGroup('admin_staff') ? 'Admin staff' : 'Admin';
              } elseif ($user->inGroup('teacher')) {
                // Try to get teacher name from teachers table
                $db = \Config\Database::connect();
                $teacher = $db->table('teachers')
                  ->select('first_name, last_name')
                  ->where('user_id', $user->id)
                  ->get()
                  ->getRow();
                if ($teacher) {
                  $userName = $teacher->first_name . ' ' . $teacher->last_name;
                } else {
                  $userName = 'Teacher';
                }
              } elseif ($user->inGroup('student')) {
                // Try to get student name from students table
                $db = \Config\Database::connect();
                $student = $db->table('students')
                  ->select('first_name, last_name')
                  ->where('user_id', $user->id)
                  ->get()
                  ->getRow();
                if ($student) {
                  $userName = $student->first_name . ' ' . $student->last_name;
                } else {
                  $userName = 'Student';
                }
              }
            ?>
            <div class="greeting-main"><?= $greeting ?>!</div>
            <div class="greeting-name"><?= esc($userName) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Sidebar Navigation -->
      <nav class="app-sidebar-nav" aria-label="Portal navigation">
        <?php
          $currentPath = rtrim(parse_url(current_url(), PHP_URL_PATH) ?: '', '/');
          $activeItemPath = '';
          $activeMatchLen = -1;

          foreach ($navSections as $sectionScan) {
            foreach (($sectionScan['items'] ?? []) as $itemScan) {
              $scanPath = rtrim(parse_url($itemScan['href'] ?? '', PHP_URL_PATH) ?: '', '/');
              if ($scanPath === '') {
                continue;
              }
              $scanMatches = $currentPath === $scanPath || str_starts_with($currentPath . '/', $scanPath . '/');
              if (! $scanMatches) {
                continue;
              }

              $scanLen = strlen($scanPath);
              if ($scanLen > $activeMatchLen) {
                $activeMatchLen = $scanLen;
                $activeItemPath = $scanPath;
              }
            }
          }

          foreach ($navSections as $section):
        ?>
          <div class="app-sidebar-section-title"><?= esc($section['title']) ?></div>
          <ul class="app-sidebar-menu">
              <?php foreach ($section['items'] as $item):
              $itemPath = rtrim(parse_url($item['href'], PHP_URL_PATH) ?: '', '/');
              $isActive = $itemPath !== '' && $itemPath === $activeItemPath;
              $isLocked = ! empty($item['locked']);
            ?>
              <li>
                <?php if ($isLocked): ?>
                  <a href="<?= base_url('student/profile') ?>" class="app-sidebar-link app-sidebar-link-locked" data-label="<?= esc($item['label']) ?>" title="<?= esc($studentUnlockReason !== '' ? $studentUnlockReason : 'Complete your profile to unlock this page') ?>" style="opacity:.6;">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <span class="app-sidebar-link-text"><?= esc($item['label']) ?></span>
                  </a>
                <?php else: ?>
                <a href="<?= $item['href'] ?>" class="app-sidebar-link<?= $isActive ? ' active' : '' ?>" data-label="<?= esc($item['label']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                  <i class="bi <?= esc($item['icon']) ?>" aria-hidden="true"></i>
                  <span class="app-sidebar-link-text"><?= esc($item['label']) ?></span>
                  <?php if (! empty($item['badge'])): ?>
                    <span class="badge bg-danger app-sidebar-badge ms-auto flex-shrink-0" id="<?= esc($item['badge']) ?>" style="display: none;"></span>
                  <?php endif; ?>
                </a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>

        <div class="app-sidebar-divider"></div>
        <div class="app-sidebar-section-title">Session</div>
        <ul class="app-sidebar-menu app-sidebar-menu-account">
          <li>
            <a href="<?= base_url('logout') ?>" class="app-sidebar-link app-sidebar-link-logout" data-label="Logout">
              <i class="bi bi-box-arrow-left" aria-hidden="true"></i><span class="app-sidebar-link-text">Logout</span>
            </a>
          </li>
        </ul>
      </nav>
    </aside>
  </div>

  <!-- Sidebar Backdrop (Mobile Only) -->
  <div class="app-sidebar-backdrop d-lg-none"></div>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
          <button class="app-sidebar-toggle d-lg-none me-3" type="button" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <button class="app-desktop-collapse d-none d-lg-inline-flex btn btn-outline-secondary btn-sm me-3" type="button" aria-label="Collapse sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <?php
            $pageTitle = (string) ($title ?? 'Dashboard');
            $parts = explode(' - ', $pageTitle, 2);
            $subtitle = trim($parts[1] ?? '');
            // Ensure subtitle is CSCS Tap n Track when it looks like a number (e.g. badge/count mistaken for title)
            if ($subtitle === '' || ctype_digit($subtitle)) {
              $subtitle = 'CSCS Tap n Track';
            }
          ?>
          <div class="top-bar-title-wrap">
            <h1 class="mb-0 top-bar-title">
              <span class="title-main"><?= esc($parts[0]) ?></span><span class="title-sub"> <?= esc($subtitle) ?></span>
            </h1>
          </div>
        </div>
        <div class="top-bar-actions d-flex align-items-center gap-2">
          <?php
          // The permanent tour button that used to sit here is gone on purpose:
          // the tour now starts by itself on a role's first visit, and Tappy in
          // the floating dock is the way to replay it, so a permanent control in
          // the top bar was a button nobody needed.
          ?>
          <a
            href="<?= esc($notificationsHref ?? base_url('notifications')) ?>"
            class="top-bar-icon-btn top-bar-notification-bell"
            aria-label="Notifications"
            title="Notifications"
          >
            <i class="bi bi-bell"></i>
          </a>
          <a
            href="<?= esc($accountPanelHref ?? base_url('/')) ?>"
            class="top-bar-icon-btn top-bar-profile-btn"
            aria-label="Profile"
            title="Profile"
          >
            <i class="bi bi-person-circle"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Page Content -->
    <main class="page-content">
      <div class="dashboard-page-container">
        <?= $this->renderSection('content') ?>
      </div>
    </main>

    <footer class="dashboard-footer" role="contentinfo">
      <div class="dashboard-footer-bar" aria-hidden="true"></div>
      <div class="dashboard-footer-mobile">
        <div class="dashboard-footer-mobile-brand">
          <img src="<?= asset_url('LPHS2.png') ?>" alt="" width="28" height="28" />
          <span>CSCS Tap n Track</span>
        </div>
        <p class="dashboard-footer-mobile-meta">&copy; <?= date('Y') ?> · v2.1.0</p>
      </div>
      <div class="dashboard-footer-inner dashboard-footer-inner--desktop">
        <div class="dashboard-footer-grid">
          <div class="dashboard-footer-brand">
            <img src="<?= asset_url('LPHS2.png') ?>" alt="CSCS" />
            <div>
              <p class="dashboard-footer-brand-title">CSCS Tap n Track</p>
              <p class="dashboard-footer-brand-sub">Modern Education Management</p>
            </div>
          </div>
          <div class="dashboard-footer-contact">
            <a href="https://maps.google.com/?q=WQ8Q%2BJ5V%20Cauayan%20City" target="_blank" rel="noopener">
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              <span>Mabini Street, District I, Cauayan City, Isabela</span>
            </a>
            <span><i class="bi bi-clock" aria-hidden="true"></i> 24/7 System Access</span>
          </div>
          <div class="dashboard-footer-aside">
            <a href="https://www.facebook.com/cauayan.south.central" target="_blank" rel="noopener" class="dashboard-footer-social" aria-label="Facebook">
              <i class="bi bi-facebook" aria-hidden="true"></i>
            </a>
            <p class="dashboard-footer-copy">&copy; <?= date('Y') ?> CSCS Tap n Track<br>Version 2.1.0</p>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <?php
  // Full-viewport overlays (modals, etc.) must render here — outside .main-content — so they are
  // not capped by .main-content > * { z-index: 1 } and sit above the fixed sidebar / sticky top bar.
  ?>
  <?= $this->renderSection('portal_overlays') ?>

  <div id="dashboard-modal-portal" aria-hidden="true"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.gradeLevelLabels = <?= json_encode(grade_level_js_labels()) ?>;
    window.formatGradeLevel = function(level) {
      if (level === null || level === undefined || level === '') return 'N/A';
      const key = String(level);
      return window.gradeLevelLabels[key] ?? ('Grade ' + key);
    };
  </script>
  <script src="<?= asset_url('js/admin-filter-bar.js') ?>?v=<?= $jsV('admin-filter-bar.js') ?>"></script>
<script src="<?= asset_url('js/mascot.js') ?>?v=<?= $jsV('mascot.js') ?>"></script>
<script src="<?= asset_url('js/mascot-tour.js') ?>?v=<?= $jsV('mascot-tour.js') ?>"></script>
<script src="<?= asset_url('js/mascot-welcome.js') ?>?v=<?= $jsV('mascot-welcome.js') ?>"></script>
<script src="<?= asset_url('js/mobile-tables.js') ?>?v=<?= $jsV('mobile-tables.js') ?>"></script>
  <script src="<?= asset_url('js/admin-table-enhancements.js') ?>?v=<?= $jsV('admin-table-enhancements.js') ?>"></script>
  <script src="<?= asset_url('js/dashboard-modals.js') ?>?v=<?= $jsV('dashboard-modals.js') ?>"></script>
  <script src="<?= asset_url('js/modal-system.js') ?>?v=<?= $jsV('modal-system.js') ?>"></script>
  <script src="<?= asset_url('js/psgc-data.js') ?>?v=<?= $jsV('psgc-data.js') ?>"></script>
  <script src="<?= asset_url('js/location-selector.js') ?>?v=<?= $jsV('location-selector.js') ?>"></script>
  <script src="<?= asset_url('js/phone-input.js') ?>?v=<?= $jsV('phone-input.js') ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
  <script src="<?= asset_url('js/student-photo-crop.js') ?>?v=<?= $jsV('student-photo-crop.js') ?>"></script>
  <script>
    // Load notification counts
    <?php if (auth()->user()): ?>
    function updateBadge(id, count) {
      const badge = document.getElementById(id);
      if (badge) {
        if (count > 0) {
          badge.textContent = count;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      }
    }

    function loadNotificationCounts() {
      fetch('<?= base_url('api/notification-counts') ?>')
        .then(response => response.json())
        .then(data => {
          if (data.pending_applications !== undefined) updateBadge('pending-applications-count', data.pending_applications);
          if (data.password_resets !== undefined) updateBadge('password-resets-count', data.password_resets);
          if (data.announcements !== undefined) updateBadge('announcements-count', data.announcements);
          if (data.teacher_announcements !== undefined) updateBadge('teacher-announcements-count', data.teacher_announcements);
          if (data.student_announcements !== undefined) updateBadge('student-announcements-count', data.student_announcements);
          if (data.new_grades !== undefined) updateBadge('new-grades-count', data.new_grades);
          if (data.schedule_updates !== undefined) updateBadge('schedule-updates-count', data.schedule_updates);

          // Topbar bell: do not show count badge (avoids "1" appearing next to page title)
          // Sidebar items (Pending Applications, etc.) still show their counts.
        })
        .catch(error => console.error('Error loading notification counts:', error));
    }

    document.addEventListener('DOMContentLoaded', loadNotificationCounts);
    setInterval(loadNotificationCounts, 30000);
    <?php endif; ?>
  </script>
  <script>
    // Sidebar functionality (scoped to dashboard)
    document.addEventListener('DOMContentLoaded', function() {
      const sidebarWrapper = document.querySelector('.app-sidebar-wrapper');
      const mobileToggleButtons = document.querySelectorAll('.app-sidebar-toggle');
      const desktopCollapseBtn = document.querySelector('.app-desktop-collapse');
      const sidebarBackdrop = document.querySelector('.app-sidebar-backdrop');

      // Mobile sidebar toggle
      mobileToggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
          sidebarWrapper.classList.toggle('show');
          sidebarBackdrop.classList.toggle('show');
        });
      });

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth < 992) {
          if (!sidebarWrapper.contains(event.target) && !event.target.closest('.app-sidebar-toggle')) {
            sidebarWrapper.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
          }
        }
      });

      // Handle window resize
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
          sidebarWrapper.classList.remove('show');
          sidebarBackdrop.classList.remove('show');
        }
      });

      // Desktop collapse toggle
      if (desktopCollapseBtn) {
        desktopCollapseBtn.addEventListener('click', function() {
          sidebarWrapper.classList.toggle('collapsed');
        });
      }

      // Set active sidebar link based on current page
      const currentPath = window.location.pathname;
      document.querySelectorAll('.app-sidebar-link').forEach(link => {
        try {
          const linkPath = new URL(link.href, window.location.origin).pathname;
          if (linkPath === currentPath) {
            link.classList.add('active');
          }
        } catch (e) {
          // ignore URL parse errors
        }
      });
    });
  </script>

  <?php if (isset($role) && in_array($role, ['student', 'teacher'], true)): ?>
    <?= view('partials/platform_rating_logout_modal') ?>
  <?php endif; ?>

  <?= view('partials/mascot_dock', ['dockContext' => 'dashboard']) ?>

  <?php
  // The first-login welcome modal, rendered after the dock so it paints above it
  // (z-index 1090 vs 1050). It returns an empty string when an admin has switched
  // it off, so this line is safe to leave in place.
  ?>
  <?= view('partials/welcome_modal') ?>

</body>
</html>


