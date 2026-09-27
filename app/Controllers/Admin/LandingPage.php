<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Files\UploadedFile;

class LandingPage extends BaseController
{
    public function index()
    {
        helper(['landing', 'asset', 'admin_access', 'mascot']);

        landing_migrate_legacy_hero_uploads();

        $records = landing_hero_slide_objects();
        $slides = [];

        foreach ($records as $i => $record) {
            $slides[$i + 1] = [
                'path'     => $record['path'],
                'url'      => featured_poster_url($record['path']),
                'position' => $record['position'],
                'scale'    => $record['scale'],
            ];
        }

        return view('admin/landing_page', [
            'title'              => 'Landing Page — CSCS Tap n Track',
            'slides'             => $slides,
            'announcementStrip'  => landing_announcement_strip_text(),
            'previewSlides'      => landing_hero_slides_for_view(),
            // Tappy's message on the public front page. Keyed "home" because the
            // front page has no path segment; see the note in update().
            'mascot'             => $this->mascotState(),
            'mascotPoses'        => mascot_pose_choices(),
        ]);
    }

    /**
     * Tappy's message for the public front page: the administrator's own wording
     * when they have set any, otherwise the built-in line plus a note that it is
     * the default.
     *
     * @return array{pose: string, title: string, text: string, isCustom: bool, defaults: array{title: string, text: string, pose: string}}
     */
    private function mascotState(): array
    {
        $custom  = mascot_line_override_get('home');
        $builtin = mascot_line_for('home');

        return [
            'pose'     => $custom['pose'] ?? $builtin['pose'],
            'title'    => $custom['title'] ?? '',
            'text'     => $custom['text'] ?? '',
            'isCustom' => $custom !== null,
            'defaults' => [
                'title' => $builtin['title'],
                'text'  => $builtin['text'],
                'pose'  => $builtin['pose'],
            ],
        ];
    }

