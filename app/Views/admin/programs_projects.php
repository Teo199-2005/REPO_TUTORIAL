    <?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<link href="<?= asset_url('css/admin-landing-preview.css') ?>" rel="stylesheet" />

<div class="mb-4">
    <h1 class="h3 mb-2">Programs & Projects Management</h1>
    <p class="text-muted mb-0">Each tab below edits one public page &mdash; a header image, up to six content sections, and Tappy's own message on that page. What you save here is exactly what a visitor sees there.</p>
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

<?php

/**
 * Programs & Projects content manager.
 *
 * Two tabs, Programs and Projects, because the controller has always accepted
 * both and the public site has always served both (/programs and /projects) -
 * but this view used to hard-code $tab = 'programs' and render a single form, so
 * the Projects tab had no editor at all and nothing anyone typed could ever reach
 * the /projects page. The tab loop below is what fixes that.
 *
 * Each tab owns its own <form> and its own element ids. The ids are suffixed with
 * the tab name on purpose: the add/delete section script drives a container by
 * id, and two tabs sharing "sectionsContainer" means the Projects button
 * manipulates the Programs list.
 *
 * The Tappy card at the bottom of each tab writes the line he gives on that tab's
 * public page - same mechanism as admin/childpro-gad, where the tab name IS the
 * public path segment. Saving both boxes empty restores the built-in wording.
 *
 * @var array<string,array> $tabs keyed by tab name, from the controller
 * @var array<string,string> $mascotPoses
 */

$ppTabs     = $tabs ?? [];
$ppTabNames = array_keys($ppTabs);
$mascotPoses = $mascotPoses ?? ['hero' => 'Waving (default)'];
?>

<style>
.pp-row { display: flex; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1.5rem; }
.pp-col { flex: 0 0 calc(50% - 0.625rem); max-width: calc(50% - 0.625rem); }
.pp-card { height: 100%; display: flex; flex-direction: column; }
.pp-card .card-body { flex: 1 1 auto; overflow: auto; }
@media (max-width: 767px) {
    .pp-col { flex: 0 0 100%; max-width: 100%; }
}

.section-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #fff;
    position: relative;
}

.section-item .delete-section-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}
</style>

<?php if (count($ppTabNames) > 1): ?>
    <nav class="nav nav-tabs mb-4" id="ppAdminTabs" role="tablist">
        <?php foreach ($ppTabNames as $i => $tabName): ?>
            <button
              class="nav-link<?= $i === 0 ? ' active' : '' ?>"
              id="pp-tab-<?= esc($tabName) ?>"
              data-bs-toggle="tab"
              data-bs-target="#pp-pane-<?= esc($tabName) ?>"
              type="button"
              role="tab"
              aria-controls="pp-pane-<?= esc($tabName) ?>"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
            ><?= esc(ucfirst($tabName)) ?></button>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<div class="tab-content" id="ppAdminTabContent">
<?php foreach ($ppTabNames as $i => $tab): ?>
    <?php
        $tabData    = $ppTabs[$tab];
        $hero       = $tabData['hero'] ?? [];
        $sections   = $tabData['sections'] ?? [];
        $heroUrl    = $tabData['heroUrl'] ?? '';
        $mascot     = $tabData['mascot'] ?? ['pose' => 'hero', 'title' => '', 'text' => '', 'isCustom' => false, 'defaults' => ['title' => '', 'text' => '', 'pose' => 'hero']];
        $mascotPose = $mascot['pose'] ?? 'hero';
        $tabLabel   = ucfirst($tab);
    ?>
    <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="pp-pane-<?= esc($tab) ?>" role="tabpanel" aria-labelledby="pp-tab-<?= esc($tab) ?>" tabindex="0">

