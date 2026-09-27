<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="h3 mb-2">CHILDPRO / GAD Management</h1>
    <p class="text-muted mb-0">Manage content for the public CHILDPRO and GAD pages — up to 6 content sections each with image/video, title, and description.</p>
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

<style>
.cg-row { display: flex; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1.5rem; }
.cg-col { flex: 0 0 calc(50% - 0.625rem); max-width: calc(50% - 0.625rem); }
.cg-card { height: 100%; display: flex; flex-direction: column; }
.cg-card .card-body { flex: 1 1 auto; overflow: auto; }
@media (max-width: 767px) {
    .cg-col { flex: 0 0 100%; max-width: 100%; }
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

<!-- Tab navigation -->
<nav class="nav nav-tabs mb-4" id="cgAdminTabs" role="tablist">
    <button class="nav-link active" id="tab-childpro-tab" data-bs-toggle="tab" data-bs-target="#tab-childpro" type="button" role="tab" aria-controls="tab-childpro" aria-selected="true">
        <i class="bi bi-people me-1"></i>CHILDPRO
    </button>
    <button class="nav-link" id="tab-gad-tab" data-bs-toggle="tab" data-bs-target="#tab-gad" type="button" role="tab" aria-controls="tab-gad" aria-selected="false">
        <i class="bi bi-gender-ambiguous me-1"></i>GAD
    </button>
</nav>

<div class="tab-content" id="cgAdminTabContent">
    <!-- CHILDPRO Tab -->
    <div class="tab-pane fade show active" id="tab-childpro" role="tabpanel" aria-labelledby="tab-childpro-tab">
        <?php
            $activeTab = 'childpro';
            $tabData = $tabs[$activeTab] ?? [];
            $hero = $tabData['hero'] ?? [];
            $sections = $tabData['sections'] ?? [];
            $heroUrl = $tabData['heroUrl'] ?? '';
            $heroPosition = $hero['position'] ?? '50% 50%';
            $heroScale = (float) ($hero['scale'] ?? 1.0);
        ?>

        <form method="post" action="<?= base_url('admin/childpro-gad/update') ?>" enctype="multipart/form-data" id="childproForm">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="<?= $activeTab ?>">

            <!-- Hero Section -->
            <div class="cg-row">
                <div class="cg-col">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-image me-2"></i>Hero Section</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($heroUrl !== ''): ?>
                                <?php $heroExt = strtolower(pathinfo(parse_url($heroUrl, PHP_URL_PATH), PATHINFO_EXTENSION)); ?>
                                <?php if (in_array($heroExt, ['mp4', 'webm', 'ogg', 'mov'])): ?>
                                    <video controls class="w-100 mb-2" style="max-height: 200px; background: #000;">
                                        <source src="<?= esc($heroUrl) ?>" type="video/<?= esc($heroExt) ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php else: ?>
                                    <img src="<?= esc($heroUrl) ?>" alt="Hero" class="img-fluid w-100 mb-2" style="max-height: 200px; object-fit: cover;">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="bg-light border rounded mb-2" style="height: 120px; display: flex; align-items: center; justify-content: center; color: #999;">No media uploaded</div>
                            <?php endif; ?>
                            <input type="file" class="form-control form-control-sm mb-2 hero-file-input" name="hero_image" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg,video/quicktime">
                            <small class="text-muted">Max 300MB. Images: JPG, PNG, WebP. Videos: MP4, WebM, OGG, MOV</small>
                            <div class="hero-upload-progress" style="display: none;">
                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Uploading... <span class="upload-time-estimate"></span></small>
                            </div>
                            <input type="hidden" name="hero_position" value="<?= esc($heroPosition) ?>">
                            <input type="hidden" name="hero_scale" value="<?= esc(number_format($heroScale, 2, '.', '')) ?>">
                        </div>
                    </div>
                </div>

                <div class="cg-col">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Hero Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hero Title</label>
                                <input type="text" class="form-control" name="hero_title" value="<?= esc($hero['title'] ?? '') ?>" maxlength="200" placeholder="e.g. Child Protection Program">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hero Description</label>
                                <textarea class="form-control" name="hero_description" rows="4" maxlength="500" placeholder="A brief description..."><?= esc($hero['description'] ?? '') ?></textarea>
                                <small class="text-muted">Max 500 characters.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Sections -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <h5 class="mb-3"><i class="bi bi-layers me-2"></i>Content Sections</h5>
                    <div id="childproSectionsContainer">
                        <?php foreach ($sections as $index => $section): ?>
                            <?php
                                $sectionId = $section['id'] ?? 'section_' . ($index + 1) . '_childpro';
                                $sectionTitle = $section['title'] ?? '';
                                $sectionDesc = $section['description'] ?? '';
                                $mediaType = $section['media_type'] ?? 'image';
                                $mediaUrl = $section['media_url'] ?? '';
                            ?>
                            <div class="section-item" data-section-index="<?= $index ?>">
                                <input type="hidden" name="section_id[]" value="<?= esc($sectionId) ?>">
                                <input type="hidden" name="section_order[]" value="<?= $index + 1 ?>">
                                
                                <button type="button" class="btn btn-danger btn-sm delete-section-btn" onclick="deleteSection(this)" title="Delete section">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <div class="row g-2">
                                    <div class="col-md-8">
                                        <label class="form-label small">Title</label>
                                        <input type="text" class="form-control form-control-sm" name="section_title[]" value="<?= esc($sectionTitle) ?>" maxlength="200">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small">Order</label>
                                        <input type="number" class="form-control form-control-sm" name="section_order[]" value="<?= $index + 1 ?>" min="1" max="10">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small">Upload Media</label>
                                    <?php if ($mediaUrl !== ''): ?>
                                        <img src="<?= esc(childpro_gad_media_url($mediaUrl)) ?>" alt="Section media" class="img-fluid mb-2" style="max-height: 80px; max-width: 200px; object-fit: cover;">
                                    <?php endif; ?>
                                    <input type="file" class="form-control form-control-sm" name="section_media_<?= $sectionId ?>" accept="image/jpeg,image/png,image/webp,video/mp4">
                                    <input type="hidden" name="section_media_url[]" value="<?= esc($mediaUrl) ?>">
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small">Description</label>
                                    <textarea class="form-control form-control-sm" name="section_description[]" rows="3" maxlength="1000"><?= esc($sectionDesc) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-success btn-lg mt-3" onclick="addSection('childproSectionsContainer', 'childpro')">
                        <i class="bi bi-plus-lg me-2"></i>Add Section
                    </button>
                    <p class="text-muted small mt-2 mb-0">Click to add a new content section. Maximum 6 sections allowed.</p>
                </div>
            </div>

            <!-- Tappy's message on the public page -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-quote me-2"></i>Tappy's message on the public CHILDPRO page</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                $mascot     = $tabData['mascot'] ?? ['pose' => 'hero', 'title' => '', 'text' => '', 'isCustom' => false, 'defaults' => ['title' => '', 'text' => '', 'pose' => 'hero']];
                                $mascotPose = $mascot['pose'] ?? 'hero';
                            ?>
                            <p class="text-muted small">
                                Tappy floats in the corner of the public
                                <a href="<?= base_url('childpro') ?>" target="_blank" rel="noopener">CHILDPRO</a>
                                page. Write what he should say there. Leave both boxes empty to go back to
                                the built-in wording.
                            </p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Pose</label>
                                    <select class="form-select form-select-sm" name="mascot_pose">
                                        <?php foreach (($mascotPoses ?? ['hero' => 'Waving (default)']) as $poseValue => $poseLabel): ?>
                                            <option value="<?= esc($poseValue) ?>" <?= $mascotPose === $poseValue ? 'selected' : '' ?>><?= esc($poseLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small">Heading</label>
                                    <input type="text" class="form-control form-control-sm" name="mascot_title"
                                           value="<?= esc($mascot['title'] ?? '') ?>" maxlength="60"
                                           placeholder="<?= esc($mascot['defaults']['title'] ?? 'CHILDPRO') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Message</label>
                                    <textarea class="form-control form-control-sm" name="mascot_text" rows="2" maxlength="240"
                                              placeholder="<?= esc($mascot['defaults']['text'] ?? '') ?>"></textarea>
                                    <div class="form-text">
                                        Currently using:
                                        <strong><?= esc($mascot['defaults']['text'] ?? '') ?></strong>
                                        <?php if ($mascot['isCustom'] ?? false): ?>
                                            — this box will replace it. Clear both boxes to restore the default.
                                        <?php else: ?>
                                            — the built-in wording. Anything you type here overrides it.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save CHILDPRO Page
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- GAD Tab -->
    <div class="tab-pane fade" id="tab-gad" role="tabpanel" aria-labelledby="tab-gad-tab">
        <?php
            $activeTab = 'gad';
            $tabData = $tabs[$activeTab] ?? [];
            $hero = $tabData['hero'] ?? [];
            $sections = $tabData['sections'] ?? [];
            $heroUrl = $tabData['heroUrl'] ?? '';
            $heroPosition = $hero['position'] ?? '50% 50%';
            $heroScale = (float) ($hero['scale'] ?? 1.0);
        ?>

        <form method="post" action="<?= base_url('admin/childpro-gad/update') ?>" enctype="multipart/form-data" id="gadForm">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="<?= $activeTab ?>">

            <!-- Hero Section -->
            <div class="cg-row">
                <div class="cg-col">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-image me-2"></i>Hero Section</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($heroUrl !== ''): ?>
                                <?php $heroExt = strtolower(pathinfo(parse_url($heroUrl, PHP_URL_PATH), PATHINFO_EXTENSION)); ?>
                                <?php if (in_array($heroExt, ['mp4', 'webm', 'ogg', 'mov'])): ?>
                                    <video controls class="w-100 mb-2" style="max-height: 200px; background: #000;">
                                        <source src="<?= esc($heroUrl) ?>" type="video/<?= esc($heroExt) ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php else: ?>
                                    <img src="<?= esc($heroUrl) ?>" alt="Hero" class="img-fluid w-100 mb-2" style="max-height: 200px; object-fit: cover;">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="bg-light border rounded mb-2" style="height: 120px; display: flex; align-items: center; justify-content: center; color: #999;">No media uploaded</div>
                            <?php endif; ?>
                            <input type="file" class="form-control form-control-sm mb-2 hero-file-input" name="hero_image" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/ogg,video/quicktime">
                            <small class="text-muted">Max 300MB. Images: JPG, PNG, WebP. Videos: MP4, WebM, OGG, MOV</small>
                            <div class="hero-upload-progress" style="display: none;">
                                <div class="progress mb-1" style="height: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">Uploading... <span class="upload-time-estimate"></span></small>
                            </div>
                            <input type="hidden" name="hero_position" value="<?= esc($heroPosition) ?>">
                            <input type="hidden" name="hero_scale" value="<?= esc(number_format($heroScale, 2, '.', '')) ?>">
                        </div>
                    </div>
                </div>

                <div class="cg-col">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Hero Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hero Title</label>
                                <input type="text" class="form-control" name="hero_title" value="<?= esc($hero['title'] ?? '') ?>" maxlength="200" placeholder="e.g. GAD Program">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hero Description</label>
                                <textarea class="form-control" name="hero_description" rows="4" maxlength="500" placeholder="A brief description..."><?= esc($hero['description'] ?? '') ?></textarea>
                                <small class="text-muted">Max 500 characters.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Sections -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <h5 class="mb-3"><i class="bi bi-layers me-2"></i>Content Sections</h5>
                    <div id="gadSectionsContainer">
                        <?php foreach ($sections as $index => $section): ?>
                            <?php
                                $sectionId = $section['id'] ?? 'section_' . ($index + 1) . '_gad';
                                $sectionTitle = $section['title'] ?? '';
                                $sectionDesc = $section['description'] ?? '';
                                $mediaType = $section['media_type'] ?? 'image';
                                $mediaUrl = $section['media_url'] ?? '';
                            ?>
                            <div class="section-item" data-section-index="<?= $index ?>">
                                <input type="hidden" name="section_id[]" value="<?= esc($sectionId) ?>">
                                <input type="hidden" name="section_order[]" value="<?= $index + 1 ?>">
                                
                                <button type="button" class="btn btn-danger btn-sm delete-section-btn" onclick="deleteSection(this)" title="Delete section">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <div class="row g-2">
                                    <div class="col-md-8">
                                        <label class="form-label small">Title</label>
                                        <input type="text" class="form-control form-control-sm" name="section_title[]" value="<?= esc($sectionTitle) ?>" maxlength="200">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small">Order</label>
                                        <input type="number" class="form-control form-control-sm" name="section_order[]" value="<?= $index + 1 ?>" min="1" max="10">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small">Upload Media</label>
                                    <?php if ($mediaUrl !== ''): ?>
                                        <img src="<?= esc(childpro_gad_media_url($mediaUrl)) ?>" alt="Section media" class="img-fluid mb-2" style="max-height: 80px; max-width: 200px; object-fit: cover;">
                                    <?php endif; ?>
                                    <input type="file" class="form-control form-control-sm" name="section_media_<?= $sectionId ?>" accept="image/jpeg,image/png,image/webp,video/mp4">
                                    <input type="hidden" name="section_media_url[]" value="<?= esc($mediaUrl) ?>">
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small">Description</label>
                                    <textarea class="form-control form-control-sm" name="section_description[]" rows="3" maxlength="1000"><?= esc($sectionDesc) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-success btn-lg mt-3" onclick="addSection('gadSectionsContainer', 'gad')">
                        <i class="bi bi-plus-lg me-2"></i>Add Section
                    </button>
                    <p class="text-muted small mt-2 mb-0">Click to add a new content section. Maximum 6 sections allowed.</p>
                </div>
            </div>

            <!-- Tappy's message on the public page -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <div class="card border-0 shadow-sm cg-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-quote me-2"></i>Tappy's message on the public GAD page</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                $mascot     = $tabData['mascot'] ?? ['pose' => 'hero', 'title' => '', 'text' => '', 'isCustom' => false, 'defaults' => ['title' => '', 'text' => '', 'pose' => 'hero']];
                                $mascotPose = $mascot['pose'] ?? 'hero';
                            ?>
                            <p class="text-muted small">
                                Tappy floats in the corner of the public
                                <a href="<?= base_url('gad') ?>" target="_blank" rel="noopener">GAD</a>
                                page. Write what he should say there. Leave both boxes empty to go back to
                                the built-in wording.
                            </p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Pose</label>
                                    <select class="form-select form-select-sm" name="mascot_pose">
                                        <?php foreach (($mascotPoses ?? ['hero' => 'Waving (default)']) as $poseValue => $poseLabel): ?>
                                            <option value="<?= esc($poseValue) ?>" <?= $mascotPose === $poseValue ? 'selected' : '' ?>><?= esc($poseLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small">Heading</label>
                                    <input type="text" class="form-control form-control-sm" name="mascot_title"
                                           value="<?= esc($mascot['title'] ?? '') ?>" maxlength="60"
                                           placeholder="<?= esc($mascot['defaults']['title'] ?? 'GAD') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Message</label>
                                    <textarea class="form-control form-control-sm" name="mascot_text" rows="2" maxlength="240"
                                              placeholder="<?= esc($mascot['defaults']['text'] ?? '') ?>"></textarea>
                                    <div class="form-text">
                                        Currently using:
                                        <strong><?= esc($mascot['defaults']['text'] ?? '') ?></strong>
                                        <?php if ($mascot['isCustom'] ?? false): ?>
                                            — this box will replace it. Clear both boxes to restore the default.
                                        <?php else: ?>
                                            — the built-in wording. Anything you type here overrides it.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="cg-row">
                <div class="cg-col" style="flex: 0 0 100%; max-width: 100%;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save GAD Page
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Upload Size Warning Modal -->
<div class="modal fade" id="uploadSizeModal" tabindex="-1" aria-labelledby="uploadSizeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="uploadSizeModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>File Size Too Large
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>The selected file exceeds the maximum allowed size.</strong></p>
                <p>Maximum allowed size: <strong>300MB</strong></p>
                <p>Please choose a smaller file or compress your video before uploading.</p>
                <hr>
                <p class="mb-0"><strong>Tips to reduce file size:</strong></p>
                <ul>
                    <li>Use video compression tools (HandBrake, FFmpeg)</li>
                    <li>Lower the video resolution (1080p → 720p)</li>
                    <li>Reduce video bitrate</li>
                    <li>Upload to YouTube and use the YouTube link instead</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let childproSectionCounter = <?= count($sections) ?>;
let gadSectionCounter = <?= count($sections) ?>;

function addSection(containerId, type) {
    const container = document.getElementById(containerId);
    const currentCount = container.querySelectorAll('.section-item').length;
    
    if (currentCount >= 6) {
        alert('Maximum 6 sections allowed.');
        return;
    }

    let counter, suffix;
    if (type === 'childpro') {
        childproSectionCounter++;
        counter = childproSectionCounter;
        suffix = 'childpro';
    } else {
        gadSectionCounter++;
        counter = gadSectionCounter;
        suffix = 'gad';
    }

    const sectionHtml = `
        <div class="section-item" data-section-index="${counter}">
            <input type="hidden" name="section_id[]" value="section_${counter}_${suffix}">
            <input type="hidden" name="section_order[]" value="${currentCount + 1}">
            
            <button type="button" class="btn btn-danger btn-sm delete-section-btn" onclick="deleteSection(this)" title="Delete section">
                <i class="bi bi-trash"></i>
            </button>

                                <div class="row g-2">
                                    <div class="col-md-8">
                                        <label class="form-label small">Title</label>
                                        <input type="text" class="form-control form-control-sm" name="section_title[]" maxlength="200">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small">Order</label>
                                        <input type="number" class="form-control form-control-sm" name="section_order[]" value="${currentCount + 1}" min="1" max="10">
                                    </div>
                                </div>

            <div class="mt-2">
                <label class="form-label small">Upload Media</label>
                <input type="file" class="form-control form-control-sm" name="section_media_${counter}_${suffix}" accept="image/jpeg,image/png,image/webp,video/mp4">
                <input type="hidden" name="section_media_url[]" value="">
            </div>

            <div class="mt-2">
                <label class="form-label small">Description</label>
                <textarea class="form-control form-control-sm" name="section_description[]" rows="3" maxlength="1000"></textarea>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', sectionHtml);
}

async function deleteSection(btn) {
    const ok = await customConfirm('Delete this section? It will be removed from the page after saving.', 'Delete Section');
    if (!ok) return;
    const sectionItem = btn.closest('.section-item');
    sectionItem.remove();
    
    const container = sectionItem.parentElement;
    const items = container.querySelectorAll('.section-item');
    items.forEach((item, index) => {
        item.querySelector('input[name="section_order[]"]').value = index + 1;
        const orderInput = item.querySelector('input[type="number"]');
        if (orderInput) orderInput.value = index + 1;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const MAX_SIZE_MB = 300;
    const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
    const AVG_UPLOAD_SPEED_MBPS = 5;

    function formatFileSize(bytes) {
        if (bytes >= 1024 * 1024 * 1024) {
            return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
        }
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function calculateUploadTime(fileSizeBytes) {
        const fileSizeMB = fileSizeBytes / (1024 * 1024);
        const uploadSpeedMBps = AVG_UPLOAD_SPEED_MBPS / 8;
        const uploadTimeSeconds = fileSizeMB / uploadSpeedMBps;
        
        if (uploadTimeSeconds < 60) {
            return Math.ceil(uploadTimeSeconds) + ' seconds';
        } else if (uploadTimeSeconds < 3600) {
            return Math.ceil(uploadTimeSeconds / 60) + ' minutes';
        } else {
            const minutes = Math.floor(uploadTimeSeconds / 60);
            const seconds = Math.ceil(uploadTimeSeconds % 60);
            return minutes + ' min ' + seconds + ' sec';
        }
    }

    document.querySelectorAll('.hero-file-input').forEach(function(input) {
        const progressDiv = input.closest('.card-body').querySelector('.hero-upload-progress');
        const form = input.closest('form');
        const sizeModal = new bootstrap.Modal(document.getElementById('uploadSizeModal'));
        
        if (progressDiv && form) {
            input.addEventListener('change', function() {
                if (input.files && input.files.length > 0) {
                    const file = input.files[0];
                    const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    
                    console.log('Selected file: ' + file.name + ' (' + formatFileSize(file.size) + ')');
                    
                    if (file.size > MAX_SIZE_BYTES) {
                        sizeModal.show();
                        input.value = '';
                        progressDiv.style.display = 'none';
                        return false;
                    }
                    
                    const uploadTime = calculateUploadTime(file.size);
                    const timeEstimateSpan = progressDiv.querySelector('.upload-time-estimate');
                    if (timeEstimateSpan) {
                        timeEstimateSpan.textContent = '(~' + uploadTime + ' at ' + AVG_UPLOAD_SPEED_MBPS + ' Mbps)';
                    }
                    
                    progressDiv.style.display = 'block';
                    const progressBar = progressDiv.querySelector('.progress-bar');
                    if (progressBar) {
                        progressBar.style.width = '30%';
                    }
                } else {
                    progressDiv.style.display = 'none';
                    const progressBar = progressDiv.querySelector('.progress-bar');
                    if (progressBar) {
                        progressBar.style.width = '0%';
                    }
                }
            });

            form.addEventListener('submit', function(e) {
                const input = form.querySelector('.hero-file-input');
                if (input && input.files && input.files.length > 0) {
                    progressDiv.style.display = 'block';
                    const progressBar = progressDiv.querySelector('.progress-bar');
                    const timeEstimateSpan = progressDiv.querySelector('.upload-time-estimate');
                    if (progressBar) {
                        progressBar.style.width = '50%';
                    }
                    if (timeEstimateSpan) {
                        timeEstimateSpan.textContent = '(uploading, please wait...)';
                    }
                }
            });
        }
    });

    // --- Instant image preview for newly selected files (before saving) ---
    document.querySelectorAll('input[type="file"][accept^="image"]').forEach(function (input) {
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file || !/^image\//.test(file.type)) return; // video files are previewed after saving
            const reader = new FileReader();
            reader.onload = function (e) {
                const isHero = input.classList.contains('hero-file-input');
                const container = isHero ? input.closest('.card-body') : input.closest('.mt-2');
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
});
</script>
<?= $this->endSection() ?>