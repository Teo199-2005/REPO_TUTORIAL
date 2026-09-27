<?php

/**
 * Standard admin page header.
 *
 * Icon tile, title, optional subtitle and right-aligned actions. Every admin
 * page uses this so the top of each screen looks the same.
 *
 * @var array $pageHeader
 *   icon     string  Bootstrap Icons class without the "bi " prefix
 *   title    string  page title
 *   subtitle string  one-line explanation (optional)
 *   actions  string  pre-rendered HTML for the action buttons (optional)
 */

$pageHeader = $pageHeader ?? [];

$headerIcon     = (string) ($pageHeader['icon'] ?? 'bi-layout-text-window-reverse');
$headerTitle    = (string) ($pageHeader['title'] ?? 'Admin');
$headerSubtitle = trim((string) ($pageHeader['subtitle'] ?? ''));
$headerActions  = (string) ($pageHeader['actions'] ?? '');
?>
<div class="admin-page-header">
  <div class="admin-page-header__lead">
    <span class="admin-page-header__icon" aria-hidden="true">
      <i class="bi <?= esc($headerIcon) ?>"></i>
    </span>
    <div>
      <h1 class="admin-page-header__title"><?= esc($headerTitle) ?></h1>
      <?php if ($headerSubtitle !== ''): ?>
        <p class="admin-page-header__subtitle"><?= esc($headerSubtitle) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($headerActions !== ''): ?>
    <div class="admin-page-header__actions">
      <?= $headerActions // pre-rendered by the calling view ?>
    </div>
  <?php endif; ?>
</div>
<div class="blue-divider"></div>
