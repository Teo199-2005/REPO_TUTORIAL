<?php
/**
 * Shared password-requirement indicator.
 *
 * Renders the same list of rules under every password field so the wording is
 * identical on every screen, and every value comes from password_policy() so
 * the display can never drift from the server rule.
 *
 * @var string|null $id       Optional id so JavaScript can live-update it
 * @var string      $class    Extra classes for the wrapper
 * @var bool        $compact  Render as a single line instead of a list
 */
$passwordHintId = $id ?? null;
$passwordHintClass = trim('password-requirements' . ($class ?? ''));
$passwordHintInline = ! empty($compact);
// The policy is embedded so the JS checks the exact same numbers the server does.
$passwordPolicyJson = json_encode([
    'min_length' => password_policy_min_length(),
    'min_digits' => password_policy_min_digits(),
]);
?>
<div id="<?= esc($passwordHintId) ?>" class="<?= esc($passwordHintClass) ?>"
     data-password-policy="<?= esc($passwordPolicyJson) ?>"
     <?= $passwordHintInline ? 'data-compact="1"' : '' ?>>
  <span class="password-requirements-title">
    <i class="bi bi-shield-lock" aria-hidden="true"></i>
    Password requirements:
  </span>
  <ul class="password-requirements-list">
    <?php foreach (password_policy_requirements() as $requirement): ?>
      <li class="password-requirement">
        <i class="bi <?= esc($requirement['icon']) ?>" aria-hidden="true"></i>
        <span><?= esc($requirement['label']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
