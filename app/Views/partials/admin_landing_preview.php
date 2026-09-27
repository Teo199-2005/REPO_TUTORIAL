<?php
helper(['landing', 'asset']);
$previewSlides = $previewSlides ?? landing_hero_slides_for_view();
$stripText     = $stripText ?? landing_announcement_strip_text();
$showStrip     = trim((string) $stripText) !== '';
$slideCount    = count($previewSlides);
$previewId     = $previewId ?? 'adminLandingPreview';
$lifelines     = landing_lifelines();
?>
<link href="<?= asset_url('css/admin-landing-preview.css') ?>" rel="stylesheet">

<div id="<?= esc($previewId) ?>" class="admin-landing-preview" data-slide-count="<?= (int) $slideCount ?>">
    <section class="hero hero-slideshow" aria-label="Landing page preview">
        <div class="landing-announcement-strip<?= $showStrip ? '' : ' is-hidden' ?>" id="<?= esc($previewId) ?>Strip" aria-hidden="<?= $showStrip ? 'false' : 'true' ?>">
            <div class="landing-announcement-strip__viewport">
                <div class="landing-announcement-strip__track">
                    <?php for ($copy = 0; $copy < 2; $copy++): ?>
                        <div class="landing-announcement-strip__group"<?= $copy === 1 ? ' aria-hidden="true"' : '' ?>>
                            <?php for ($repeat = 0; $repeat < 4; $repeat++): ?>
                                <span class="landing-announcement-strip__item">
                                    <i class="bi bi-megaphone-fill landing-announcement-strip__icon" aria-hidden="true"></i>
                                    <span class="landing-announcement-strip__text" data-strip-text><?= esc($stripText) ?></span>
                                </span>
                                <span class="landing-announcement-strip__sep" aria-hidden="true">◆</span>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="hero-slideshow__stage" id="<?= esc($previewId) ?>Stage">
            <?php if ($slideCount === 0): ?>
                <div class="admin-landing-preview__empty">No hero images — default banner will be used on the home page.</div>
            <?php else: ?>
                <?php foreach ($previewSlides as $index => $slide): ?>
                    <div class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>" data-slide-index="<?= (int) $index ?>">
                        <img
                            class="hero-banner-img"
                            src="<?= esc($slide['url']) ?>"
                            alt="<?= esc($slide['alt']) ?>"
                            width="1983"
                            height="793"
                            decoding="async"
                            style="object-position: <?= esc($slide['position'] ?? '50% 50%') ?>; transform: scale(<?= esc(number_format((float) ($slide['scale'] ?? 1), 2, '.', '')) ?>);"
                        >
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($slideCount > 1): ?>
            <!-- dot controls removed in preview; use arrow nav -->
        <?php endif; ?>
        <!-- Lifelines preview (Water / Communication / Electricity) -->
        <div class="hero-lifelines" role="region" aria-label="School lifelines status">
            <div class="lifeline-card">
                <div class="lifeline-icon"><i class="bi bi-droplet-fill" aria-hidden="true"></i></div>
                <div class="lifeline-body">
                    <div class="lifeline-title">Water</div>
                    <div class="lifeline-status"><?= esc($lifelines['water'] ?? 'FUNCTIONAL') ?></div>
                </div>
            </div>
            <div class="lifeline-card">
                <div class="lifeline-icon"><i class="bi bi-telephone-fill" aria-hidden="true"></i></div>
                <div class="lifeline-body">
                    <div class="lifeline-title">Communication</div>
                    <div class="lifeline-status"><?= esc($lifelines['communication'] ?? 'FUNCTIONAL') ?></div>
                </div>
            </div>
            <div class="lifeline-card">
                <div class="lifeline-icon"><i class="bi bi-lightbulb-fill" aria-hidden="true"></i></div>
                <div class="lifeline-body">
                    <div class="lifeline-title">Electricity</div>
                    <div class="lifeline-status"><?= esc($lifelines['electricity'] ?? 'FUNCTIONAL') ?></div>
                </div>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="<?= esc($previewId) ?>Data"><?= json_encode([
    'defaultBanner' => hero_banner_url(),
    'slides'        => array_values(array_map(static fn (array $s): array => [
        'url' => $s['url'],
        'alt' => $s['alt'],
    ], $previewSlides)),
    'strip'         => $stripText,
    'lifelines'     => $lifelines,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
    <script>
        (function () {
            var root = document.getElementById('<?= esc($previewId) ?>');
            if (!root) return;
            var hero = root.querySelector('.hero-slideshow');
            if (!hero) return;
            var slides = hero.querySelectorAll('.hero-slide');
            if (!slides.length) return;
            var current = 0;
            function goTo(idx) {
                current = (idx + slides.length) % slides.length;
                slides.forEach(function (s, i) { s.classList.toggle('is-active', i === current); });
            }
            var prev = hero.querySelector('.hero-prev');
            var next = hero.querySelector('.hero-next');
            if (prev) prev.addEventListener('click', function () { goTo(current - 1); });
            if (next) next.addEventListener('click', function () { goTo(current + 1); });
        })();

        (function () {
            var root = document.getElementById('<?= esc($previewId) ?>');
            if (!root) return;
            function updateStripDuration(strip) {
                if (!strip) return;
                var track = strip.querySelector('.landing-announcement-strip__track');
                if (!track) return;
                var distance = track.offsetWidth / 2;
                var speed = 70; // pixels per second
                var duration = Math.max(16, Math.min(60, Math.round(distance / speed)));
                track.style.setProperty('--admin-landing-strip-duration', duration + 's');
            }

            var strip = root.querySelector('.landing-announcement-strip');
            updateStripDuration(strip);
            window.addEventListener('resize', function () { updateStripDuration(strip); });
        })();
    </script>
