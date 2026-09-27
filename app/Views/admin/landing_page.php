<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="h3 mb-2">Landing Page</h1>
    <p class="text-muted mb-0">Manage the public home page hero slideshow (up to 3 images), the announcement strip above the hero, and Tappy's own message on the front page. Everything here is what a visitor sees at <?= esc(base_url()) ?>.</p>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="post" action="<?= base_url('admin/landing-page/update') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-images me-2"></i>Hero slideshow</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-muted">Upload up to <strong>3</strong> wide banner images which will be shown in addition to the primary school banner (primary + up to 3 uploads = up to 4 slides). Recommended aspect ratio about <strong>2.5:1</strong> (e.g. 1983×793 px). If none are uploaded, the default school banner is used.</p>
            <p class="text-muted small">Server limits: upload_max_filesize = <strong><?= esc(ini_get('upload_max_filesize')) ?></strong>, post_max_size = <strong><?= esc(ini_get('post_max_size')) ?></strong></p>

            <div class="row g-4">
                <?php for ($slot = 1; $slot <= 3; $slot++): ?>
                    <?php $slide = $slides[$slot] ?? null; ?>
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100">
                            <h6 class="fw-bold text-primary mb-3">Slide <?= $slot ?></h6>
                            <div class="slide-editor__preview-wrapper mb-3 rounded-3 overflow-hidden border bg-light position-relative" data-slot="<?= $slot ?>" tabindex="0" aria-label="Slide <?= $slot ?> preview">
                                <?php if (! empty($slide['url'])): ?>
                                    <img src="<?= esc($slide['url']) ?>" alt="Hero slide <?= $slot ?>" class="img-fluid w-100 slide-preview-img" data-slot="<?= $slot ?>" style="aspect-ratio: 2.5/1; object-fit: cover; object-position: <?= esc($slide['position'] ?? '50% 50%') ?>; transform: scale(<?= esc(number_format((float)($slide['scale'] ?? 1), 2, '.', '')) ?>);">
                                    <div class="slide-editor__preview-hint">Drag image to reposition</div>
                                <?php else: ?>
                                    <div class="slide-editor__placeholder d-flex align-items-center justify-content-center p-3 text-center small text-muted">No image yet. Upload a slide to preview and drag it into place.</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="remove_slide_<?= $slot ?>" id="remove_slide_<?= $slot ?>" value="0">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php if (! empty($slide['url'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary open-slide-editor" data-slot="<?= $slot ?>" title="Open the crop editor">
                                        <i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i>
                                        <span class="d-none d-sm-inline">Edit crop</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-slide-btn" data-slot="<?= $slot ?>" title="Remove this slide">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>
                                        <span class="d-none d-sm-inline">Remove</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <label class="form-label small fw-semibold" for="hero_slide_<?= $slot ?>">Replace or add image</label>
                            <input type="file" class="form-control form-control-sm hero-slide-input" id="hero_slide_<?= $slot ?>" name="hero_slide_<?= $slot ?>" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                            <div class="form-text small text-muted mt-1">
                                <span class="slide-input-hint">JPG, PNG, or WEBP — max 50MB. Choosing a file opens the crop editor automatically.</span>
                                <span class="text-danger d-none slide-input-error" role="alert"></span>
                            </div>

                            <div class="slide-editor__controls mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label small fw-semibold mb-0" for="hero_slide_<?= $slot ?>_zoom">Zoom</label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary reset-slide-edit" data-slot="<?= $slot ?>">Reset</button>
                                </div>
                                <input type="range" class="form-range slide-zoom-range" id="hero_slide_<?= $slot ?>_zoom" data-slot="<?= $slot ?>" min="1" max="2.5" step="0.05" value="<?= esc(number_format((float)($slide['scale'] ?? 1), 2, '.', '')) ?>" <?= empty($slide['url']) ? 'disabled' : '' ?>>
                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <span class="slide-position-label" id="hero_slide_<?= $slot ?>_position_label">Position: <?= esc($slide['position'] ?? '50% 50%') ?></span>
                                    <span class="slide-zoom-label" id="hero_slide_<?= $slot ?>_zoom_label"><?= esc(number_format((float)($slide['scale'] ?? 1), 2, '.', '')) ?>×</span>
                                </div>
                            </div>

                            <input type="hidden" name="hero_slide_<?= $slot ?>_position" id="hero_slide_<?= $slot ?>_position" value="<?= esc($slide['position'] ?? '50% 50%') ?>">
                            <input type="hidden" name="hero_slide_<?= $slot ?>_scale" id="hero_slide_<?= $slot ?>_scale" value="<?= esc(number_format((float)($slide['scale'] ?? 1), 2, '.', '')) ?>">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Announcement strip</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-muted">This message scrolls in a loop above the hero images on the public home page (e.g. principal advisories). Leave empty to hide the strip.</p>
            <label class="form-label fw-semibold" for="announcement_strip">Announcement text</label>
            <textarea
                class="form-control"
                id="announcement_strip"
                name="announcement_strip"
                rows="3"
                maxlength="500"
                placeholder="Example: Welcome to CSCS! Enrollment for SY 2025–2026 is now open. Contact the registrar for assistance."
            ><?= esc($announcementStrip ?? '') ?></textarea>
            <small class="text-muted d-block mt-2">Maximum 500 characters. Keep wording concise for smooth scrolling.</small>
        </div>
    </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>Lifelines</h5>
    </div>
    <div class="card-body p-4">
      <p class="text-muted">Set the current status of essential services shown on the public home page.</p>
      <?php $lifelines = landing_lifelines(); // helper returns defaults if missing ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-semibold" for="lifeline_water">Water</label>
          <select id="lifeline_water" name="lifeline_water" class="form-select form-select-sm">
            <option value="FUNCTIONAL"<?= $lifelines['water'] === 'FUNCTIONAL' ? ' selected' : '' ?>>FUNCTIONAL</option>
            <option value="NOT FUNCTIONAL"<?= $lifelines['water'] === 'NOT FUNCTIONAL' ? ' selected' : '' ?>>NOT FUNCTIONAL</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold" for="lifeline_communication">Communication</label>
          <select id="lifeline_communication" name="lifeline_communication" class="form-select form-select-sm">
            <option value="FUNCTIONAL"<?= $lifelines['communication'] === 'FUNCTIONAL' ? ' selected' : '' ?>>FUNCTIONAL</option>
            <option value="NOT FUNCTIONAL"<?= $lifelines['communication'] === 'NOT FUNCTIONAL' ? ' selected' : '' ?>>NOT FUNCTIONAL</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold" for="lifeline_electricity">Electricity</label>
          <select id="lifeline_electricity" name="lifeline_electricity" class="form-select form-select-sm">
            <option value="FUNCTIONAL"<?= $lifelines['electricity'] === 'FUNCTIONAL' ? ' selected' : '' ?>>FUNCTIONAL</option>
            <option value="NOT FUNCTIONAL"<?= $lifelines['electricity'] === 'NOT FUNCTIONAL' ? ' selected' : '' ?>>NOT FUNCTIONAL</option>
          </select>
        </div>
      </div>
    </div>
  </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Live preview</h5>
            <span class="badge bg-secondary fw-normal">Updates as you edit — matches public home page hero</span>
        </div>
        <div class="card-body p-3 p-md-4">
            <?= view('partials/admin_landing_preview', [
                'previewSlides' => $previewSlides ?? [],
                'stripText'     => $announcementStrip ?? '',
                'previewId'     => 'adminLandingPreview',
            ]) ?>
        </div>
    </div>

    <div class="modal fade slide-editor-modal" id="heroSlideEditorModal" tabindex="-1" aria-labelledby="heroSlideEditorModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content slide-editor-modal__content">
                <div class="modal-header slide-editor-modal__header">
                    <div>
                        <h5 class="modal-title mb-1" id="heroSlideEditorModalLabel"><i class="bi bi-aspect-ratio me-2"></i>Hero slide editor</h5>
                        <p class="text-muted small mb-0">Drag the image inside the frame and zoom until the exact area looks right.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body slide-editor-modal__body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-8">
                            <div class="slide-editor-modal__stage-shell">
                                <div class="slide-editor-modal__stage" id="heroSlideEditorStage" role="img" aria-label="Hero slide crop preview">
                                    <img id="heroSlideEditorImage" alt="Hero slide preview" class="slide-editor-modal__image">
                                    <div class="slide-editor-modal__grid" aria-hidden="true"></div>
                                    <div class="slide-editor-modal__focus" aria-hidden="true"></div>
                                    <div class="slide-editor-modal__label" id="heroSlideEditorSlotLabel">Slide</div>
                                </div>
                            </div>
                            <div class="slide-editor-modal__tip mt-3">
                                <i class="bi bi-hand-index-thumb me-2" aria-hidden="true"></i>
                                Drag to reposition. Use the zoom slider or mouse wheel to adjust how much of the image is visible.
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="slide-editor-modal__panel">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="heroSlideEditorZoom">Zoom</label>
                                    <input type="range" class="form-range" id="heroSlideEditorZoom" min="1" max="2.5" step="0.05" value="1">
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span id="heroSlideEditorZoomLabel">1.00x</span>
                                        <span>Range: 1.00x - 2.50x</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="small text-muted mb-1">Visible area</div>
                                    <div class="slide-editor-modal__readout">
                                        <span id="heroSlideEditorPositionLabel">Position: 50% 50%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Quick actions</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="heroSlideEditorCenterBtn">Center</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="heroSlideEditorResetBtn">Reset</button>
                                    </div>
                                </div>
                                <div class="alert alert-info mb-0 small">
                                    Any image size works. This editor shows the exact crop the public hero will use.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer slide-editor-modal__footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="heroSlideEditorApplyBtn">Use this crop</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tappy's message on the public front page.
         Keyed "home" because the front page has no path segment at all; the form
         still posts to the same endpoint, and the controller saves it under that
         key. Mirrors the card on admin/childpro-gad and admin/programs-projects. -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-chat-quote me-2"></i>Tappy's message on the public home page</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small">
                Tappy floats in the corner of the public
                <a href="<?= base_url() ?>" target="_blank" rel="noopener">home page</a>.
                Write what he should say there. Leave both boxes empty to go back to
                the built-in wording.
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">Pose</label>
                    <select class="form-select form-select-sm" name="mascot_pose">
                        <?php foreach (($mascotPoses ?? ['hero' => 'Waving (default)']) as $poseValue => $poseLabel): ?>
                            <option value="<?= esc($poseValue) ?>" <?= ($mascot['pose'] ?? 'hero') === $poseValue ? 'selected' : '' ?>><?= esc($poseLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small">Heading</label>
                    <input type="text" class="form-control form-control-sm" name="mascot_title"
                           value="<?= esc($mascot['title'] ?? '') ?>" maxlength="60"
                           placeholder="<?= esc($mascot['defaults']['title'] ?? 'Welcome') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small">Message</label>
                    <textarea class="form-control form-control-sm" name="mascot_text" rows="2" maxlength="240"
                              placeholder="<?= esc($mascot['defaults']['text'] ?? '') ?>"></textarea>
                    <div class="form-text">
                        Currently using:
                        <strong><?= esc($mascot['defaults']['text'] ?? '') ?></strong>
                        <?php if ($mascot['isCustom'] ?? false): ?>
                            &mdash; this box will replace it. Clear both boxes to restore the default.
                        <?php else: ?>
                            &mdash; the built-in wording. Anything you type here overrides it.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg px-4">
        <i class="bi bi-check-circle me-2"></i>Save landing page
    </button>
    <a href="<?= base_url() ?>" class="btn btn-outline-secondary btn-lg ms-2" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-2"></i>Open home page
    </a>
</form>

<script type="application/json" id="adminLandingPreviewSlots"><?= json_encode([
    1 => $slides[1]['url'] ?? '',
    2 => $slides[2]['url'] ?? '',
    3 => $slides[3]['url'] ?? '',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>

<script type="application/json" id="adminLandingPreviewLifelines"><?= json_encode($lifelines ?? [
  'water' => 'FUNCTIONAL',
  'communication' => 'FUNCTIONAL',
  'electricity' => 'FUNCTIONAL',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script>
if (false) {
(function () {
  const previewRoot = document.getElementById('adminLandingPreview');
  const dataEl = document.getElementById('adminLandingPreviewData');
  const slotsEl = document.getElementById('adminLandingPreviewSlots');
  const stripField = document.getElementById('announcement_strip');
  const stripEl = document.getElementById('adminLandingPreviewStrip');
  if (!previewRoot || !dataEl) return;

  let config;
  try {
    config = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    config = { slides: [], defaultBanner: '', strip: '' };
  }

  // Remove slide handling: immediately mark and submit (no confirmation)
  document.querySelectorAll('.remove-slide-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var slot = btn.getAttribute('data-slot');
      var input = document.getElementById('remove_slide_' + slot);
      if (input) input.value = '1';
      var form = btn.closest('form');
      if (form) form.submit();
    }, false);
  });

  let slotUrls = { 1: '', 2: '', 3: '' };
  try {
    slotUrls = Object.assign(slotUrls, JSON.parse(slotsEl ? slotsEl.textContent : '{}'));
  } catch (e) { /* keep defaults */ }

  const stage = document.getElementById('adminLandingPreviewStage');
  const defaultBanner = config.defaultBanner || '';
  let objectUrls = [];

  function revokeObjectUrls() {
    objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    objectUrls = [];
  }

  function normalizePositionString(position) {
    position = String(position || '50% 50%').replace(/\s+/g, ' ').trim();
    const matches = position.match(/^(\d{1,3})%\s+(\d{1,3})%$/);
    if (!matches) {
      return '50% 50%';
    }
    const x = Math.min(100, Math.max(0, Number(matches[1])));
    const y = Math.min(100, Math.max(0, Number(matches[2])));
    return x + '% ' + y + '%';
  }

  function normalizeScaleNumber(value) {
    const scale = Number(value);
    if (Number.isNaN(scale)) {
      return 1.0;
    }
    return Math.min(2.5, Math.max(1.0, scale));
  }

  function getSlideMeta(slot) {
    const posEl = document.getElementById('hero_slide_' + slot + '_position');
    const scaleEl = document.getElementById('hero_slide_' + slot + '_scale');
    return {
      position: normalizePositionString(posEl ? posEl.value : '50% 50%'),
      scale: normalizeScaleNumber(scaleEl ? scaleEl.value : 1),
    };
  }

  function setSlideMeta(slot, position, scale) {
    const posEl = document.getElementById('hero_slide_' + slot + '_position');
    const scaleEl = document.getElementById('hero_slide_' + slot + '_scale');
    const zoomEl = document.getElementById('hero_slide_' + slot + '_zoom');
    const labelEl = document.getElementById('hero_slide_' + slot + '_position_label');
    const zoomLabelEl = document.getElementById('hero_slide_' + slot + '_zoom_label');
    const normalizedPosition = normalizePositionString(position);
    const normalizedScale = normalizeScaleNumber(scale);

    if (posEl) {
      posEl.value = normalizedPosition;
    }
    if (scaleEl) {
      scaleEl.value = normalizedScale.toFixed(2);
    }
    if (zoomEl) {
      zoomEl.value = normalizedScale.toFixed(2);
      zoomEl.disabled = false;
    }
    if (labelEl) {
      labelEl.textContent = 'Position: ' + normalizedPosition;
    }
    if (zoomLabelEl) {
      zoomLabelEl.textContent = normalizedScale.toFixed(2) + '×';
    }

    const previewImg = document.querySelector('.slide-preview-img[data-slot="' + slot + '"]');
    if (previewImg) {
      previewImg.style.objectPosition = normalizedPosition;
      previewImg.style.transform = 'scale(' + normalizedScale + ')';
    }
  }

  function updateSlideEditorUI(slot) {
    const meta = getSlideMeta(slot);
    const zoomInput = document.querySelector('.slide-zoom-range[data-slot="' + slot + '"]');
    const labelEl = document.getElementById('hero_slide_' + slot + '_position_label');
    const zoomLabelEl = document.getElementById('hero_slide_' + slot + '_zoom_label');
    if (zoomInput) {
      zoomInput.value = meta.scale.toFixed(2);
      zoomInput.disabled = false;
    }
    if (labelEl) {
      labelEl.textContent = 'Position: ' + meta.position;
    }
    if (zoomLabelEl) {
      zoomLabelEl.textContent = meta.scale.toFixed(2) + '×';
    }
    setSlideMeta(slot, meta.position, meta.scale);
  }

  function buildSlidesFromForm() {
    const slides = [];
    for (let slot = 1; slot <= 3; slot++) {
      const remove = document.getElementById('remove_slide_' + slot);
      if (remove && (remove.checked || remove.value === '1')) continue;

      const meta = getSlideMeta(slot);
      const fileInput = document.getElementById('hero_slide_' + slot);
      const file = fileInput && fileInput.files && fileInput.files[0];
      if (file) {
        const url = URL.createObjectURL(file);
        let previewImg = document.querySelector('.slide-preview-img[data-slot="' + slot + '"]');
        const wrapper = document.querySelector('.slide-editor__preview-wrapper[data-slot="' + slot + '"]');
        if (!previewImg && wrapper) {
          wrapper.innerHTML = '';
          previewImg = document.createElement('img');
          previewImg.className = 'img-fluid w-100 slide-preview-img';
          previewImg.dataset.slot = slot;
          previewImg.style.aspectRatio = '2.5/1';
          previewImg.style.objectFit = 'cover';
          wrapper.appendChild(previewImg);
          const hint = document.createElement('div');
          hint.className = 'slide-editor__preview-hint';
          hint.textContent = 'Drag image to reposition';
          wrapper.appendChild(hint);
        }
        if (previewImg) {
          previewImg.src = url;
          const meta = getSlideMeta(slot);
          setSlideMeta(slot, meta.position, meta.scale);
        }
        renderHero();
        return;
      }

      const existingUrl = slotUrls[String(slot)] || slotUrls[slot] || '';
      if (existingUrl) {
        slides.push({ url: existingUrl, alt: 'Hero slide ' + slot, position: meta.position, scale: meta.scale });
      }
    }
    if (slides.length === 0 && defaultBanner) {
      slides.push({ url: defaultBanner, alt: 'Default school banner', position: '50% 50%', scale: 1 });
    }
    return slides;
  }

  function setActiveSlide(index) {
    const slides = stage.querySelectorAll('.hero-slide');
    const dots = previewRoot.querySelectorAll('.hero-slideshow__dot');
    slides.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
    });
    dots.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
      el.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });
  }

  function renderHero() {
    revokeObjectUrls();
    const slides = buildSlidesFromForm();
    previewRoot.setAttribute('data-slide-count', String(slides.length));
    stage.innerHTML = '';

    if (slides.length === 0) {
      stage.innerHTML = '<div class="admin-landing-preview__empty">No hero images — default banner will be used on the home page.</div>';
      const oldDots = previewRoot.querySelector('.hero-slideshow__dots');
      if (oldDots) oldDots.remove();
      return;
    }

    slides.forEach(function (slide, index) {
      const wrap = document.createElement('div');
      wrap.className = 'hero-slide' + (index === 0 ? ' is-active' : '');
      wrap.setAttribute('data-slide-index', String(index));
      const img = document.createElement('img');
      img.className = 'hero-banner-img';
      img.src = slide.url;
      img.alt = slide.alt || ('Hero slide ' + (index + 1));
      img.width = 1983;
      img.height = 793;
      img.decoding = 'async';
      img.style.objectPosition = slide.position || '50% 50%';
      img.style.transform = 'scale(' + (slide.scale || 1) + ')';
      img.style.transformOrigin = 'center center';
      wrap.appendChild(img);
      stage.appendChild(wrap);
    });

    let dotsWrap = previewRoot.querySelector('.hero-slideshow__dots');
    if (slides.length > 1) {
      if (!dotsWrap) {
        dotsWrap = document.createElement('div');
        dotsWrap.className = 'hero-slideshow__dots';
        dotsWrap.setAttribute('role', 'tablist');
        dotsWrap.setAttribute('aria-label', 'Preview slideshow');
        previewRoot.querySelector('.hero-slideshow').appendChild(dotsWrap);
      }
      dotsWrap.innerHTML = '';
      slides.forEach(function (_, index) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hero-slideshow__dot' + (index === 0 ? ' is-active' : '');
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-label', 'Slide ' + (index + 1));
        btn.setAttribute('data-goto', String(index));
        btn.addEventListener('click', function () { setActiveSlide(index); });
        dotsWrap.appendChild(btn);
      });
      dotsWrap.style.display = '';
    } else if (dotsWrap) {
      dotsWrap.remove();
    }
  }

  function updateStrip() {
    if (!stripField || !stripEl) return;
    const text = stripField.value.trim();
    stripEl.classList.toggle('is-hidden', text === '');
    stripEl.setAttribute('aria-hidden', text === '' ? 'true' : 'false');
    stripEl.querySelectorAll('[data-strip-text]').forEach(function (node) {
      node.textContent = text || ' ';
    });
  }

  function updateLifelines() {
    try {
      const lifelinesEl = document.getElementById('adminLandingPreviewLifelines');
      let values = lifelinesEl ? JSON.parse(lifelinesEl.textContent || '{}') : {};
      // prefer form values if present
      ['water','communication','electricity'].forEach(function (k) {
        const sel = document.getElementById('lifeline_' + k);
        if (sel) values[k] = sel.value;
      });

      const root = previewRoot.querySelector('.hero-lifelines');
      if (!root) return;
      const cards = root.querySelectorAll('.lifeline-card');
      if (cards.length < 3) return;
      const keys = ['water','communication','electricity'];
      cards.forEach(function (card, i) {
            const status = (values[keys[i]] || '').toUpperCase();
            const statusEl = card.querySelector('.lifeline-status');
            if (statusEl) statusEl.textContent = status || 'FUNCTIONAL';
            // toggle down state
            if (String(status).trim() === 'NOT FUNCTIONAL') {
              card.classList.add('is-down');
            } else {
              card.classList.remove('is-down');
            }
      });
    } catch (e) { /* ignore */ }
  }

  for (let slot = 1; slot <= 3; slot++) {
    const zoomInput = document.querySelector('.slide-zoom-range[data-slot="' + slot + '"]');
    const resetButton = document.querySelector('.reset-slide-edit[data-slot="' + slot + '"]');
    const previewWrapper = document.querySelector('.slide-editor__preview-wrapper[data-slot="' + slot + '"]');

    if (zoomInput) {
      zoomInput.addEventListener('input', function () {
        const meta = getSlideMeta(slot);
        setSlideMeta(slot, meta.position, this.value);
        renderHero();
      }, false);
    }

    if (resetButton) {
      resetButton.addEventListener('click', function () {
        setSlideMeta(slot, '50% 50%', 1.0);
        renderHero();
      }, false);
    }

    if (previewWrapper) {
      let dragging = false;
      let startX = 0;
      let startY = 0;
      let pointerId = null;
      let startPos = { x: 50, y: 50 };

      previewWrapper.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.button !== 0) {
          return;
        }
        const previewImg = previewWrapper.querySelector('.slide-preview-img');
        if (!previewImg) {
          return;
        }
        event.preventDefault();
        pointerId = event.pointerId;
        previewWrapper.setPointerCapture(pointerId);
        dragging = true;
        startX = event.clientX;
        startY = event.clientY;
        const meta = getSlideMeta(slot);
        const parts = meta.position.split(' ');
        startPos = {
          x: Number(parts[0].replace('%', '')) || 50,
          y: Number(parts[1].replace('%', '')) || 50,
        };
        previewWrapper.classList.add('is-dragging');
      }, false);

      previewWrapper.addEventListener('pointermove', function (event) {
        if (!dragging) {
          return;
        }
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        const rect = previewWrapper.getBoundingClientRect();
        const dragBasis = Math.max(Math.min(rect.width, rect.height), 1);
        const x = Math.min(100, Math.max(0, startPos.x + dx / dragBasis * 100));
        const y = Math.min(100, Math.max(0, startPos.y + dy / dragBasis * 100));
        setSlideMeta(slot, x + '% ' + y + '%', getSlideMeta(slot).scale);
        renderHero();
      }, false);

      const stopDrag = function () {
        if (!dragging) {
          return;
        }
        dragging = false;
        pointerId = null;
        previewWrapper.classList.remove('is-dragging');
      };

      previewWrapper.addEventListener('pointerup', stopDrag, false);
      previewWrapper.addEventListener('pointercancel', stopDrag, false);
      previewWrapper.addEventListener('pointerleave', stopDrag, false);
    }

    updateSlideEditorUI(slot);
  }

  for (let slot = 1; slot <= 3; slot++) {
    const fileInput = document.getElementById('hero_slide_' + slot);
    if (!fileInput) {
      continue;
    }

    fileInput.addEventListener('change', function () {
      const file = fileInput.files && fileInput.files[0];
      const errorEl = fileInput.closest('.border') ? fileInput.closest('.border').querySelector('.slide-input-error') : null;
      if (errorEl) {
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
      }

      if (!file) {
        renderHero();
        return;
      }

      if (file.size > 50 * 1024 * 1024) {
        if (errorEl) {
          errorEl.textContent = ' File is too large (max 50MB).';
          errorEl.classList.remove('d-none');
        }
        fileInput.value = '';
        renderHero();
        return;
      }

      const url = URL.createObjectURL(file);
      let previewImg = document.querySelector('.slide-preview-img[data-slot="' + slot + '"]');
      const wrapper = document.querySelector('.slide-editor__preview-wrapper[data-slot="' + slot + '"]');
      if (!previewImg && wrapper) {
        wrapper.innerHTML = '';
        previewImg = document.createElement('img');
        previewImg.className = 'img-fluid w-100 slide-preview-img';
        previewImg.dataset.slot = slot;
        previewImg.style.aspectRatio = '2.5/1';
        previewImg.style.objectFit = 'cover';
        wrapper.appendChild(previewImg);
        const hint = document.createElement('div');
        hint.className = 'slide-editor__preview-hint';
        hint.textContent = 'Drag image to reposition';
        wrapper.appendChild(hint);
      }
      if (previewImg) {
        previewImg.src = url;
        const meta = getSlideMeta(slot);
        setSlideMeta(slot, meta.position, meta.scale);
      }

      renderHero();
    }, false);
  }

  if (stripField) {
    stripField.addEventListener('input', updateStrip);
  }

  // lifelines live update
  ['lifeline_water','lifeline_communication','lifeline_electricity'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', updateLifelines);
  });

  // initial lifeline render
  updateLifelines();

  previewRoot.addEventListener('click', function (e) {
    const dot = e.target.closest('.hero-slideshow__dot');
    if (!dot) return;
    const idx = parseInt(dot.getAttribute('data-goto'), 10);
    if (!isNaN(idx)) setActiveSlide(idx);
  });

  updateStrip();
})();
}
</script>