<form method="post" action="<?= base_url('admin/programs-projects/update') ?>" enctype="multipart/form-data" class="ppForm-<?= esc($tab) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= esc($tab) ?>">

    <p class="text-muted small">
        This tab publishes to <a href="<?= base_url($tab) ?>" target="_blank" rel="noopener"><?= esc(base_url($tab)) ?></a>.
    </p>

    <!-- Hero Section -->
    <div class="pp-row">
        <div class="pp-col">
            <div class="card border-0 shadow-sm pp-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-image me-2"></i><?= esc($tabLabel) ?> &mdash; Header Image</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hero Image</label>
                        <div class="mb-2">
                            <?php if ($heroUrl !== ''): ?>
                                <img src="<?= esc($heroUrl) ?>" alt="Hero preview" class="img-fluid w-100" style="max-height: 280px; object-fit: cover;">
                            <?php else: ?>
                                <div class="alert alert-light border">No image uploaded yet.</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" class="form-control" name="hero_image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, WEBP — max 50MB.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="hero_title" value="<?= esc($hero['title'] ?? '') ?>" placeholder="Hero title">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="hero_description" rows="2" maxlength="500" placeholder="Hero description"><?= esc($hero['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Sections -->
    <div class="pp-row">
        <div class="pp-col" style="flex: 0 0 100%; max-width: 100%;">
            <h5 class="mb-3"><i class="bi bi-layers me-2"></i>Content Sections</h5>
            <div id="ppSections-<?= esc($tab) ?>">
                <?php foreach ($sections as $index => $section): ?>
                    <?php
                        $sectionId = $section['id'] ?? 'section_' . ($index + 1) . '_programs';
                        $sectionTitle = $section['title'] ?? '';
                        $sectionDesc = $section['description'] ?? '';
                        $mediaType = $section['media_type'] ?? 'image';
                        $mediaUrl = $section['media_url'] ?? '';
                    ?>
                    <div class="section-item" data-section-index="<?= $index ?>">
                        <input type="hidden" name="section_id[]" value="<?= esc($sectionId) ?>">
                        <input type="hidden" name="section_order[]" value="<?= $index + 1 ?>">
                        
                        <button type="button" class="btn btn-danger btn-sm delete-section-btn" onclick="ppDeleteSection(this)" title="Delete section">
                            <i class="bi bi-trash"></i>
                        </button>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Title</label>
                                <input type="text" class="form-control form-control-sm" name="section_title[]" value="<?= esc($sectionTitle) ?>" placeholder="Section title" maxlength="200">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small">Media Type</label>
                                <select class="form-select form-select-sm" name="section_media_type[]">
                                    <option value="image" <?= $mediaType === 'image' ? 'selected' : '' ?>>Image</option>
                                    <option value="video" <?= $mediaType === 'video' ? 'selected' : '' ?>>YouTube Video</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small">Order</label>
                                <input type="number" class="form-control form-control-sm" name="section_order_<?= $index ?>" value="<?= $index + 1 ?>" min="1" max="10">
                            </div>
                        </div>

                        <div class="mt-2">
                            <label class="form-label small">Upload Image</label>
                            <?php if ($mediaUrl !== ''): ?>
                                <img src="<?= esc(programs_projects_media_url($mediaUrl)) ?>" alt="Section media" class="img-fluid mb-2" style="max-height: 80px; max-width: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <input type="file" class="form-control form-control-sm" name="section_media_<?= $sectionId ?>" accept="image/jpeg,image/png,image/webp">
                            <input type="hidden" name="section_media_url[]" value="<?= esc($mediaUrl) ?>">
                        </div>

                        <div class="mt-2">
                            <label class="form-label small">Description</label>
                            <textarea class="form-control form-control-sm" name="section_description[]" rows="3" placeholder="Section description..." maxlength="1000"><?= esc($sectionDesc) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn btn-success btn-lg mt-3" onclick="ppAddSection('ppSections-<?= esc($tab) ?>', '<?= esc($tab) ?>')">
                <i class="bi bi-plus-lg me-2"></i>Add Section
            </button>
            <p class="text-muted small mt-2 mb-0">Click to add a new content section. Maximum 6 sections allowed.</p>
    </div>

    <!-- Tappy's message on this tab's public page -->
    <div class="pp-row">
        <div class="pp-col" style="flex: 0 0 100%; max-width: 100%;">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-chat-quote me-2"></i>Tappy's message on the public <?= esc($tabLabel) ?> page</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Tappy floats in the corner of the public
                        <a href="<?= base_url($tab) ?>" target="_blank" rel="noopener"><?= esc($tabLabel) ?></a>
                        page. Write what he should say there. Leave both boxes empty to go back to
                        the built-in wording.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Pose</label>
                            <select class="form-select form-select-sm" name="mascot_pose">
                                <?php foreach ($mascotPoses as $poseValue => $poseLabel): ?>
                                    <option value="<?= esc($poseValue) ?>" <?= $mascotPose === $poseValue ? 'selected' : '' ?>><?= esc($poseLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small">Heading</label>
                            <input type="text" class="form-control form-control-sm" name="mascot_title"
                                   value="<?= esc($mascot['title'] ?? '') ?>" maxlength="60"
                                   placeholder="<?= esc($mascot['defaults']['title'] ?? $tabLabel) ?>">
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
        </div>
    </div>

    <!-- Save Button -->
    <div class="pp-row">
        <div class="pp-col" style="flex: 0 0 100%; max-width: 100%;">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>Save <?= esc($tabLabel) ?> Page
            </button>
        </div>
    </div>
</form>

    </div>
<?php endforeach; ?>
</div>

<script>
// Section add/delete, scoped to a container id rather than a global one.
// The old version reached for a single document.getElementById('sectionsContainer')
// and a single 'addSectionBtn', which cannot work now that both tabs are on the
// page: the Projects button would have driven the Programs list.
function ppAddSection(containerId, tab) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const currentCount = container.querySelectorAll('.section-item').length;
    if (currentCount >= 6) {
        window.alert('Maximum 6 sections allowed.');
        return;
    }

    container.insertAdjacentHTML('beforeend', ppSectionHtml(currentCount, tab));
}

function ppSectionHtml(index, tab) {
    const n = index + 1;
    return `
        <div class="section-item" data-section-index="${n}">
            <input type="hidden" name="section_id[]" value="section_${n}_${tab}">
            <input type="hidden" name="section_order[]" value="${n}">

            <button type="button" class="btn btn-danger btn-sm delete-section-btn" onclick="ppDeleteSection(this)" title="Delete section">
                <i class="bi bi-trash"></i>
            </button>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Title</label>
                    <input type="text" class="form-control form-control-sm" name="section_title[]" placeholder="Section title" maxlength="200">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Media Type</label>
                    <select class="form-select form-select-sm" name="section_media_type[]">
                        <option value="image">Image</option>
                        <option value="video">YouTube Video</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Order</label>
                    <input type="number" class="form-control form-control-sm" name="section_order_${n}" value="${n}" min="1" max="10">
                </div>
            </div>

            <div class="mt-2">
                <label class="form-label small">Upload Image</label>
                <input type="file" class="form-control form-control-sm" name="section_media_section_${n}_${tab}" accept="image/jpeg,image/png,image/webp">
                <input type="hidden" name="section_media_url[]" value="">
            </div>

            <div class="mt-2">
                <label class="form-label small">Description</label>
                <textarea class="form-control form-control-sm" name="section_description[]" rows="3" placeholder="Section description..." maxlength="1000"></textarea>
            </div>
        </div>
    `;
}

async function ppDeleteSection(btn) {
    const ok = await customConfirm('Delete this section? It will be removed from the page after saving.', 'Delete Section');
    if (!ok) return;
    const sectionItem = btn.closest('.section-item');
    // Scoped to the section's own container, so deleting a Projects section
    // cannot renumber the Programs list beside it.
    const container = sectionItem.parentElement;
    sectionItem.remove();

    // Re-number the remaining sections so the order column stays 1..n.
    const items = container.querySelectorAll('.section-item');
    items.forEach((item, index) => {
        item.querySelector('input[name="section_order[]"]').value = index + 1;
        const orderInput = item.querySelector('input[type="number"]');
        if (orderInput) orderInput.value = index + 1;
    });
}

// --- Instant image preview for newly selected files (before saving) ---
document.querySelectorAll('input[type="file"][accept^="image"]').forEach(function (input) {
    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file || !/^image\//.test(file.type)) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const isHero = input.getAttribute('name') === 'hero_image';
            const container = isHero ? input.closest('.mb-3') : input.closest('.mt-2');
            if (!container) return;
            let img = container.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = 'Selected media preview';
                img.className = 'img-fluid mb-2';
                img.style.cssText = 'max-height: 200px; max-width: 100%; object-fit: cover;';
                container.insertBefore(img, input);
            }
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?= $this->endSection() ?>