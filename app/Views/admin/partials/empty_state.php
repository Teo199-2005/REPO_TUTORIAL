<?php

/**
 * Standard "nothing to show" block.
 *
 * One shape for every empty list: icon, a short sentence, a hint about what to
 * do next and an optional action.
 *
 * @var array $emptyState
 *   icon     string  Bootstrap Icons class without the "bi " prefix
 *   title    string  short sentence, e.g. "No students found"
 *   hint     string  what to do next (optional)
 *   action   string  pre-rendered HTML for a button/link (optional)
 *   colspan  int     columns to span (default 1)
 */

$emptyState = $emptyState ?? [];

$emptyIcon     = (string) ($emptyState['icon'] ?? 'bi-inbox');
$emptyTitle    = (string) ($emptyState['title'] ?? 'Nothing to show');
$emptyHint     = trim((string) ($emptyState['hint'] ?? ''));
$emptyAction   = (string) ($emptyState['action'] ?? '');
$emptyColspan  = max(1, (int) ($emptyState['colspan'] ?? 1));
?>
<div class="admin-empty">
  <?php
    // Tappy appears in the shared empty state, so every admin list that runs
    // out of results gets him automatically. The pose differs by reason: a
    // filter that found nothing is a "search" moment, an empty list is not.
    $emptyTitleLc = strtolower($emptyTitle);
    $emptyReason  = $emptyTitleLc . ' ' . strtolower($emptyHint);

    $emptyIsFilterMiss = str_contains($emptyTitleLc, 'match')
        || str_contains($emptyTitleLc, 'no result')
        || str_contains($emptyTitleLc, 'found these filters');

    // "Nothing left to do" is a different feeling from "nothing here yet", and
    // the sticker is what tells them apart at a glance: a green tick says the
    // work is finished, a lightbulb says here is how to start.
    $emptyIsAllDone = str_contains($emptyReason, 'caught up')
        || str_contains($emptyReason, 'no pending')
        || str_contains($emptyReason, 'nothing pending')
        || str_contains($emptyReason, 'all done')
        || str_contains($emptyReason, 'up to date');

    $emptyPose    = $emptyIsFilterMiss ? 'search' : 'sleeping';
    $emptySticker = $emptyIsAllDone ? 'done' : 'tip';
  ?>
  <div class="mascot-empty">
    <?= mascot_img(['name' => $emptyPose, 'alt' => 'Tappy', 'size' => 132]) ?>
  </div>
  <?php if ($emptyIcon !== ''): ?>
    <i class="bi <?= esc($emptyIcon) ?> admin-empty__icon" aria-hidden="true"></i>
  <?php endif; ?>
  <p class="admin-empty__title">
    <?= mascot_sticker_for($emptySticker, ['class' => 'mascot-chip mascot-chip--sm']) ?>
    <?= esc($emptyTitle) ?>
  </p>
  <?php if ($emptyHint !== ''): ?>
    <p class="admin-empty__hint"><?= esc($emptyHint) ?></p>
  <?php endif; ?>
  <?php if ($emptyAction !== ''): ?>
    <div class="mt-3"><?= $emptyAction // pre-rendered by the calling view ?></div>
  <?php endif; ?>
</div>
