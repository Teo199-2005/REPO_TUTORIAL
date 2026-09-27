/**
 * Image croppers with drag-to-reposition and zoom. Cropping happens in one
 * large stage inside the modal; the cropped result is written back into the
 * file input and the page thumbnail (circle for the profile picture, square
 * for the 2x2 ID picture). Requires Cropper.js (loaded from the jsdelivr CDN
 * in dashboard_layout.php) and Bootstrap 5 for the crop modal.
 *
 * Only the student profile page instantiates a cropper; other pages simply
 * never call StudentPhotoCrop.attach().
 */
(function (window, document) {
    'use strict';

    if (window.StudentPhotoCrop) {
        return;
    }

    /**
     * Replace a file input's value with a Blob produced by the cropper so
     * the normal multipart form submits the cropped bytes.
     */
    function setFileFromBlob(input, blob, fileName) {
        try {
            const dt = new DataTransfer();
            dt.items.add(new File([blob], fileName, { type: blob.type || 'image/jpeg' }));
            input.files = dt.files;
            // Tappy gives a thumbs-up once the photo is cropped and ready to
            // upload. Purely decorative: the wrapper hides itself if the
            // artwork has not been pasted in yet.
            var note = document.createElement('div');
            note.className = 'mascot-upload-done';
            var base = (function () {
                var a = document.createElement('a');
                a.href = window.location.href;
                return a.href.replace(/\/[^/]*$/, '') + '/assets/mascot/';
            })();
            note.innerHTML = '<span class="mascot mascot--thumbs-up">' +
                '<img src="' + base + 'thumbs-up.png" alt="" aria-hidden="true" ' +
                'width="96" height="96" loading="lazy" decoding="async" style="--mascot-size:96px">' +
                '</span>';
            (input.closest('form, .modal-body, body') || document.body).appendChild(note);
        } catch (err) {
            window.alert('Your browser cannot attach the cropped image. Please try a different browser.');
        }
    }

    /**
     * @param {object} options
     *   input     — <input type="file"> to enrich
     *   preview   — optional element for a live result (no longer used: the
 *               profile modal crops in one big stage instead)
     *   shape     — 'circle' | 'square'
     *   modal     — Bootstrap modal element wrapping the crop stage
     *   stage     — <img> Cropper.js binds to
     *   applyBtn  — confirm button inside the modal
     *   cancelBtn — dismiss button inside the modal
     *   dropZone  — optional element that opens the file picker on click
     */
    function attach(options) {
        const input = options.input;
        if (!input || input.dataset.cropBound === '1') {
            return;
        }
        input.dataset.cropBound = '1';

        if (typeof window.Cropper === 'undefined' || typeof window.bootstrap === 'undefined') {
            // Libraries missing — keep the plain file input working.
            return;
        }

        let cropper = null;
        let pendingCropper = false;
        let objectUrl = null;
        let pendingName = 'photo.jpg';

        const modal = new window.bootstrap.Modal(options.modal, {
            backdrop: 'static',
            keyboard: false,
        });

        function destroyCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        function renderPreview(dataUrl) {
            if (!options.preview) {
                return;
            }
            if (options.preview.tagName === 'IMG') {
                options.preview.src = dataUrl;
            } else {
                options.preview.style.backgroundImage = 'url("' + dataUrl + '")';
            }
        }

        function openFor(file) {
            if (!file || !/^image\//.test(file.type)) {
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                window.alert('Please choose an image 2MB or smaller.');
                input.value = '';
                return;
            }

            pendingName = file.name || (options.shape === 'circle' ? 'profile.jpg' : 'id.jpg');
            input.dataset.cropApplied = '0';
            destroyCropper();
            objectUrl = URL.createObjectURL(file);
            options.stage.src = objectUrl;

            // A Bootstrap modal is still display:none when .show() returns, so
            // Cropper built here would measure a 0x0 box and clamp its stage to
            // the 200x100 minimum — the reason the crop area looked tiny.
            // Build it once the dialog is laid out (shown.bs.modal listener in
            // attach() below, or immediately if the dialog is already open).
            pendingCropper = true;
            if (options.modal.classList.contains('show')) {
                pendingCropper = false;
                createCropper();
            } else {
                modal.show();
            }
        }

        /**
         * Create the Cropper instance. Only called while the modal is visible:
         * Cropper sizes its stage from this element's parent, and a hidden
         * parent measures 0x0 (Cropper then falls back to its 200x100 minimum).
         * Cropper handles the image load itself, so the image need not be
         * ready yet.
         */
        function createCropper() {
            const previewSelector = options.preview && options.preview.classList
                ? '.' + Array.from(options.preview.classList).join('.')
                : '';

            cropper = new window.Cropper(options.stage, {
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                movable: true,
                zoomable: true,
                rotatable: false,
                scalable: false,
                // Both shapes crop 1:1 (circular avatar, square 2x2 ID photo).
                aspectRatio: 1,
                preview: previewSelector,
            });
        }

        input.addEventListener('change', function () {
            openFor(input.files && input.files[0]);
        });

        if (options.dropZone) {
            options.dropZone.addEventListener('click', function () {
                input.click();
            });
        }

        options.applyBtn.addEventListener('click', function () {
            if (!cropper) {
                modal.hide();
                return;
            }
            const canvas = cropper.getCroppedCanvas({
                width: 800,
                height: 800,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
                fillColor: '#ffffff',
            });
            canvas.toBlob(function (blob) {
                if (!blob) {
                    return;
                }
                const ext = blob.type === 'image/png' ? '.png' : '.jpg';
                const base = pendingName.replace(/\.[^.]+$/, '');
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                setFileFromBlob(input, blob, base + '-cropped' + ext);
                renderPreview(dataUrl);
                if (options.pagePreview) {
                    if (options.pagePreview.tagName === 'IMG') {
                        options.pagePreview.src = dataUrl;
                    } else {
                        options.pagePreview.innerHTML = '';
                        options.pagePreview.style.backgroundImage = 'url("' + dataUrl + '")';
                        options.pagePreview.style.backgroundSize = 'cover';
                        options.pagePreview.style.backgroundPosition = 'center';
                    }
                }
                input.dataset.cropApplied = '1';
                destroyCropper();
                modal.hide();
            }, 'image/jpeg', 0.9);
        });

        options.cancelBtn.addEventListener('click', function () {
            modal.hide();
        });

        options.modal.addEventListener('shown.bs.modal', function () {
            // The dialog is laid out now, so Cropper measures the real stage
            // size instead of the 0x0 box of a hidden modal.
            if (pendingCropper && !cropper) {
                pendingCropper = false;
                createCropper();
            }
        });

        options.modal.addEventListener('hidden.bs.modal', function () {
            pendingCropper = false;
            const wasApplied = input.dataset.cropApplied === '1';
            delete input.dataset.cropApplied;
            if (!wasApplied) {
                // Closed without applying — discard the selection so the
                // form never submits an uncropped image.
                input.value = '';
            }
            destroyCropper();
        });
    }

    window.StudentPhotoCrop = { attach: attach, setFileFromBlob: setFileFromBlob };
})(window, document);
