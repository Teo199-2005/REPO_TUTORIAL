<?php

declare(strict_types=1);

if (! function_exists('asset_url')) {
    /**
     * Public asset URL on the current host (avoids broken CSS when app.baseURL
     * in .env does not match the URL you are actually visiting).
     */
    function asset_url(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! is_cli() && ! empty($_SERVER['HTTP_HOST'])) {
            $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

            $scheme = $https ? 'https' : 'http';
            $host   = (string) $_SERVER['HTTP_HOST'];

            $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $basePath   = rtrim(dirname($scriptName), '/');
            if ($basePath === '/' || $basePath === '.') {
                $basePath = '';
            }

            if (defined('FCPATH')) {
                $relative = str_replace('/', DIRECTORY_SEPARATOR, $path);
                $publicFile = FCPATH . 'public' . DIRECTORY_SEPARATOR . $relative;

                if (! is_file(FCPATH . $relative) && is_file($publicFile) && $basePath !== '/public') {
                    $basePath = '/public' . $basePath;
                }
            }

            return $scheme . '://' . $host . $basePath . '/' . $path;
        }

        return function_exists('base_url') ? base_url($path) : '/' . $path;
    }
}

if (! function_exists('asset_file_path')) {
    /**
     * Resolve a public asset path on disk (Hostinger flat layout or public/ subfolder).
     */
    function asset_file_path(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $relative = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $candidates = [];

        if (defined('FCPATH')) {
            $candidates[] = FCPATH . $relative;
            $candidates[] = FCPATH . 'public' . DIRECTORY_SEPARATOR . $relative;
        }

        if (defined('ROOTPATH')) {
            $candidates[] = rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . $relative;
        }

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}

if (! function_exists('hero_banner_url')) {
    /**
     * Hero banner image — checks public/ and flat Hostinger layouts.
     */
    function hero_banner_url(): string
    {
        $relative = 'assets/images/backgrounds/herosouth.png';
        $candidates = [];

        if (defined('FCPATH')) {
            $candidates[] = FCPATH . $relative;
            $candidates[] = FCPATH . 'public' . DIRECTORY_SEPARATOR . $relative;
        }

        if (defined('ROOTPATH')) {
            $candidates[] = rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . $relative;
        }

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return asset_url($relative);
            }
        }

        return asset_url($relative);
    }
}

if (! function_exists('about_poster_url')) {
    /**
     * About section Tap n Track poster (public/poster2.png).
     */
    function about_poster_url(): string
    {
        $paths = [
            'poster2.png',
            'assets/images/poster2.png',
        ];

        $fileCandidates = [];
        foreach ($paths as $relative) {
            $relative = ltrim(str_replace('\\', '/', $relative), '/');

            if (defined('FCPATH')) {
                $fileCandidates[] = ['file' => FCPATH . $relative, 'url' => $relative];
                $fileCandidates[] = [
                    'file' => FCPATH . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative),
                    'url' => $relative,
                ];
            }

            if (defined('ROOTPATH')) {
                $fileCandidates[] = [
                    'file' => rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\')
                        . DIRECTORY_SEPARATOR . 'public'
                        . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative),
                    'url' => $relative,
                ];
            }
        }

        foreach ($fileCandidates as $entry) {
            if (is_file($entry['file'])) {
                return asset_url($entry['url']);
            }
        }

        return asset_url('poster2.png');
    }
}

if (! function_exists('school_logo_url')) {
    /**
     * School logo (LPHS2.png) for headers, favicon, and share previews.
     */
    function school_logo_url(): string
    {
        if (function_exists('school_logo_path_candidates')) {
            foreach (school_logo_path_candidates() as $path) {
                if (is_file($path)) {
                    return asset_url('LPHS2.png');
                }
            }
        }

        return asset_url('LPHS2.png');
    }
}

if (! function_exists('featured_poster_disk_path')) {
    /**
     * Absolute path for a stored featured-poster relative path (supports legacy locations).
     */
    function featured_poster_disk_path(string $relativePath): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relative === '') {
            return null;
        }

        $candidates = [];
        if (defined('FCPATH')) {
            $candidates[] = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $candidates[] = FCPATH . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            // Legacy mistaken upload path (public/public/uploads/…)
            $candidates[] = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        if (defined('ROOTPATH')) {
            $candidates[] = rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}

if (! function_exists('featured_poster_url')) {
    /** Public URL for a featured dashboard poster stored in system_settings. */
    function featured_poster_url(?string $storedPath): string
    {
        if ($storedPath === null || trim($storedPath) === '') {
            return '';
        }

        $relative = ltrim(str_replace('\\', '/', $storedPath), '/');

        if (featured_poster_disk_path($relative) !== null) {
            return asset_url($relative);
        }

        // DB has a path but PHP could not stat the file (permissions / legacy folder) — still try the public URL
        if (preg_match('#^uploads/(featured|landing)/[a-zA-Z0-9._-]+$#', $relative) === 1) {
            return asset_url($relative);
        }

        return '';
    }
}

if (! function_exists('featured_dashboard_poster')) {
    /**
     * Load featured poster path + URL for teacher or student dashboards.
     *
     * @return array{path: string, url: string}
     */
    function featured_dashboard_poster(string $role): array
    {
        $keys = [
            'teacher' => 'featured_poster_teacher',
            'student' => 'featured_poster_student',
        ];

        $key = $keys[$role] ?? null;
        if ($key === null) {
            return ['path' => '', 'url' => ''];
        }

        try {
            $model = new \App\Models\SystemSettingModel();
            $path  = (string) ($model->getSetting($key, '') ?? '');

            return [
                'path' => $path,
                'url'  => featured_poster_url($path),
            ];
        } catch (\Throwable $e) {
            return ['path' => '', 'url' => ''];
        }
    }
}

if (! function_exists('school_logo_absolute_url')) {
    /**
     * Absolute logo URL (required for Facebook / Open Graph og:image).
     */
    function school_logo_absolute_url(): string
    {
        $url = school_logo_url();

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (function_exists('asset_url')) {
            return asset_url('LPHS2.png');
        }

        return $url;
    }
}