<script>
(function () {
  const previewRoot = document.getElementById('adminLandingPreview');
  const dataEl = document.getElementById('adminLandingPreviewData');
  const slotsEl = document.getElementById('adminLandingPreviewSlots');
  const stripField = document.getElementById('announcement_strip');
  const stripEl = document.getElementById('adminLandingPreviewStrip');
  const stage = document.getElementById('adminLandingPreviewStage');
  if (!previewRoot || !dataEl || !stage) return;

  let config = { slides: [], defaultBanner: '', strip: '' };
  try {
    config = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    config = { slides: [], defaultBanner: '', strip: '' };
  }

  let slotUrls = { 1: '', 2: '', 3: '' };
  try {
    slotUrls = Object.assign(slotUrls, JSON.parse(slotsEl ? slotsEl.textContent : '{}'));
  } catch (e) { /* keep defaults */ }

  const modalEl = document.getElementById('heroSlideEditorModal');
  let modalInstance = null;
  function getModalInstance() {
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
      return null;
    }

    if (!modalInstance) {
      modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    return modalInstance;
  }
  const editorImage = document.getElementById('heroSlideEditorImage');
  const editorStage = document.getElementById('heroSlideEditorStage');
  const editorZoom = document.getElementById('heroSlideEditorZoom');
  const editorZoomLabel = document.getElementById('heroSlideEditorZoomLabel');
  const editorPositionLabel = document.getElementById('heroSlideEditorPositionLabel');
  const editorSlotLabel = document.getElementById('heroSlideEditorSlotLabel');
  const editorApplyBtn = document.getElementById('heroSlideEditorApplyBtn');
  const editorCenterBtn = document.getElementById('heroSlideEditorCenterBtn');
  const editorResetBtn = document.getElementById('heroSlideEditorResetBtn');

  const defaultBanner = config.defaultBanner || '';
  const objectUrls = [];
  const editor = {
    slot: null,
    file: null,
    fileInput: null,
    sourceType: 'existing',
    sourceUrl: '',
    objectUrl: '',
    position: '50% 50%',
    scale: 1,
    applied: false,
    dragging: false,
    startX: 0,
    startY: 0,
    startPos: { x: 50, y: 50 },
  };

  function revokeObjectUrls() {
    while (objectUrls.length > 0) {
      const url = objectUrls.pop();
      URL.revokeObjectURL(url);
    }
  }

  function normalizePositionString(position) {
    position = String(position || '50% 50%').replace(/\s+/g, ' ').trim();
    const matches = position.match(/^(\d{1,3})%\s+(\d{1,3})%$/);
    if (!matches) {
      return '50% 50%';
    }
    const x = Math.min(100, Math.max(0, Number(matches[1])));
    const y = Math.min(100, Math.max(0, Number(matches[2])));
    return x + '% ' + y + '%';
  }

  function normalizeScaleNumber(value) {
    const scale = Number(value);
    if (Number.isNaN(scale)) {
      return 1.0;
    }
    return Math.min(2.5, Math.max(1.0, scale));
  }

  function positionToTuple(position) {
    const parts = normalizePositionString(position).split(' ');
    return {
      x: Number(parts[0].replace('%', '')) || 50,
      y: Number(parts[1].replace('%', '')) || 50,
    };
  }

  function tupleToPosition(x, y) {
    return Math.round(Math.min(100, Math.max(0, x))) + '% ' + Math.round(Math.min(100, Math.max(0, y))) + '%';
  }

  function getSlideMeta(slot) {
    const posEl = document.getElementById('hero_slide_' + slot + '_position');
    const scaleEl = document.getElementById('hero_slide_' + slot + '_scale');
    return {
      position: normalizePositionString(posEl ? posEl.value : '50% 50%'),
      scale: normalizeScaleNumber(scaleEl ? scaleEl.value : 1),
    };
  }

  function getPreviewWrapper(slot) {
    return document.querySelector('.slide-editor__preview-wrapper[data-slot="' + slot + '"]');
  }

  function getPreviewImg(slot) {
    return document.querySelector('.slide-preview-img[data-slot="' + slot + '"]');
  }

  function setSlideMeta(slot, position, scale) {
    const posEl = document.getElementById('hero_slide_' + slot + '_position');
    const scaleEl = document.getElementById('hero_slide_' + slot + '_scale');
    const zoomEl = document.getElementById('hero_slide_' + slot + '_zoom');
    const labelEl = document.getElementById('hero_slide_' + slot + '_position_label');
    const zoomLabelEl = document.getElementById('hero_slide_' + slot + '_zoom_label');
    const normalizedPosition = normalizePositionString(position);
    const normalizedScale = normalizeScaleNumber(scale);

    if (posEl) {
      posEl.value = normalizedPosition;
    }
    if (scaleEl) {
      scaleEl.value = normalizedScale.toFixed(2);
    }
    if (zoomEl) {
      zoomEl.value = normalizedScale.toFixed(2);
      zoomEl.disabled = false;
    }
    if (labelEl) {
      labelEl.textContent = 'Position: ' + normalizedPosition;
    }
    if (zoomLabelEl) {
      zoomLabelEl.textContent = normalizedScale.toFixed(2) + 'x';
    }

    const previewImg = getPreviewImg(slot);
    if (previewImg) {
      previewImg.style.objectPosition = normalizedPosition;
      previewImg.style.transform = 'scale(' + normalizedScale + ')';
    }
  }

  function updateSlideEditorUI(slot) {
    const meta = getSlideMeta(slot);
    const zoomInput = document.querySelector('.slide-zoom-range[data-slot="' + slot + '"]');
    const labelEl = document.getElementById('hero_slide_' + slot + '_position_label');
    const zoomLabelEl = document.getElementById('hero_slide_' + slot + '_zoom_label');
    if (zoomInput) {
      zoomInput.value = meta.scale.toFixed(2);
      zoomInput.disabled = false;
    }
    if (labelEl) {
      labelEl.textContent = 'Position: ' + meta.position;
    }
    if (zoomLabelEl) {
      zoomLabelEl.textContent = meta.scale.toFixed(2) + 'x';
    }
    setSlideMeta(slot, meta.position, meta.scale);
  }

  function renderPreviewCardImage(slot, url, position, scale, alt) {
    let previewImg = getPreviewImg(slot);
    const wrapper = getPreviewWrapper(slot);

    if (!previewImg && wrapper) {
      wrapper.innerHTML = '';
      previewImg = document.createElement('img');
      previewImg.className = 'img-fluid w-100 slide-preview-img';
      previewImg.dataset.slot = slot;
      previewImg.style.aspectRatio = '2.5/1';
      previewImg.style.objectFit = 'cover';
      wrapper.appendChild(previewImg);
      const hint = document.createElement('div');
      hint.className = 'slide-editor__preview-hint';
      hint.textContent = 'Drag image to reposition';
      wrapper.appendChild(hint);
    }

    if (previewImg) {
      previewImg.src = url;
      previewImg.alt = alt || ('Hero slide ' + slot);
      previewImg.style.objectPosition = position;
      previewImg.style.transform = 'scale(' + scale + ')';
    }
  }

  function syncPreviewCard(slot, url, position, scale) {
    const wrapper = getPreviewWrapper(slot);
    if (!wrapper || !url) {
      return;
    }
    let img = getPreviewImg(slot);
    if (!img) {
      wrapper.innerHTML = '';
      img = document.createElement('img');
      img.className = 'img-fluid w-100 slide-preview-img';
      img.dataset.slot = String(slot);
      img.style.aspectRatio = '2.5/1';
      img.style.objectFit = 'cover';
      wrapper.appendChild(img);
      const hint = document.createElement('div');
      hint.className = 'slide-editor__preview-hint';
      hint.textContent = 'Drag image to reposition';
      wrapper.appendChild(hint);
    }
    img.src = url;
    img.alt = 'Hero slide ' + slot;
    img.style.objectPosition = normalizePositionString(position);
    img.style.transform = 'scale(' + normalizeScaleNumber(scale) + ')';
    img.style.transformOrigin = 'center center';
  }

  function buildSlidesFromForm() {
    const slides = [];
    for (let slot = 1; slot <= 3; slot++) {
      const remove = document.getElementById('remove_slide_' + slot);
      if (remove && (remove.checked || remove.value === '1')) {
        continue;
      }

      const meta = getSlideMeta(slot);
      const fileInput = document.getElementById('hero_slide_' + slot);
      const file = fileInput && fileInput.files && fileInput.files[0];
      if (file) {
        const url = URL.createObjectURL(file);
        objectUrls.push(url);
        slides.push({ url: url, alt: 'Hero slide ' + slot, position: meta.position, scale: meta.scale });
        syncPreviewCard(slot, url, meta.position, meta.scale);
        continue;
      }

      const existingUrl = slotUrls[String(slot)] || slotUrls[slot] || '';
      if (existingUrl) {
        slides.push({ url: existingUrl, alt: 'Hero slide ' + slot, position: meta.position, scale: meta.scale });
        syncPreviewCard(slot, existingUrl, meta.position, meta.scale);
      }
    }

    if (slides.length === 0 && defaultBanner) {
      slides.push({ url: defaultBanner, alt: 'Default school banner', position: '50% 50%', scale: 1 });
    }

    return slides;
  }

  function setActiveSlide(index) {
    const slides = stage.querySelectorAll('.hero-slide');
    const dots = previewRoot.querySelectorAll('.hero-slideshow__dot');
    slides.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
    });
    dots.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
      el.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });
  }

  function renderHero() {
    revokeObjectUrls();
    const slides = buildSlidesFromForm();
    previewRoot.setAttribute('data-slide-count', String(slides.length));
    stage.innerHTML = '';

    if (slides.length === 0) {
      stage.innerHTML = '<div class="admin-landing-preview__empty">No hero images - default banner will be used on the home page.</div>';
      const oldDots = previewRoot.querySelector('.hero-slideshow__dots');
      if (oldDots) oldDots.remove();
      return;
    }

    slides.forEach(function (slide, index) {
      const wrap = document.createElement('div');
      wrap.className = 'hero-slide' + (index === 0 ? ' is-active' : '');
      wrap.setAttribute('data-slide-index', String(index));
      const img = document.createElement('img');
      img.className = 'hero-banner-img';
      img.src = slide.url;
      img.alt = slide.alt || ('Hero slide ' + (index + 1));
      img.width = 1983;
      img.height = 793;
      img.decoding = 'async';
      img.style.objectPosition = slide.position || '50% 50%';
      img.style.transform = 'scale(' + (slide.scale || 1) + ')';
      img.style.transformOrigin = 'center center';
      wrap.appendChild(img);
      stage.appendChild(wrap);
    });

    let dotsWrap = previewRoot.querySelector('.hero-slideshow__dots');
    if (slides.length > 1) {
      if (!dotsWrap) {
        dotsWrap = document.createElement('div');
        dotsWrap.className = 'hero-slideshow__dots';
        dotsWrap.setAttribute('role', 'tablist');
        dotsWrap.setAttribute('aria-label', 'Preview slideshow');
        previewRoot.querySelector('.hero-slideshow').appendChild(dotsWrap);
      }
      dotsWrap.innerHTML = '';
      slides.forEach(function (_, index) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hero-slideshow__dot' + (index === 0 ? ' is-active' : '');
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-label', 'Slide ' + (index + 1));
        btn.setAttribute('data-goto', String(index));
        btn.addEventListener('click', function () { setActiveSlide(index); });
        dotsWrap.appendChild(btn);
      });
      dotsWrap.style.display = '';
    } else if (dotsWrap) {
      dotsWrap.remove();
    }
  }

  function updateStrip() {
    if (!stripField || !stripEl) return;
    const text = stripField.value.trim();
    stripEl.classList.toggle('is-hidden', text === '');
    stripEl.setAttribute('aria-hidden', text === '' ? 'true' : 'false');
    stripEl.querySelectorAll('[data-strip-text]').forEach(function (node) {
      node.textContent = text || ' ';
    });
  }

  function updateLifelines() {
    try {
      const lifelinesEl = document.getElementById('adminLandingPreviewLifelines');
      let values = lifelinesEl ? JSON.parse(lifelinesEl.textContent || '{}') : {};
      ['water', 'communication', 'electricity'].forEach(function (k) {
        const sel = document.getElementById('lifeline_' + k);
        if (sel) values[k] = sel.value;
      });

      const root = previewRoot.querySelector('.hero-lifelines');
      if (!root) return;
      const cards = root.querySelectorAll('.lifeline-card');
      if (cards.length < 3) return;
      const keys = ['water', 'communication', 'electricity'];
      cards.forEach(function (card, i) {
        const status = (values[keys[i]] || '').toUpperCase();
        const statusEl = card.querySelector('.lifeline-status');
        if (statusEl) statusEl.textContent = status || 'FUNCTIONAL';
        if (String(status).trim() === 'NOT FUNCTIONAL') {
          card.classList.add('is-down');
        } else {
          card.classList.remove('is-down');
        }
      });
    } catch (e) { /* ignore */ }
  }

  function renderEditorPreview() {
    if (!editorImage) return;
    const position = normalizePositionString(editor.position);
    const scale = normalizeScaleNumber(editor.scale);
    editorImage.style.objectFit = 'cover';
    editorImage.style.objectPosition = position;
    editorImage.style.transform = 'scale(' + scale + ')';
    editorImage.style.transformOrigin = 'center center';
    if (editorZoom) {
      editorZoom.value = scale.toFixed(2);
    }
    if (editorZoomLabel) {
      editorZoomLabel.textContent = scale.toFixed(2) + 'x';
    }
    if (editorPositionLabel) {
      editorPositionLabel.textContent = 'Position: ' + position;
    }
  }

  function openEditor(slot) {
    const fileInput = document.getElementById('hero_slide_' + slot);
    const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    const existingUrl = slotUrls[String(slot)] || slotUrls[slot] || '';
    let sourceUrl = existingUrl;

    editor.applied = false;
    editor.slot = slot;
    editor.fileInput = fileInput;
    editor.position = getSlideMeta(slot).position;
    editor.scale = getSlideMeta(slot).scale;

    if (file) {
      sourceUrl = URL.createObjectURL(file);
      editor.objectUrl = sourceUrl;
      editor.file = file;
      editor.sourceType = 'file';
      editor.sourceUrl = sourceUrl;
      if (fileInput) {
        fileInput.value = '';
      }
    } else {
      editor.objectUrl = '';
      editor.file = null;
      editor.sourceType = 'existing';
      editor.sourceUrl = sourceUrl;
    }

    if (!sourceUrl || !editorImage) {
      return;
    }

    if (editorSlotLabel) {
      editorSlotLabel.textContent = 'Slide ' + slot;
    }

    editorImage.onload = function () {
      renderEditorPreview();
    };
    editorImage.src = sourceUrl;
    renderEditorPreview();

    const instance = getModalInstance();
    if (instance) {
      instance.show();
    }
  }

  function cleanupEditor() {
    if (editor.objectUrl) {
      URL.revokeObjectURL(editor.objectUrl);
      editor.objectUrl = '';
    }
    if (!editor.applied && editor.sourceType === 'file' && editor.fileInput) {
      editor.fileInput.value = '';
    }
    editor.file = null;
    editor.fileInput = null;
    editor.sourceType = 'existing';
    editor.sourceUrl = '';
    editor.slot = null;
    editor.dragging = false;
    editor.applied = false;
  }

  function applyEditor() {
    if (editor.slot === null) {
      return;
    }

    if (editor.sourceType === 'file' && editor.fileInput && editor.file) {
      try {
        const dt = new DataTransfer();
        dt.items.add(editor.file);
        editor.fileInput.files = dt.files;
      } catch (e) {
        if (window.console && console.warn) {
          console.warn('Could not keep the selected hero slide file after editing.', e);
        }
      }
    }

    setSlideMeta(editor.slot, editor.position, editor.scale);
    editor.applied = true;
    renderHero();

    const wrapper = getPreviewWrapper(editor.slot);
    if (wrapper && typeof wrapper.scrollIntoView === 'function') {
      wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const instance = getModalInstance();
    if (instance) {
      instance.hide();
    }
  }

  function centerEditor() {
    editor.position = '50% 50%';
    renderEditorPreview();
  }

  function resetEditor() {
    editor.position = '50% 50%';
    editor.scale = 1.0;
    renderEditorPreview();
  }

  function pointerToPosition(event) {
    if (!editorStage) {
      return editor.position;
    }
    const rect = editorStage.getBoundingClientRect();
    const dragBasis = Math.max(Math.min(rect.width, rect.height), 1);
    const dx = event.clientX - editor.startX;
    const dy = event.clientY - editor.startY;
    const x = editor.startPos.x + dx / dragBasis * 100;
    const y = editor.startPos.y + dy / dragBasis * 100;
    return tupleToPosition(x, y);
  }

  function bindEditorControls() {
    if (editorZoom) {
      editorZoom.addEventListener('input', function () {
        editor.scale = normalizeScaleNumber(this.value);
        renderEditorPreview();
      });
    }

    if (editorCenterBtn) {
      editorCenterBtn.addEventListener('click', centerEditor);
    }

    if (editorResetBtn) {
      editorResetBtn.addEventListener('click', resetEditor);
    }

    if (editorApplyBtn) {
      editorApplyBtn.addEventListener('click', applyEditor);
    }

    if (editorStage) {
      editorStage.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.button !== 0) {
          return;
        }
        if (editor.slot === null) {
          return;
        }
        event.preventDefault();
        editor.dragging = true;
        editor.startX = event.clientX;
        editor.startY = event.clientY;
        editor.startPos = positionToTuple(editor.position);
        editorStage.setPointerCapture(event.pointerId);
        editorStage.classList.add('is-dragging');
      });

      editorStage.addEventListener('pointermove', function (event) {
        if (!editor.dragging) {
          return;
        }
        editor.position = pointerToPosition(event);
        renderEditorPreview();
      });

      const stopDragging = function () {
        if (!editor.dragging) {
          return;
        }
        editor.dragging = false;
        editorStage.classList.remove('is-dragging');
      };

      editorStage.addEventListener('pointerup', stopDragging);
      editorStage.addEventListener('pointercancel', stopDragging);
      editorStage.addEventListener('pointerleave', stopDragging);
      editorStage.addEventListener('wheel', function (event) {
        event.preventDefault();
        const delta = event.deltaY > 0 ? -0.05 : 0.05;
        editor.scale = normalizeScaleNumber(editor.scale + delta);
        renderEditorPreview();
      }, { passive: false });
    }

    if (modalEl) {
      modalEl.addEventListener('hidden.bs.modal', function () {
        cleanupEditor();
      });
    }
  }

  function bindQuickControls() {
    document.querySelectorAll('.remove-slide-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const slot = btn.getAttribute('data-slot');
        const input = document.getElementById('remove_slide_' + slot);
        if (input) input.value = '1';
        const form = btn.closest('form');
        if (form) form.submit();
      }, false);
    });

    document.querySelectorAll('.open-slide-editor').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const slot = parseInt(btn.getAttribute('data-slot'), 10);
        if (!isNaN(slot)) {
          openEditor(slot);
        }
      });
    });

    for (let slot = 1; slot <= 3; slot++) {
      const zoomInput = document.querySelector('.slide-zoom-range[data-slot="' + slot + '"]');
      const resetButton = document.querySelector('.reset-slide-edit[data-slot="' + slot + '"]');
      const previewWrapper = getPreviewWrapper(slot);

      if (zoomInput) {
        zoomInput.addEventListener('input', function () {
          const meta = getSlideMeta(slot);
          setSlideMeta(slot, meta.position, this.value);
          renderHero();
        }, false);
      }

      if (resetButton) {
        resetButton.addEventListener('click', function () {
          setSlideMeta(slot, '50% 50%', 1.0);
          renderHero();
        }, false);
      }

      if (previewWrapper) {
        let dragging = false;
        let startX = 0;
        let startY = 0;
        let startPos = { x: 50, y: 50 };

        previewWrapper.addEventListener('pointerdown', function (event) {
          if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
          }
          const previewImg = previewWrapper.querySelector('.slide-preview-img');
          if (!previewImg) {
            return;
          }
          event.preventDefault();
          dragging = true;
          startX = event.clientX;
          startY = event.clientY;
          startPos = positionToTuple(getSlideMeta(slot).position);
          previewWrapper.setPointerCapture(event.pointerId);
          previewWrapper.classList.add('is-dragging');
        }, false);

        previewWrapper.addEventListener('pointermove', function (event) {
          if (!dragging) {
            return;
          }
          const rect = previewWrapper.getBoundingClientRect();
          const dragBasis = Math.max(Math.min(rect.width, rect.height), 1);
          const x = startPos.x + (event.clientX - startX) / dragBasis * 100;
          const y = startPos.y + (event.clientY - startY) / dragBasis * 100;
          setSlideMeta(slot, tupleToPosition(x, y), getSlideMeta(slot).scale);
          renderHero();
        }, false);

        const stopDrag = function () {
          if (!dragging) {
            return;
          }
          dragging = false;
          previewWrapper.classList.remove('is-dragging');
        };

        previewWrapper.addEventListener('pointerup', stopDrag, false);
        previewWrapper.addEventListener('pointercancel', stopDrag, false);
        previewWrapper.addEventListener('pointerleave', stopDrag, false);
      }

      updateSlideEditorUI(slot);
    }

    for (let slot = 1; slot <= 3; slot++) {
      const fileInput = document.getElementById('hero_slide_' + slot);
      if (!fileInput) {
        continue;
      }

      fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0];
        const errorEl = fileInput.closest('.border') ? fileInput.closest('.border').querySelector('.slide-input-error') : null;
        if (errorEl) {
          errorEl.classList.add('d-none');
          errorEl.textContent = '';
        }

        if (!file) {
          return;
        }

        if (file.size > 50 * 1024 * 1024) {
          if (errorEl) {
            errorEl.textContent = ' File is too large (max 50MB).';
            errorEl.classList.remove('d-none');
          }
          fileInput.value = '';
          return;
        }

        const mime = String(file.type || '').toLowerCase();
        const name = String(file.name || '').toLowerCase();
        if (mime && !mime.startsWith('image/') && !/\.(jpe?g|png|webp)$/i.test(name)) {
          if (errorEl) {
            errorEl.textContent = ' Please choose a JPG, PNG, or WEBP image.';
            errorEl.classList.remove('d-none');
          }
          fileInput.value = '';
          return;
        }

        openEditor(slot);
      }, false);
    }
  }

  function bindLifelines() {
    ['lifeline_water', 'lifeline_communication', 'lifeline_electricity'].forEach(function (id) {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('change', updateLifelines);
      }
    });
  }

  if (stripField) {
    stripField.addEventListener('input', updateStrip);
  }

  previewRoot.addEventListener('click', function (e) {
    const dot = e.target.closest('.hero-slideshow__dot');
    if (!dot) return;
    const idx = parseInt(dot.getAttribute('data-goto'), 10);
    if (!isNaN(idx)) setActiveSlide(idx);
  });

  bindEditorControls();
  bindQuickControls();
  bindLifelines();
  updateLifelines();
  renderHero();
  updateStrip();

  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function () {
      if (!editor.applied && editor.sourceType === 'file' && editor.fileInput) {
        editor.fileInput.value = '';
      }
    });
  }
})();
</script>

<!-- Remove slide confirmation modal -->
<!-- confirmation modal removed: remove is immediate -->

<?= $this->endSection() ?>
