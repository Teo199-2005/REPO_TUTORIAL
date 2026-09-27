<?php

/**
 * Header art for the announcements pages.
 *
 * Shared by the four announcements list views so the megaphone and the
 * poster live in one file: they are a matched pair, and duplicating the markup
 * four times is how the two drift apart.
 *
 * Both parts are stacked rather than side by side, because poster-announce is a
 * full-frame illustration with no clear area to put a heading in.
 *
 * Both return nothing when the artwork is absent, so the pages are unchanged
 * without it.
 */

$announcePoster = mascot_poster_banner('announce', ['alt' => '']);
$announceMascot = mascot_img([
    'name'    => 'megaphone',
    'alt'     => '',
    'class'   => 'mascot-announce-chip',
    'loading' => 'lazy',
]);
?>
<?php if ($announcePoster !== '' || $announceMascot !== ''): ?>
  <div class="mascot-announce-head">
    <?= $announcePoster ?>
    <?php if ($announceMascot !== ''): ?>
      <div class="mascot-announce-head__chip" aria-hidden="true"><?= $announceMascot ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
