<?php

/**
 * Standard admin filter bar.
 *
 * Renders a consistent filter row across every admin list page:
 *   - up to 4 PRIMARY filters always visible;
 *   - every remaining filter hidden behind a "More Filters" toggle that
 *     carries a count of the advanced filters currently narrowing the list;
 *   - no Apply button — admin-filter-bar.js submits on change (debounced for
 *     text inputs);
 *   - a Reset button, shown only while at least one filter is active.
 *
 * Contract
 * --------
 *   $action      string  URL the form posts to (defaults to the current URL)
 *   $primary     list    filters shown without expanding  (max 4)
 *   $advanced    list    filters behind the toggle
 *   $activeCount int     how many advanced filters are set
 *   $totalCount  int     how many filters of any kind are set
 *   $resetUrl    string  "clear everything" link
 *   $id          string  unique id, used for the collapse target
 *   $label       string  accessible name for the form
 *
 * Each filter is an array:
 *   name      string  input name (also the query-string key)
 *   label     string  visible label
 *   icon      string  Bootstrap Icons class, without the "bi " prefix
 *   type      string  'select' (default) | 'search' | 'text' | 'date'
 *   value     string  currently selected/current value
 *   options   list    [ ['value' => 'x', 'label' => 'X'], ... ]  (selects)
 *   placeholder string (search / text)
 *
 * @var array $filterBar
 */

$filterBar = $filterBar ?? [];

$action      = (string) ($filterBar['action'] ?? current_url());
$primary     = array_slice((array) ($filterBar['primary'] ?? []), 0, 4);
$advanced    = (array) ($filterBar['advanced'] ?? []);
$activeCount = (int) ($filterBar['activeCount'] ?? 0);
$totalCount  = (int) ($filterBar['totalCount'] ?? 0);
$resetUrl    = (string) ($filterBar['resetUrl'] ?? current_url());
$barId       = (string) ($filterBar['id'] ?? 'adminFilterPanel');
$formLabel   = (string) ($filterBar['label'] ?? 'Filter this list');

// A toggle is pointless when everything already fits on screen.
$hasAdvanced = $advanced !== [];
$panelId     = $barId . 'More';

/**
 * Render one filter control.
 *
 * Kept as a closure rather than a partial so the field definition array stays
 * the single description of a filter.
 */
$renderField = static function (array $field): void {
    $name  = (string) ($field['name'] ?? '');
    $type  = strtolower((string) ($field['type'] ?? 'select'));
    $icon  = (string) ($field['icon'] ?? 'bi-funnel');
    $value = (string) ($field['value'] ?? '');
    $label = (string) ($field['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $name)));

    // The field id must be unique per form; the name is unique inside a form,
    // which is all that matters for <label for> here.
    $id = 'flt-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $name);
    ?>
    <div class="col-md-3">
      <label class="form-label" for="<?= esc($id) ?>">
        <i class="bi <?= esc($icon) ?> me-1" aria-hidden="true"></i><?= esc($label) ?>
      </label>
      <?php if ($type === 'search' || $type === 'text' || $type === 'date'): ?>
        <input type="<?= $type === 'search' ? 'search' : ($type === 'date' ? 'date' : 'text') ?>"
               id="<?= esc($id) ?>"
               name="<?= esc($name) ?>"
               class="form-control"
               value="<?= esc($value) ?>"
               <?= ! empty($field['placeholder']) ? 'placeholder="' . esc((string) $field['placeholder']) . '"' : '' ?>
               autocomplete="off">
      <?php else: ?>
        <?php
          // A field may supply either a flat `options` list or `groups`
          // ([ ['label' => 'Group', 'options' => [...]], ... ]) rendered as
          // <optgroup>, which is how the activity log keeps 60 action codes
          // navigable.
          $groups    = (array) ($field['groups'] ?? []);
          $flatItems = $groups === [] ? (array) ($field['options'] ?? []) : [];

          $renderOption = static function (array $option, string $selected) {
              $optValue = (string) ($option['value'] ?? $option[0] ?? '');
              $optLabel = (string) ($option['label'] ?? $option[1] ?? $optValue);

              echo '<option value="' . esc($optValue) . '"'
                  . ($selected === $optValue ? ' selected' : '') . '>'
                  . esc($optLabel) . '</option>';
          };
        ?>
        <select id="<?= esc($id) ?>" name="<?= esc($name) ?>" class="form-select">
          <?php if ($groups !== []): ?>
            <?php foreach ($groups as $group): ?>
              <?php $groupItems = (array) ($group['options'] ?? []); ?>
              <?php if ($groupItems === []) { continue; } ?>
              <optgroup label="<?= esc((string) ($group['label'] ?? '')) ?>">
                <?php foreach ($groupItems as $option) { $renderOption((array) $option, $value); } ?>
              </optgroup>
            <?php endforeach; ?>
          <?php else: ?>
            <?php foreach ($flatItems as $option) { $renderOption((array) $option, $value); } ?>
          <?php endif; ?>
        </select>
      <?php endif; ?>
    </div>
    <?php
};
?>
<form class="admin-filter-form admin-filter-bar" method="get" action="<?= esc($action) ?>" aria-label="<?= esc($formLabel) ?>">
  <div class="row g-2">
    <?php foreach ($primary as $field): ?>
      <?php $renderField((array) $field); ?>
    <?php endforeach; ?>

    <?php if ($hasAdvanced): ?>
      <div class="col-md-3 admin-filter-bar__more">
        <button class="btn btn-outline-secondary w-100" type="button"
                data-admin-filter-toggle data-admin-filter-target="#<?= esc($panelId) ?>"
                data-bs-toggle="collapse" data-bs-target="#<?= esc($panelId) ?>"
                aria-expanded="<?= $activeCount > 0 ? 'true' : 'false' ?>"
                aria-controls="<?= esc($panelId) ?>">
          <i class="bi bi-sliders me-1" aria-hidden="true"></i>More filters
          <?php if ($activeCount > 0): ?>
            <span class="badge bg-primary ms-1"><?= (int) $activeCount ?></span>
          <?php endif; ?>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($totalCount > 0): ?>
      <div class="col-md-3">
        <a class="btn btn-outline-secondary w-100" href="<?= esc($resetUrl) ?>" title="Clear every filter">
          <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reset
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($hasAdvanced): ?>
    <div class="collapse <?= $activeCount > 0 ? 'show' : '' ?>" id="<?= esc($panelId) ?>">
      <div class="row g-2 admin-filter-bar__advanced">
        <?php foreach ($advanced as $field): ?>
          <?php $renderField((array) $field); ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</form>
