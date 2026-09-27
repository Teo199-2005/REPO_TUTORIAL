<?php

/**
 * Tappy's floating dock.
 *
 * The mascot floats in front of the page rather than sitting inside a card, so
 * this partial is rendered as a direct child of <body> by both layouts — a
 * mascot nested inside a dashboard container would be clipped by that
 * container's overflow and trapped in its stacking context, which is exactly
 * what stopped it floating reliably before.
 *
 * Tappy is a guide, not a chatbot: there is no input box, because there is
 * nothing to type. What he does have is a line about the page he is standing on
 * (see mascot_line_for), and he is the way back into the guided tour now that
 * the "Take a tour" button has been removed from the top bar. That is why the
 * artwork is wrapped in a real <button> rather than left as decoration.
 *
 * Two deliberate degradations:
 *   - The dock ships with the `hidden` attribute and only appears once
 *     mascot.js has confirmed the artwork actually loaded. A missing PNG must
 *     never leave an empty speech bubble floating over the page.
 *   - Without JavaScript the dock stays hidden. A bubble that cannot be
 *     dismissed is worse than no mascot at all.
 *
 * @var string $dockContext 'dashboard' or 'public'
 */
$dockContext = ($dockContext ?? '') === 'public' ? 'public' : 'dashboard';

$segment = mascot_segment_key();

if ($dockContext === 'public') {
    // Public pages get a real per-page line, not one generic greeting. CHILDPRO,
    // GAD, registration and the public pages are all reachable by visitors who
    // have never signed in, so the wording explains the page instead of pointing
    // at a portal they may not have access to. An administrator can override the
    // CHILDPRO and GAD wording from admin/childpro-gad; mascot_line_for() applies
    // that before falling back to the built-in line below.
    //
    // The home page is the root URL, which has no first path segment at all, so
    // mascot_segment_key() returns an empty string. It is normalised to "home"
    // here; without that, the front page would fall through to the generic
    // "Ask me anything about this page" instead of its own line.
    $lineKey = $segment !== '' ? $segment : 'home';
    $line    = mascot_line_for($lineKey);
    $dockCopy = [
        'key'   => $lineKey,
        'pose'  => $line['pose'],
        'title' => $line['title'],
        'text'  => $line['text'],
    ];
} else {
    // A different pose and a different line on every page, rather than the same
    // silent pointing hand everywhere.
    $line     = mascot_line_for($segment);
    $dockCopy = [
        'key'   => $segment !== '' ? $segment : 'dashboard',
        'pose'  => $line['pose'],
        'title' => $line['title'],
        'text'  => $line['text'],
    ];
}

$panelId = 'mascot-dock-panel-' . preg_replace('/[^a-z0-9]+/i', '-', $dockCopy['key'] ?? 'default');

// 'var(--mascot-dock)' rather than a pixel value, so this placement can shrink on
// a phone - an inline px would beat the responsive media query. 'eager' because
// the dock is above the fold by definition; the greeting is worthless if it
// arrives after the visitor has already read the page.
$dockArt = mascot_img([
    'name'    => $dockCopy['pose'],
    'size'    => 'var(--mascot-dock)',
    'class'   => 'mascot-dock__art',
    'loading' => 'eager',
]);
// Whether this person has already sent Tappy away. Read from the account, not
// from the browser: the localStorage copy in mascot.js is only a mirror, so the
// choice follows the account across a logout and a fresh login instead of
// evaporating with the session.
$mascotHidden    = mascot_user_hidden();
$mascotVisUrl    = mascot_user_visibility_url();

?>
<div
  class="mascot-dock<?= $mascotHidden ? ' mascot-dock--tucked' : '' ?>"
  data-mascot-dock
  data-dock-key="<?= esc($dockCopy['key']) ?>"
  data-dock-pose="<?= esc($dockCopy['pose']) ?>"
  data-mascot-hidden="<?= $mascotHidden ? '1' : '0' ?>"
  data-mascot-user="<?= esc(mascot_user_scope_key()) ?>"
  data-mascot-visibility-url="<?= esc($mascotVisUrl) ?>"
  data-mascot-csrf-name="<?= esc(csrf_token()) ?>"
  data-mascot-csrf-hash="<?= esc(csrf_hash()) ?>"
  <?php if ($mascotHidden): ?>hidden<?php endif; ?>
>
  <div class="mascot-dock__panel" id="<?= esc($panelId) ?>" role="status" aria-live="polite" hidden>
    <button
      type="button"
      class="mascot-dock__close"
      data-mascot-hide
      aria-label="Hide Tappy"
      title="Hide Tappy"
    >&times;</button>
    <p class="mascot-dock__title" data-mascot-title><?= esc($dockCopy['title']) ?></p>
    <p class="mascot-dock__text" data-mascot-text data-mascot-type><?= esc($dockCopy['text']) ?></p>
    <?php if ($dockContext === 'dashboard'): ?>
      <div class="mascot-dock__actions">
        <button type="button" class="mascot-dock__action" data-mascot-tour>
          Show me around
        </button>
        <button type="button" class="mascot-dock__action mascot-dock__action--quiet" data-mascot-dismiss>
          Not now
        </button>
      </div>
    <?php endif; ?>
  </div>
  <button type="button" class="mascot-dock__hint" data-mascot-hint hidden>Need a hand?</button>
  <button
    type="button"
    class="mascot-dock__trigger"
    data-mascot-trigger
    aria-expanded="false"
    aria-controls="<?= esc($panelId) ?>"
    aria-label="<?= esc(mascot_name() . ' — ask for help') ?>"
  >
    <?= $dockArt ?>
  </button>
</div>

<?php
  // The way back.
  //
  // This has to be a SIBLING of .mascot-dock, not a child of it: when Tappy is
  // hidden the whole dock carries the `hidden` attribute, so a button inside it
  // would be hidden along with him and there would be no way to bring him back.
  //
  // Rendered visible only when the account has actually hidden him, so a page
  // where Tappy is showing never grows a stray "Tappy" pill in the corner.
  ?>
<button
  type="button"
  class="mascot-restore"
  data-mascot-restore
  aria-label="Bring <?= esc(mascot_name()) ?> back"
  title="Bring <?= esc(mascot_name()) ?> back"
  <?= $mascotHidden ? '' : 'hidden' ?>
>
  <?php /* The icon-scale mark, not a character pose. This renders at 32px, and a
             full 3D head turns to mush at that size where flat vector stays
             legible. Falls back to the bust when the mark is not installed. */ ?>
  <?= mascot_logo_for('mark', ['size' => 32, 'loading' => 'lazy', 'alt' => '']) ?: mascot_img(['name' => 'bust', 'size' => 32, 'loading' => 'lazy', 'alt' => '']) ?>
  <span class="mascot-restore__label"><?= esc(mascot_name()) ?></span>
</button>