    public function update()
    {
        helper(['landing', 'admin_access', 'mascot']);

        landing_migrate_legacy_hero_uploads();

        if (! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        // Early detect if the POST/request was truncated due to php.ini limits
        // (e.g. upload_max_filesize or post_max_size). If so, abort and do not
        // modify saved slides to avoid clearing existing uploads.
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $postMax = ini_get('post_max_size');
        // parse shorthand byte value (K/M/G)
        $mult = 1;
        $last = strtolower(substr($postMax, -1));
        $num = (int) $postMax;
        if ($last === 'g') $mult = 1024 * 1024 * 1024;
        elseif ($last === 'm') $mult = 1024 * 1024;
        elseif ($last === 'k') $mult = 1024;
        $postMaxBytes = $num * $mult;
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            return redirect()->back()->with('error', 'Upload failed: total request size exceeds server limit (post_max_size = ' . ini_get('post_max_size') . '). No changes were made.');
        }

        $existing = landing_hero_slide_objects();
        $slots    = [
            1 => $existing[0] ?? null,
            2 => $existing[1] ?? null,
            3 => $existing[2] ?? null,
        ];
        $uploadDir = landing_hero_upload_dir();

        for ($slot = 1; $slot <= 3; $slot++) {
            if ($this->request->getPost('remove_slide_' . $slot) === '1') {
                $slots[$slot] = null;
                continue;
            }

            $position = landing_normalize_slide_position((string) $this->request->getPost('hero_slide_' . $slot . '_position') ?? '50% 50%');
            $scale = landing_normalize_slide_scale($this->request->getPost('hero_slide_' . $slot . '_scale') ?? 1);

            $file = $this->request->getFile('hero_slide_' . $slot);
            if ($file === null || $file->getName() === '') {
                if (isset($slots[$slot]['path']) && is_string($slots[$slot]['path'])) {
                    $slots[$slot] = [
                        'path'     => $slots[$slot]['path'],
                        'position' => $position,
                        'scale'    => $scale,
                    ];
                }
                continue;
            }

            $validation = $this->validateHeroUpload($file);
            if ($validation !== true) {
                return redirect()->back()->with('error', 'Slide ' . $slot . ': ' . $validation);
            }

            $newName = $file->getRandomName();
            try {
                $file->move($uploadDir, $newName);
            } catch (\Throwable $e) {
                log_message('error', 'Landing hero upload failed: ' . $e->getMessage());

                return redirect()->back()->with('error', 'Could not save slide ' . $slot . '. Check permissions on public/uploads/landing/.');
            }

            $slots[$slot] = [
                'path'     => 'uploads/landing/' . $newName,
                'position' => $position,
                'scale'    => $scale,
            ];
        }

        $cleanSlides = [];
        foreach ([1, 2, 3] as $slot) {
            if (isset($slots[$slot]['path']) && is_string($slots[$slot]['path']) && $slots[$slot]['path'] !== '') {
                $cleanSlides[] = $slots[$slot];
            }
        }

        landing_save_hero_slides($cleanSlides);

        $stripText = trim((string) $this->request->getPost('announcement_strip'));
        $stripText = preg_replace('/\s+/u', ' ', $stripText) ?? '';
        if (strlen($stripText) > 500) {
            $stripText = mb_substr($stripText, 0, 500);
        }

        $model = new \App\Models\SystemSettingModel();
        $model->setSetting(
            landing_strip_setting_key(),
            $stripText,
            'Landing page announcement ticker (principal message)'
        );

        // Lifelines: optional fields set by admin
        $lifelines = [
            'water' => strtoupper(trim((string) $this->request->getPost('lifeline_water') ?? 'FUNCTIONAL')),
            'communication' => strtoupper(trim((string) $this->request->getPost('lifeline_communication') ?? 'FUNCTIONAL')),
            'electricity' => strtoupper(trim((string) $this->request->getPost('lifeline_electricity') ?? 'FUNCTIONAL')),
        ];
        // Use helper to save
        try {
            helper('landing');
            landing_save_lifelines($lifelines);
        } catch (\Throwable $e) {
            // non-fatal; proceed
            log_message('warning', 'Could not save landing lifelines: ' . $e->getMessage());
        }

        // --- Tappy's message on the public front page ---
        // The front page runs on the root URL, which has no path segment at all -
        // mascot_segment_key() returns '' there and mascot_dock.php normalises it
        // to "home". So "home" is the key, and that is what the built-in line for
        // the landing page is looked up under too.
        $mascotTitle = trim((string) $this->request->getPost('mascot_title'));
        $mascotText  = trim((string) $this->request->getPost('mascot_text'));
        $mascotPose  = trim((string) $this->request->getPost('mascot_pose'));

        if ($mascotTitle !== '' && mb_strlen($mascotTitle) > 60) {
            $mascotTitle = mb_substr($mascotTitle, 0, 60);
        }
        if ($mascotText !== '' && mb_strlen($mascotText) > 240) {
            $mascotText = mb_substr($mascotText, 0, 240);
        }

        // An unknown pose is ignored rather than stored, so a stale form (or a
        // hand-made POST) cannot leave the front page with a missing character.
        if ($mascotPose !== '' && ! array_key_exists($mascotPose, mascot_pose_choices())) {
            $mascotPose = '';
        }

        mascot_line_override_save('home', [
            'pose'  => $mascotPose,
            'title' => $mascotTitle,
            'text'  => $mascotText,
        ]);

        audit_event('settings.landing_page_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'setting',
            'resource_id'   => 'landing_page',
            'description'   => 'Public landing page content updated',
            'metadata'      => [
                // Recorded so the activity log shows whether Tappy's message was
                // changed, which is otherwise invisible in a diff of the page copy.
                'mascot_message_custom' => $mascotTitle !== '' || $mascotText !== '',
            ],
        ]);

        return redirect()->back()->with('success', 'Landing page updated successfully.');
    }

    /**
     * @return true|string
     */
    private function validateHeroUpload(UploadedFile $file)
    {
        if (! $file->isValid()) {
            $error = $file->getErrorString();

            return $error !== '' ? $error : 'Upload failed. The file may be too large (max 50MB).';
        }

        if ($file->getSize() > 50 * 1024 * 1024) {
            return 'Image must be 50MB or less.';
        }

        $ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'Image must be JPG, PNG, or WEBP.';
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/pjpeg', 'image/x-png'];
        if ($mime !== '' && ! in_array($mime, $allowedMime, true) && ! str_starts_with($mime, 'image/')) {
            return 'File must be an image (JPG, PNG, or WEBP).';
        }

        return true;
    }
}
