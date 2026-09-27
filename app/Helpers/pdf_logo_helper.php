<?php

declare(strict_types=1);

if (! function_exists('school_logo_path_candidates')) {
    /**
     * @return list<string>
     */
    function school_logo_path_candidates(): array
    {
        $paths = [];

        if (defined('FCPATH')) {
            $paths[] = FCPATH . 'LPHS2.png';
            $paths[] = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'LPHS2.png';
        }

        if (defined('ROOTPATH')) {
            $paths[] = rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'LPHS2.png';
        }

        if (defined('APPPATH')) {
            $paths[] = dirname(rtrim(APPPATH, DIRECTORY_SEPARATOR . '/\\'))
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'LPHS2.png';
        }

        $seen = [];
        $out  = [];
        foreach ($paths as $p) {
            if ($p === '' || isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $out[]    = $p;
        }

        return $out;
    }
}

if (! function_exists('school_logo_base64')) {
    /**
     * PNG file contents as base64 for Dompdf data URIs (no GD required).
     * Supports standard CI4 (logo in public/) and flat Hostinger (public_html + public/).
     */
    function school_logo_base64(): string
    {
        foreach (school_logo_path_candidates() as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $binary = @file_get_contents($path);
            if ($binary !== false && $binary !== '') {
                return base64_encode($binary);
            }
        }

        return '';
    }
}

if (! function_exists('deped_seal_path_candidates')) {
    /**
     * Locate the DepEd official seal for the academic report card.
     *
     * The canonical asset is public/DepEd_Official_Seal.png (2194x2193, PNG with
     * a transparent background). DepEd_Official_Seal.jpg is kept as a legacy
     * fallback so an older deployment that has not re-uploaded the PNG still
     * renders a seal; the MIME type is taken from whichever file is found, so a
     * JPEG is never mislabelled as a PNG.
     *
     * Mirrors school_logo_path_candidates() so a standard CI4 deploy (asset in
     * public/) and a flat Hostinger deploy (public_html + public/) both resolve.
     *
     * @return list<string>
     */
    function deped_seal_path_candidates(): array
    {
        $roots = [];

        if (defined('FCPATH')) {
            $roots[] = FCPATH;
        }
        if (defined('ROOTPATH')) {
            $roots[] = rtrim(ROOTPATH, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . 'public';
        }
        if (defined('APPPATH')) {
            $roots[] = dirname(rtrim(APPPATH, DIRECTORY_SEPARATOR . '/\\')) . DIRECTORY_SEPARATOR . 'public';
        }

        $paths = [];
        $seen  = [];
        foreach ($roots as $root) {
            $root = rtrim($root, DIRECTORY_SEPARATOR . '/\\');
            if ($root === '') {
                continue;
            }
            // PNG first: that is the file the report card actually ships.
            foreach (['DepEd_Official_Seal.png', 'DepEd_Official_Seal.jpg'] as $file) {
                $path = $root . DIRECTORY_SEPARATOR . $file;
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $paths[]     = $path;
            }
        }

        return $paths;
    }
}

if (! function_exists('deped_seal_data_uri')) {
    /**
     * Ready-to-use data URI for <img src> inside a Dompdf document.
     *
     * The report card controllers run Dompdf with isRemoteEnabled = false, so
     * a real filesystem path or an http(s) URL renders as a broken image; only
     * inline data URIs survive. The MIME type follows the file that was
     * actually found, so a JPEG seal is not mislabelled as a PNG.
     */
    function deped_seal_data_uri(): string
    {
        foreach (deped_seal_path_candidates() as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $binary = @file_get_contents($path);
            if ($binary === false || $binary === '') {
                continue;
            }
            $info = @getimagesize($path);
            $mime = is_array($info) && ! empty($info['mime']) ? $info['mime'] : 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        return '';
    }
}
