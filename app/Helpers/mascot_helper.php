<?php

declare(strict_types=1);

/**
 * CSCS Tap n Track mascot ("Tappy").
 *
 * The mascot images are AI-generated artwork, not part of the repository, so
 * they are pasted in later by hand. Every helper here therefore treats a
 * missing image as a normal state rather than an error: mascot_img() emits a
 * wrapper that hides itself if the file is absent, so a page with no artwork
 * yet still lays out correctly and simply shows the text.
 *
 * The prompts and the exact file names are documented next to the images in
 * public/assets/mascot/PROMPTS.md — that file is the contract between the
 * artwork and this code.
 */

if (! function_exists('mascot_asset_dir')) {
    /**
     * Folder (relative to /public) holding the artwork.
     */
    function mascot_asset_dir(): string
    {
        return 'assets/mascot';
    }
}

if (! function_exists('mascot_file_name')) {
    /**
     * Normalise a caller-supplied name to a safe .png file name.
     *
     * Guards against path traversal and against a name that would not match
     * the generated file (e.g. "point right" -> "point-right").
     */
    function mascot_file_name(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? '' : $slug . '.png';
    }
}

if (! function_exists('mascot_url')) {
    /**
     * Public URL for one mascot image.
     *
     * @param string $name File name without extension, e.g. 'point-right'.
     */
    function mascot_url(string $name): string
    {
        $file = mascot_file_name($name);

        return $file === '' ? '' : asset_url(mascot_asset_dir() . '/' . $file);
    }
}

if (! function_exists('mascot_exists')) {
    /**
     * True when the artwork has actually been pasted into public/.
     *
     * Used to skip work (and to keep the "Take a tour" button honest) while the
     * image set is still incomplete.
     */
    function mascot_exists(string $name): bool
    {
        $file = mascot_file_name($name);

        if ($file === '') {
            return false;
        }

        // asset_file_path() already knows the deploy layouts (standard CI4 and
        // flat Hostinger), so reuse it rather than guessing a path here.
        return asset_file_path(mascot_asset_dir() . '/' . $file) !== null;
    }
}

if (! function_exists('mascot_dimensions')) {
    /**
     * Pixel dimensions of one mascot image, read straight from the PNG header.
     *
     * Why this matters: the poses are not square. Most are 2:3 portrait (hero is
     * closer to 1:2.3) and sleeping.png is landscape, so a fixed square wrapper
     * letterboxes them - the character ends up using barely half the width it is
     * given, and the landscape pose can never fill more than 60% of the box
     * height. Sizing the wrapper from the real ratio is what makes the larger
     * placements actually look large.
     *
     * Cached per request because a page can ask for the same pose more than once
     * and the header is only 24 bytes to read.
     *
     * @return array{width:int,height:int}|null null when the file is missing or
     *                                                  is not a PNG
     */
    function mascot_dimensions(string $name): ?array
    {
        $file = mascot_file_name($name);
        if ($file === '') {
            return null;
        }

        $path = asset_file_path(mascot_asset_dir() . '/' . $file);
        if ($path === null) {
            return null;
        }

        static $cache = [];

        if (array_key_exists($file, $cache)) {
            return $cache[$file];
        }

        // 8-byte signature, then the IHDR chunk header, then width and height as
        // two big-endian uint32s.
        $head = @file_get_contents($path, false, null, 0, 24);
        if (! is_string($head) || strlen($head) < 24 || substr($head, 12, 4) !== 'IHDR') {
            return $cache[$file] = null;
        }

        $size = unpack('Nwidth/Nheight', substr($head, 16, 8));
        if ($size === false || $size['width'] < 1 || $size['height'] < 1) {
            return $cache[$file] = null;
        }

        return $cache[$file] = ['width' => $size['width'], 'height' => $size['height']];
    }
}

if (! function_exists('mascot_role_key')) {
    /**
     * Coarse role key for the current user, used to key once-only state.
     *
     * admin_staff is folded into 'admin' on purpose: both sign in to the admin
     * dashboard and both get the same welcome copy, and admin_staff is only a
     * page-restriction variant of it. admin_staff is tested first because those
     * accounts can sit in more than one group.
     */
    function mascot_role_key(): string
    {
        try {
            $user = auth()->user();

            if ($user === null) {
                return 'guest';
            }

            foreach (['student', 'teacher', 'parent', 'admin_staff', 'admin'] as $group) {
                if ($user->inGroup($group)) {
                    return $group === 'admin_staff' ? 'admin' : $group;
                }
            }
        } catch (\Throwable $e) {
            // A half-loaded auth service must never break a page render.
        }

        return 'staff';
    }
}

if (! function_exists('mascot_role_label')) {
    /**
     * Human label for a role key, e.g. "this is the Admin dashboard".
     */
    function mascot_role_label(?string $key = null): string
    {
        $labels = [
            'admin'   => 'Admin',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent'  => 'Parent',
            'staff'   => 'Staff',
            'guest'   => 'Guest',
        ];

        $key = $key ?? mascot_role_key();

        return $labels[$key] ?? 'User';
    }
}


if (! function_exists('mascot_img')) {
    /**
     * Render one mascot image, or nothing at all if the file is not there yet.
     *
     * The wrapper carries a known class so mascot.css can size and position it,
     * and the <img> hides itself on error. That is the whole point: the site is
     * fully functional before any artwork is pasted in, and each image can be
     * added later without touching code.
     *
     * @param array{name:string,alt?:string,class?:string,size?:int,loading?:string,decoration?:string} $options
     *   name        required, file name without extension ('point-right')
     *   alt         accessible text; defaults to '' (decorative)
     *   class       extra classes on the wrapper
     *   size        CSS pixel size for the wrapper (default 120). A CSS custom
     *               property such as 'var(--mascot-dock)' is also accepted, for
     *               the placements that have to shrink on a phone - a responsive
     *               media query cannot override an inline pixel value.
     *   loading     'lazy' (default) or 'eager' for above-the-fold art
     *   decoration  ring/badge treatment: 'none' (default), 'ring', 'circle'
     *   reveal      opt in to the scroll-reveal animation (see mascot.js)
     */
    function mascot_img(array $options): string
    {
        $name = (string) ($options['name'] ?? '');

        if ($name === '') {
            return '';
        }

        $file = mascot_file_name($name);
        if ($file === '') {
            return '';
        }

        $rawSize = $options['size'] ?? null;
        // A CSS custom property is passed straight through so a responsive media
        // query can still shrink the artwork; $size stays a number for the
        // width/height hints below.
        $sizeDecl = is_string($rawSize) && str_starts_with(trim($rawSize), 'var(')
            ? trim($rawSize)
            : null;
        $size   = $sizeDecl === null ? max(24, (int) ($rawSize ?? 120)) : 220;
        $alt    = (string) ($options['alt'] ?? '');
        $extra  = trim((string) ($options['class'] ?? ''));
        $load   = ($options['loading'] ?? 'lazy') === 'eager' ? 'eager' : 'lazy';
        $deco   = (string) ($options['decoration'] ?? 'none');
        $deco   = in_array($deco, ['none', 'ring', 'circle'], true) ? $deco : 'none';

        // Size the wrapper from the artwork's real proportions. --mascot-size
        // stays the box's *long* edge and the ratio decides the other axis, so a
        // portrait pose gets its full height instead of being letterboxed inside a
        // square, and the landscape pose finally fills the width it is given. The
        // width/height attributes follow the same ratio, which is what stops the
        // artwork shifting the layout around while it loads.
        $dims   = mascot_dimensions($name);
        $ratio  = 1.0;
        $attrW  = $size;
        $attrH  = $size;

        if ($dims !== null) {
            $ratio = $dims['width'] / $dims['height'];

            if ($ratio >= 1) {
                $attrW = $size;
                $attrH = max(1, (int) round($size / $ratio));
            } else {
                $attrH = $size;
                $attrW = max(1, (int) round($size * $ratio));
            }
        }

        $classes = 'mascot mascot--' . str_replace('.png', '', $file);
        if ($extra !== '') {
            $classes .= ' ' . $extra;
        }
        if ($deco !== 'none') {
            $classes .= ' mascot--' . $deco;
        }
        // Opt in to the scroll reveal. Without JavaScript the class is never
        // paired with .mascot--visible, and the reduced-motion block resets the
        // opacity, so the artwork can never be left invisible.
        if (! empty($options['reveal'])) {
            $classes .= ' mascot--reveal';
        }

        // Decorative art must not be announced twice; meaningful art gets the
        // alt text and is announced normally.
        $altAttr = $alt === '' ? ' alt="" aria-hidden="true"' : ' alt="' . esc($alt) . '"';

        // No explicit size means no inline --mascot-size at all, so the placement's
        // own CSS class decides how big the artwork is. Emitting the 120px default
        // here would beat .mascot-sticker and .mascot-chip, and every sticker
        // rendered without a size came out enormous.
        $style = '--mascot-ratio:' . round($ratio, 4);

        if ($sizeDecl !== null) {
            $style = '--mascot-size:' . $sizeDecl . ';' . $style;
        } elseif (array_key_exists('size', $options)) {
            $style = '--mascot-size:' . $size . 'px;' . $style;
        }

        return '<span class="' . esc($classes) . '" style="' . esc($style) . '">'
            . '<img src="' . esc(asset_url(mascot_asset_dir() . '/' . $file)) . '"'
            . ' width="' . $attrW . '" height="' . $attrH . '"'
            . ' loading="' . $load . '" decoding="async"' . $altAttr . '>'
            . '<noscript><span class="mascot-noscript">'
            . '<img src="' . esc(asset_url(mascot_asset_dir() . '/' . $file)) . '"'
            . ' width="' . $attrW . '" height="' . $attrH . '"' . $altAttr . '>'
            . '</span></noscript>'
            . '</span>';
    }
}

if (! function_exists('mascot_sticker')) {
    /**
     * A flat badge sticker (no character), placed beside headings and cards.
     *
     * @param array{name:string,alt?:string,class?:string,size?:int,loading?:string} $options
     */
    function mascot_sticker(array $options): string
    {
        $options['class'] = trim('mascot-sticker ' . (string) ($options['class'] ?? ''));

        return mascot_img($options);
    }
}

if (! function_exists('mascot_sticker_for')) {
    /**
     * A sticker chosen by what it means, not by which file it is.
     *
     * A view asks for 'warn' or 'top', never for 'sticker-alert.png'. That keeps
     * the artwork swappable - if the alert badge is regenerated as a different
     * shape, one edit here fixes every screen that uses it.
     *
     * Like every other mascot helper this returns an empty string when the file is
     * not installed, so a page with no stickers still lays out correctly.
     *
     * @param array{alt?:string,class?:string,size?:int|string,loading?:string} $options
     */
    function mascot_sticker_for(string $key, array $options = []): string
    {
        $stickers = [
            'new'      => 'sticker-new',
            'tip'      => 'sticker-tip',
            'done'     => 'sticker-done',
            'award'    => 'sticker-star',
            'top'      => 'sticker-grade-a',
            'locked'   => 'sticker-locked',
            'warn'     => 'sticker-alert',
            'wow'      => 'sticker-wow',
            'saved'    => 'sticker-saved',
            'upload'   => 'sticker-upload',
            'question' => 'sticker-question',
            'error'    => 'sticker-error',
            'pending'  => 'sticker-pending',
            'settings' => 'sticker-settings',
            'audit'    => 'sticker-audit',
            'backup'   => 'sticker-backup',
        ];

        $key = strtolower(trim($key));

        if (! isset($stickers[$key])) {
            return '';
        }

        $options['name'] = $stickers[$key];
        // Stickers sit next to text that already says what they mean, so they are
        // decorative by default. A caller that wants them announced passes 'alt'.
        $options['alt'] = (string) ($options['alt'] ?? '');

        return mascot_sticker($options);
    }
}

if (! function_exists('mascot_dialog_art')) {
    /**
     * The right Tappy pose for a dialog, chosen by what the dialog is for.
     *
     * This exists so no view has to decide that a "check your email" dialog gets
     * an envelope rather than a shrug. The modal partials each had their own idea,
     * and the poses needed were already generated - what was missing was one place
     * that maps intent to artwork.
     *
     * Returns '' for an unknown kind and for a pose whose file is not installed,
     * so a dialog is never left with a broken image.
     *
     * @param array{alt?:string,class?:string,size?:int|string,loading?:string} $options
     */
    function mascot_dialog_art(string $kind, array $options = []): string
    {
        $poses = [
            'captcha'   => 'search',
            'charter'   => 'reading',
            'materials' => 'laptop',
            'closed'    => 'worried',
            'confirm'   => 'thinking',
            'logout'    => 'thinking',
            'thankyou'  => 'celebrate',
            'unlock'    => 'thumbs-up',
            'success'   => 'thumbs-up',
            'alert'     => 'worried',
            'mail'      => 'envelope',
        ];

        $key  = strtolower(trim($kind));
        $pose = $poses[$key] ?? null;

        if ($pose === null || ! mascot_exists($pose)) {
            return '';
        }

        $options['name'] = $pose;
        $options['alt']  = (string) ($options['alt'] ?? '');

        return mascot_img($options);
    }
}

if (! function_exists('mascot_logo_for')) {
    /**
     * An icon-scale Tappy mark, by role.
     *
     * 'mark' is full colour for on-screen use. 'mono' is a single flat #1e40af
     * with no shading, for print, PDF and ID cards where the artwork lands on
     * white paper and colour cannot be relied on.
     *
     * @param array{alt?:string,class?:string,size?:int|string,loading?:string} $options
     */
    function mascot_logo_for(string $key = 'mark', array $options = []): string
    {
        $marks = [
            'mark' => 'logo-mark',
            'mono' => 'logo-mark-mono',
        ];

        $key = strtolower(trim($key));

        // An unknown key renders nothing rather than falling back to the colour
        // mark, so a typo in a view shows a missing image rather than the wrong
        // brand mark. The default parameter above is what supplies 'mark' when
        // the caller says nothing at all.
        if (! isset($marks[$key])) {
            return '';
        }

        $name = $marks[$key];

        if (! mascot_exists($name)) {
            return '';
        }

        $options['name'] = $name;
        $options['alt']  = (string) ($options['alt'] ?? '');

        return mascot_img($options);
    }
}

if (! function_exists('mascot_poster_for')) {
    /**
     * An ultrawide composition, by purpose.
     *
     * The character is confined to one third and the rest of the frame is
     * deliberately empty, because that space is filled with live HTML text. Do not
     * put a headline in the artwork: the view has to own it, or it cannot be
     * translated, resized or read by a screen reader.
     *
     * @param array{alt?:string,class?:string,size?:int|string,loading?:string} $options
     */
    function mascot_poster_for(string $key, array $options = []): string
    {
        $posters = [
            'enroll'   => 'poster-enroll',
            'about'    => 'poster-about',
            'announce' => 'poster-announce',
            'support'  => 'poster-support',

            // The four "Using the Portal" slots. Allowlisted here and in
            // tools/mascot_assets.php; a file present on disk but missing from
            // both is reported as UNDOCUMENTED.
            'portal-login'     => 'poster-portal-login',
            'portal-grades'    => 'poster-portal-grades',
            'portal-schedules' => 'poster-portal-schedules',
            'portal-updates'   => 'poster-portal-updates',
        ];

        $key  = strtolower(trim($key));
        $name = $posters[$key] ?? null;

        if ($name === null || ! mascot_exists($name)) {
            return '';
        }

        $options['name'] = $name;
        $options['alt']  = (string) ($options['alt'] ?? '');

        return mascot_img($options);
    }
}

if (! function_exists('mascot_band_url')) {
    /**
     * URL of the wide section-divider band, or '' when it is not installed.
     *
     * A URL rather than mascot_img() because the band is a background behind the
     * divider rule, not a picture in the flow.
     */
    function mascot_band_url(string $name = 'band'): string
    {
        // A bare slug, never a file name. mascot_exists() and mascot_url() both
        // run it through mascot_file_name(), which appends '.png' after turning
        // every non-alphanumeric character into a dash - so handing them
        // 'divider-band.png' would look for 'divider-band-png.png'.
        $name = 'divider-' . $name;

        return mascot_exists($name) ? mascot_url($name) : '';
    }
}

if (! function_exists('mascot_pattern_url')) {
    /**
     * URL of a seamless tiling pattern, or '' when it is not installed.
     *
     * Used as a CSS background-image. The tile is expected to be seamless, with
     * every motif fully inside the canvas, and tools/mascot_optimize.php never
     * crops a pattern-* file: cropping a tile leaves a hard-edged motif that
     * repeats across the page as a visible box.
     */
    function mascot_pattern_url(string $name = 'doodle'): string
    {
        // Bare slug for the same reason as mascot_band_url().
        $name = 'pattern-' . $name;

        return mascot_exists($name) ? mascot_url($name) : '';
    }
}

if (! function_exists('mascot_band_divider')) {
    /**
     * The wide section-divider band, or '' when it is not installed.
     *
     * Rendered *above* the existing gradient rule rather than replacing it, so a
     * site with no band artwork is byte-for-byte unchanged. That is deliberate:
     * this is a progressive enhancement, and a missing decorative file must not
     * cost a section its divider.
     *
     * Decorative by definition, so it is never announced.
     */
    function mascot_band_divider(): string
    {
        $url = mascot_band_url();

        if ($url === '') {
            return '';
        }

        // A CSS custom property rather than background-image shorthand, so the
        // URL is escaped exactly once and the styling lives in the stylesheet.
        return '<div class="mascot-band-divider" style="--mascot-band-image:url(\''
            . esc($url) . '\')" aria-hidden="true"></div>';
    }
}

if (! function_exists('mascot_poster_banner')) {
    /**
     * A full-width poster banner: art on its own row, never text over it.
     *
     * The posters are full-frame illustrations - measured opacity is 54-82% in
     * every horizontal third, so there is no clear area to set type in. A headline
     * laid over one of these would be unreadable, so the banner is stacked
     * instead: callers place their own heading, body and buttons above or below
     * this block and keep the art clear.
     *
     * Returns '' when the poster is not installed.
     *
     * @param array{alt?:string,class?:string,loading?:string} $options
     */
    function mascot_poster_banner(string $key, array $options = []): string
    {
        $art = mascot_poster_for($key, [
            'alt'     => (string) ($options['alt'] ?? ''),
            'class'   => 'mascot-poster-art',
            'loading' => (string) ($options['loading'] ?? 'lazy'),
        ]);

        if ($art === '') {
            return '';
        }

        $extra = trim((string) ($options['class'] ?? ''));

        return '<div class="mascot-poster-banner' . ($extra === '' ? '' : ' ' . esc($extra)) . '">'
            . $art
            . '</div>';
    }
}

if (! function_exists('mascot_name')) {
    /**
     * The mascot's display name, used in tooltips and tour copy.
     */
    function mascot_name(): string
    {
        return 'Tappy';
    }
}

if (! function_exists('mascot_tour_played')) {
    /**
     * True when the guided tour has already been completed for this role.
     *
     * Read by the view to decide whether to show the permanent replay button.
     */
    function mascot_tour_played(): bool
    {
        try {
            $key = 'mascot.tour.v1.' . (string) (auth()->user()?->inGroup('student') ? 'student' : 'staff');

            return session()->get($key) === 'done';
        } catch (\Throwable $e) {
            // Never let a session hiccup break a dashboard.
            return false;
        }
    }
}

if (! function_exists('mascot_user_hidden')) {
    /**
     * True when the signed-in person has chosen to send Tappy away.
     *
     * This is the server-side half of the hide/restore toggle. The browser keeps
     * its own copy in localStorage so the dock can be correct before the first
     * request completes, but localStorage is scoped to the machine: it would
     * follow whoever signs in next on a shared computer and vanish on another
     * device. Reading the account's own flag is what makes the choice survive a
     * logout and a fresh login, which is what "hide Tappy" has to mean.
     *
     * Guests and signed-out visitors have no account to store anything against,
     * so they keep the old localStorage behaviour and get no server round trip.
     * Any failure - no auth service, a database that has not been migrated yet -
     * resolves to "show Tappy", so the worst case is a mascot someone meant to
     * dismiss, never a page that cannot be rendered.
     */
    function mascot_user_hidden(): bool
    {
        try {
            if (! auth()->loggedIn()) {
                return false;
            }

            $user = auth()->user();
            if ($user === null) {
                return false;
            }

            $row  = is_array($user) ? ($user['mascot_hidden'] ?? 0) : ($user->mascot_hidden ?? 0);

            return (int) $row === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('mascot_user_visibility_url')) {
    /**
     * Where the dock posts a hide/restore choice, or '' when there is nowhere
     * to post it.
     *
     * Empty for guests: they have no account, so the dock falls back to its
     * localStorage copy and skips the request entirely rather than firing one
     * that is guaranteed to be rejected.
     */
    function mascot_user_visibility_url(): string
    {
        try {
            if (! auth()->loggedIn()) {
                return '';
            }
        } catch (\Throwable $e) {
            return '';
        }

        return base_url('api/mascot/visibility');
    }
}

if (! function_exists('mascot_user_scope_key')) {
    /**
     * Stable per-account suffix for the browser-side copy of the hide choice.
     *
     * The localStorage mirror is a convenience, not the source of truth - the
     * account is. But the mirror still has to be scoped per person: a browser
     * scoped to a single account leaks the choice to the next person who signs
     * in on the same machine, which is the exact failure this feature was built
     * to remove. Keying by user id means signing in as somebody else simply
     * starts from their own value instead of inheriting the last one's.
     *
     * Empty string for guests, who have no account to key against and fall back
     * to the plain, shared key.
     */
    function mascot_user_scope_key(): string
    {
        try {
            if (! auth()->loggedIn()) {
                return '';
            }

            $user = auth()->user();
            if ($user === null) {
                return '';
            }

            $id = is_array($user) ? ($user['id'] ?? '') : ($user->id ?? '');

            return $id === '' || $id === null ? '' : 'u' . (int) $id;
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('mascot_segment_key')) {
    /**
     * "admin/students" style key for the page currently being rendered.
     *
     * Used to pick Tappy's pose and his line, and to key the once-per-session
     * dock state. Falls back to the first segment when there is no second one.
     */
    function mascot_segment_key(): string
    {
        try {
            $uri    = service('uri');
            $first  = trim((string) $uri->getSegment(1), '/');
            $second = trim((string) $uri->getSegment(2), '/');

            if ($first === '') {
                return '';
            }

            return $second === '' ? $first : $first . '/' . $second;
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('mascot_base_path')) {
    /**
     * The URL path the app is mounted on, e.g. '' or '/portal'.
     *
     * App::$baseURL is auto-detected when it is not set, so the site can be moved
     * under a subdirectory without a code change. Anything in JavaScript that
     * compares window.location.pathname against a route therefore has to remove
     * this prefix first - otherwise a site under /portal matches nothing at all.
     *
     * Only the path, never the host, and always without a trailing slash.
     */
    function mascot_base_path(): string
    {
        try {
            $path = (string) parse_url(base_url(), PHP_URL_PATH);
        } catch (\Throwable $e) {
            $path = '';
        }

        return trim($path ?? '', '/');
    }
}

if (! function_exists('mascot_pose_choices')) {
    /**
     * The poses an administrator may pick from when writing a custom message.
     *
     * Only files that actually exist are offered. Listing a pose whose artwork is
     * missing would let an admin save a choice that renders as an empty bubble,
     * and the page would look broken with no obvious cause.
     *
     * Stickers are excluded on purpose: they are punctuation for a sentence, not
     * a character with something to say.
     *
     * @return array<string, string> pose => label
     */
    function mascot_pose_choices(): array
    {
        $poses = [
            'hero'        => 'Waving (default)',
            'bust'        => 'Head and shoulders',
            'point-right' => 'Pointing right',
            'point-down'  => 'Pointing down',
            'point-up'    => 'Pointing up',
            'thumbs-up'   => 'Thumbs up',
            'reading'     => 'Reading',
            'laptop'      => 'At a laptop',
            'chart'       => 'With a chart',
            'search'      => 'Searching',
            'star'        => 'With a star',
            'celebrate'   => 'Celebrating',
            'thinking'    => 'Thinking',
            'worried'     => 'Worried',
            'tap-card'    => 'Holding an ID card',
            'sleeping'    => 'Sleeping',
            'megaphone'   => 'With a megaphone',
            'envelope'    => 'With an envelope',
            'medal'       => 'With a medal',
        ];

        $available = [];
        foreach ($poses as $pose => $label) {
            if (mascot_exists($pose)) {
                $available[$pose] = $label;
            }
        }

        // Never hand back an empty list: the select would render blank and the
        // admin would have no way to restore a working pose.
        return $available !== [] ? $available : ['hero' => 'Waving (default)'];
    }
}

if (! function_exists('mascot_line_override_key')) {
    /**
     * Setting key holding an administrator's own wording for one page.
     *
     * The segment is sanitised because it comes from the URL and ends up in a
     * settings key: without this a crafted path could write rows the admin never
     * intended. Only lowercase letters, digits, dash and slash survive.
     */
    function mascot_line_override_key(string $segment): string
    {
        $clean = strtolower(trim($segment));
        $clean = preg_replace('/[^a-z0-9\-\/]+/', '-', $clean) ?? '';

        // A segment made only of separators ("///") survives the filter above but
        // carries no page identity at all, and would store a junk key like
        // "mascot_line____". Requiring one real word character keeps the key
        // meaningful and stops meaningless rows being written.
        if (preg_match('/[a-z0-9]/', $clean) !== 1) {
            return '';
        }

        $clean = trim($clean, '-/');

        if ($clean === '') {
            return '';
        }

        return 'mascot_line_' . str_replace('/', '_', $clean);
    }
}

if (! function_exists('mascot_line_override_get')) {
    /**
     * An administrator's custom wording for this page, or null when they have not
     * written one.
     *
     * This is what makes Tappy's message on the public CHILDPRO and GAD pages
     * editable: the school decides what the page is currently promoting, and that
     * changes every year (a new theme, a new activity drive), so it cannot live
     * in the code as a fixed string.
     *
     * Any failure - no settings table yet, unparsable JSON, a pose that no longer
     * exists - returns null so the caller quietly falls back to the built-in
     * line. A missing custom message must never leave the dock with nothing to say.
     *
     * @return array{pose: string, title: string, text: string}|null
     */
    function mascot_line_override_get(string $segment): ?array
    {
        $key = mascot_line_override_key($segment);

        if ($key === '') {
            return null;
        }

        try {
            $model = new \App\Models\SystemSettingModel();
            $raw   = (string) ($model->getSetting($key, '') ?? '');
        } catch (\Throwable $e) {
            return null;
        }

        if ($raw === '') {
            return null;
        }

        return mascot_line_override_normalise(json_decode($raw, true));
    }
}

if (! function_exists('mascot_line_override_normalise')) {
    /**
     * Decide whether a stored payload is usable, and clean it up.
     *
     * Split out from the database read on purpose: this is where every judgement
     * call about an administrator's wording lives (is it worth showing at all, is
     * the pose real, is the heading missing), and keeping it a pure function means
     * those rules can be tested directly instead of needing a settings table.
     *
     * @param mixed $decoded the value from json_decode(), any shape at all
     *
     * @return array{pose: string, title: string, text: string}|null
     */
    function mascot_line_override_normalise($decoded): ?array
    {
        // Anything that is not an object - a bare string, a list, null, corrupt
        // JSON - is treated as "not set" so the built-in line shows.
        if (! is_array($decoded)) {
            return null;
        }

        $title = is_string($decoded['title'] ?? null) ? trim($decoded['title']) : '';
        $text  = is_string($decoded['text'] ?? null) ? trim($decoded['text']) : '';

        // Nothing worth overriding: an empty bubble helps nobody, so fall back.
        if ($title === '' && $text === '') {
            return null;
        }

        $pose = is_string($decoded['pose'] ?? null) ? trim($decoded['pose']) : '';

        return [
            // An unknown pose is dropped rather than rendered. The check has to be
            // mascot_exists(), not mascot_file_name(): the latter only builds a
            // filename, so it happily returns "whatever.png" for a pose that was
            // never drawn, and the dock would show a broken image.
            'pose'  => $pose !== '' && mascot_exists($pose) ? $pose : 'hero',
            'title' => $title !== '' ? $title : 'Tap n Track',
            'text'  => $text,
        ];
    }
}

if (! function_exists('mascot_line_override_save')) {
    /**
     * Store (or clear) an administrator's wording for one page.
     *
     * Blank fields clear the override rather than storing an empty message, so
     * "Reset to the default wording" is just saving empty values and there is no
     * second, separately-guarded way for the two states to disagree.
     *
     * @param array{pose?: string, title?: string, text?: string} $data
     */
    function mascot_line_override_save(string $segment, array $data): void
    {
        $key = mascot_line_override_key($segment);

        if ($key === '') {
            return;
        }

        $clean = [
            'pose'  => is_string($data['pose'] ?? null) ? trim($data['pose']) : '',
            'title' => is_string($data['title'] ?? null) ? trim($data['title']) : '',
            'text'  => is_string($data['text'] ?? null) ? trim($data['text']) : '',
        ];

        $model = new \App\Models\SystemSettingModel();

        if ($clean['title'] === '' && $clean['text'] === '') {
            $model->deleteSetting($key);

            return;
        }

        $model->setSetting(
            $key,
            json_encode($clean, JSON_UNESCAPED_SLASHES),
            "Tappy's custom message for: " . $segment
        );
    }
}

if (! function_exists('mascot_line_for')) {
    /**
     * The pose and the line Tappy uses on a given page.
     *
     * This is the map that stops him being the same silent pointing hand on every
     * screen. Longest prefix wins, so 'admin/students' beats 'admin', and anything
     * unmapped falls back to a per-area line rather than to nothing - a slightly
     * off line is fine, an empty speech bubble is not.
     *
     * Copy is deliberately generic: it describes the page, not live data. Live
     * counts belong in the view that already has them, not here where they would
     * be wrong for most roles most of the time.
     *
     * @return array{pose:string,title:string,text:string}
     */
    function mascot_line_for(?string $key = null): array
    {
        $key = $key ?? mascot_segment_key();

        // An administrator's own wording wins over the built-in line. Checked
        // first and only for an exact segment, so a custom message on /gad never
        // leaks onto every other page through the longest-prefix matching below.
        $custom = mascot_line_override_get($key);
        if ($custom !== null) {
            return $custom;
        }

        /* ===== The pose vocabulary =====

           One meaning per pose, so a new page cannot be answered with whatever
           character happens to be nearby. The whole point of these entries is that
           Tappy is doing a different job on different pages, and that stops being
           true the moment half of them are the same shrug.

             hero       the front door - the page visitors land on
             bust       a person: you, or someone you manage
             search     looking something up
             chart      numbers, trends, anything measured
             reading    a document you read rather than fill in
             laptop     routine system work: forms, uploads, settings
             star       something being shown off, or rated
             medal      merit: marks, achievements, approval
             envelope   mail arrives here: messages, reset links
             worried    something needs a human
             celebrate  an achievement
             megaphone  broadcasting to other people
             tap-card   identity issued or checked
             thumbs-up  consent, confirmation, done
             thinking   "not sure?" - help and recovery pages
             point-up   look up, something arrived
             point-down a list sitting below
             sleeping   nothing here (shared empty states only)
             point-right  POINTING AT THE MENU. Nothing else. It used to be the
                           catch-all fallback, which is why it was on every
                           unmapped page at once.

           Every pose named here must exist as a PNG in public/assets/mascot/.
           tools/mascot_assets.php and MascotTest::testPromptsDocumentEveryPoseTheCodeReferences()
           are what stop a rename from shipping a broken image. */
        $lines = [
            // ---- Admin: Overview -------------------------------------------
            'admin/dashboard'     => ['pose' => 'chart',       'title' => 'Dashboard',       'text' => 'This is the pulse of the school. Every count here updates as you record it.'],

            // ---- Admin: Enrollment ------------------------------------------
            'admin/students'      => ['pose' => 'search',      'title' => 'Students',        'text' => 'Search, filter and add students here. Give me a name and I will help you find them. Enrolments waiting on you are under Pending.'],
            'admin/teachers'      => ['pose' => 'bust',        'title' => 'Teachers',        'text' => 'Your teaching personnel: add them, edit their details and assign them to sections. Applications from the public sign-up form wait under Pending Teacher.'],

            // ---- Admin: Academics -------------------------------------------
            'admin/sections'      => ['pose' => 'point-down',  'title' => 'Sections & Subjects', 'text' => 'Sections group students by grade level, and subjects sit underneath them. Pick one to see the class list, the teachers and the subjects assigned.'],
            'admin/schedule'      => ['pose' => 'laptop',      'title' => 'Schedules',       'text' => 'Build the class schedule for each section. Anyone who changes a slot here sees it straight away in their own portal.'],
            'admin/analytics'     => ['pose' => 'chart',       'title' => 'Analytics',       'text' => 'Trends and comparisons across the whole school, with a PDF export when you need to show someone the numbers.'],
            'admin/grades'        => ['pose' => 'medal',       'title' => 'Grades',          'text' => 'Enter and review grades by quarter. This is the marks of record, so every change here follows a learner all the way to their report card.'],
            'admin/student-nutrition' => ['pose' => 'laptop',   'title' => 'Nutrition / BMI', 'text' => 'Weigh-ins, BMI and nutrition notes for every learner, with an export for the school records.'],
            'admin/subjects'      => ['pose' => 'reading',     'title' => 'Subjects',        'text' => 'Subjects and their badges are managed here. A grade cannot be entered for a subject that does not exist on this list.'],

            // ---- Admin: Communication --------------------------------------
            'admin/announcements' => ['pose' => 'megaphone',   'title' => 'Announcements',   'text' => 'Post announcements for everyone to read. You can target a section, and check who has actually opened it.'],
            'admin/materials'     => ['pose' => 'laptop',      'title' => 'Materials',       'text' => 'Upload and organise the learning materials. They are filed by subject so they stay findable as the library grows.'],

            // ---- Admin: Administration -------------------------------------
            'admin/password-resets' => ['pose' => 'worried',   'title' => 'Password resets', 'text' => 'People who cannot get in ask for a reset here. Approve the request and send them a one-time link.'],
            'admin/records'       => ['pose' => 'search',      'title' => 'Records',         'text' => 'Every learner on file: report cards, learner development reports and archived enrolments. Open one section at a time to see what is inside.'],
            'admin/id-cards'      => ['pose' => 'tap-card',    'title' => 'ID cards',        'text' => 'Print and issue the student ID cards.'],
            'admin/audit-log'     => ['pose' => 'reading',     'title' => 'Activity log',    'text' => 'Every change is recorded, so nothing gets lost. Each row says what happened, who did it and where from.'],
            'admin/settings'      => ['pose' => 'laptop',      'title' => 'Settings',        'text' => 'School details, branding and account options. Whatever you change here is shared by every dashboard in the school.'],

            // ---- Admin: System ----------------------------------------------
            // The three publishing pages. These are the ones an administrator is
            // most likely to be lost on, because nothing on screen says where the
            // content ends up - so each line says both what the page controls AND
            // the public page it lands on. "You upload a header" is only useful
            // next to "and it appears at /gad".
            'admin/landing-page'  => ['pose' => 'hero',        'title' => 'Landing Page',    'text' => 'Choose the three hero slides and the announcement strip for the front page. This is the first thing a visitor sees at the home address, so save and then go and look at it.'],
            'admin/childpro-gad'  => ['pose' => 'celebrate',   'title' => 'CHILDPRO / GAD',  'text' => 'Two tabs, CHILDPRO and GAD. Each one manages the content of its public page: a header image and up to six sections, which is exactly what appears there. My own message on those pages is written here too.'],
            'admin/programs-projects' => ['pose' => 'celebrate', 'title' => 'Programs & Projects', 'text' => 'Two tabs, Programs and Projects. Each takes a header image and up to six sections, and that is exactly what appears on the public pages. Change it here and the public page changes.'],
            'admin/platform-ratings' => ['pose' => 'star',     'title' => 'Platform feedback', 'text' => 'How staff and learners rate the platform, and what they said needs fixing. This is the page that reaches the people who can act on it.'],

            'admin/backups'       => ['pose' => 'laptop',      'title' => 'Backups',         'text' => 'Download a backup of the whole system, or restore one. Take one before anything large.'],

            // ---- Admin: Account ---------------------------------------------
            'admin/profile'       => ['pose' => 'bust',        'title' => 'Your profile',    'text' => 'Your own name, contact details and password.'],

            // Student
            'student/dashboard'     => ['pose' => 'chart',       'title' => 'Your dashboard', 'text' => 'Your grades, attendance and announcements at a glance.'],
            'student/grades'        => ['pose' => 'medal',       'title' => 'Your grades',    'text' => 'Quarterly grades and your general average live here.'],
            'student/report-card'   => ['pose' => 'medal',       'title' => 'Report card',    'text' => 'Your report card for the term, subject by subject, with your general average at the bottom.'],
            'student/learner-development-report' => ['pose' => 'reading', 'title' => 'Learner development', 'text' => 'The DepEd learner development report for the reporting period: the strengths and the areas you are working on.'],
            'student/schedule'      => ['pose' => 'point-down',  'title' => 'Schedule',       'text' => 'Your daily class schedule.'],
            // Keyed 'student/id-cards' to match the route and the sidebar. The old
            // singular key matched nothing at all, so opening a student's own ID
            // card fell through to the generic tour and the dock fell through to
            // the bare "Your portal" line.
            'student/id-cards' => ['pose' => 'tap-card',    'title' => 'Your ID card',   'text' => 'Here is your ID card. You can print it any time.'],
            'student/materials'     => ['pose' => 'reading',     'title' => 'Materials',      'text' => 'Everything your teachers have uploaded.'],
            'student/announcements' => ['pose' => 'megaphone',   'title' => 'Announcements',  'text' => 'News from your teachers and the school office.'],
            'student/analytics'     => ['pose' => 'star',       'title' => 'Progress',       'text' => 'How you are tracking compared to your last term.'],
            'student/notifications' => ['pose' => 'point-up',    'title' => 'Notifications',  'text' => 'Everything the school and your teachers have sent you, newest first.'],
            'student/platform-rating' => ['pose' => 'star',      'title' => 'Rate platform',  'text' => 'Tell us what is working and what is not. It goes straight to the people who can fix it.'],
            'student/profile'       => ['pose' => 'bust',        'title' => 'Profile',         'text' => 'Your photo, your BMI and your contact details. Your dashboard unlocks once this is finished.'],
            // Teacher. Every route in portal_nav_helper() is listed here, and that
            // completeness matters: an unlisted segment does not fall through to
            // something sensible, it falls through to the bare "Your portal" line
            // in $areas below. That is why the whole Insights group used to say
            // the same thing on Analytics, Announcements and Materials.
            //
            // Longer entries are prefixes on purpose, so the sub-pages are covered
            // without a line each: teacher/grades covers teacher/grades-table,
            // teacher/schedule covers teacher/schedule-view, teacher/attendance
            // covers teacher/attendance-history, teacher/sned covers its children.
            // "announcement" is singular for that reason - it has to prefix both
            // teacher/announcements and teacher/announcement-view.
            'teacher/dashboard'       => ['pose' => 'chart',       'title' => 'Your dashboard',  'text' => 'Your sections, attendance and gradebook at a glance.'],
            'teacher/students'        => ['pose' => 'search',      'title' => 'My students',    'text' => 'Everyone in your sections. Search by name to jump straight to a learner.'],
            'teacher/sections'        => ['pose' => 'point-down',  'title' => 'Your sections',   'text' => 'The sections assigned to you, listed below. Attendance, grades and schedules all start from the one you pick.'],
            'teacher/grades'          => ['pose' => 'medal',       'title' => 'Enter Grades',    'text' => 'Encode and review your gradebook here.'],
            'teacher/attendance'      => ['pose' => 'tap-card',    'title' => 'Attendance',      'text' => 'Mark attendance for each of your sections here.'],
            'teacher/schedule'        => ['pose' => 'laptop',      'title' => 'My Schedule',     'text' => 'Your teaching schedule, and anything the office has changed.'],
            'teacher/analytics'       => ['pose' => 'chart',       'title' => 'Class Analytics', 'text' => 'Averages, attendance rate and term trends for each of your sections.'],
            'teacher/announcement'    => ['pose' => 'megaphone',   'title' => 'Announcements',   'text' => 'News from the school office and your fellow teachers.'],
            'teacher/materials'       => ['pose' => 'reading',     'title' => 'Materials',       'text' => 'Learning materials shared with your classes.'],
            'teacher/messages'        => ['pose' => 'envelope',    'title' => 'Messages',        'text' => 'Messages from parents and the school office.'],
            'teacher/sned'            => ['pose' => 'reading',     'title' => 'SNED',            'text' => 'Special Needs and Education act records.'],
            'teacher/platform-rating' => ['pose' => 'star',        'title' => 'Rate platform',   'text' => 'Tell us what is working and what is not. It reaches the people who can fix it.'],
            'teacher/profile'         => ['pose' => 'bust',        'title' => 'Profile',         'text' => 'Keep your details and your password up to date.'],
            'teacher/notifications'   => ['pose' => 'point-up',    'title' => 'Notifications',   'text' => 'Anything new from your teachers or the office.'],
            // Parent
            'parent/dashboard'      => ['pose' => 'chart',       'title' => 'Your dashboard', 'text' => "Your child's attendance, grades and announcements."],
            'parent/children'       => ['pose' => 'point-down',  'title' => 'My Children',    'text' => 'Every child linked to your account, listed below. Pick one and their own progress opens up.'],
            'parent/grades'         => ['pose' => 'medal',       'title' => 'Grades',         'text' => "Your child's quarterly grades here."],
            'parent/announcements'  => ['pose' => 'megaphone',   'title' => 'Announcements',  'text' => 'News from the school office.'],
            // Shared
            'profile'               => ['pose' => 'bust',        'title' => 'Profile',        'text' => 'Keep your details and your password up to date.'],
            'notifications'         => ['pose' => 'point-up',    'title' => 'Notifications',  'text' => 'Anything new from your teachers or the office.'],

            // Public pages. These are reachable by anyone, including people who
            // have never signed in, so the wording explains the page rather than
            // assuming a portal the visitor may not have access to.
            //
            // The four publishing pages (CHILDPRO, GAD, Programs, Projects) are
            // the ones an administrator can reword at runtime - see
            // mascot_line_override_save(). Each is a SEPARATE key rather than one
            // shared entry, because they are separate tabs an administrator edits
            // separately, and a line that mentions "Programs and Projects" is wrong
            // on a page called "Projects". This is also the only public page whose
            // copy names where the content came from.
            'home'                  => ['pose' => 'hero',        'title' => 'Welcome',        'text' => 'This is Cauayan South Central School. Sign in to reach your portal, or browse the public pages first.'],
            'about'                 => ['pose' => 'reading',     'title' => 'About us',       'text' => 'The school, the people who run it and what we are here for.'],
            'register'              => ['pose' => 'thumbs-up',   'title' => 'Enrol a student','text' => 'Four short steps. Pick "Transferee" if your child is coming from another school and I will ask where they came from.'],
            'teacher/register'      => ['pose' => 'thumbs-up',   'title' => 'Teacher sign-up', 'text' => 'Register as teaching personnel. An administrator reviews every application before it is approved.'],
            'forgot-password'       => ['pose' => 'thinking',    'title' => 'Reset password', 'text' => 'Tell me the email on the account and I will get you a reset link.'],
            'childpro'              => ['pose' => 'star',        'title' => 'CHILDPRO',       'text' => 'The school\'s activities for ages one to five: day care, nutrition, health and early learning. Everything here was written by the school office.'],
            'gad'                   => ['pose' => 'medal',       'title' => 'GAD',            'text' => 'The Gender and Development programme: projects that make the school safer and fairer for everyone. This is our work on that, updated by the school office.'],
            'programs'              => ['pose' => 'celebrate',   'title' => 'Programs',       'text' => 'Everything the school runs, in one place. The school office keeps this page up to date.'],
            'projects'              => ['pose' => 'celebrate',   'title' => 'Projects',       'text' => 'Work in progress and work already finished, written up by the school office.'],
            'school-materials'      => ['pose' => 'reading',     'title' => 'Materials',      'text' => 'Downloadable learning materials shared by the school office.'],
        ];

        $areas = [
            'admin'   => ['pose' => 'point-right', 'title' => 'Admin dashboard', 'text' => 'Everything for running the school lives in this menu.'],
            'student' => ['pose' => 'hero',        'title' => 'Your portal',      'text' => 'Everything you need for school is in this menu.'],
            'teacher' => ['pose' => 'hero',        'title' => 'Your portal',      'text' => 'Everything you need for school is in this menu.'],
            'parent'  => ['pose' => 'hero',        'title' => 'Your portal',      'text' => "Follow your child's progress from this menu."],
        ];

        $best = null;
        $bestLen = 0;

        foreach ($lines as $prefix => $line) {
            if (strlen($prefix) > $bestLen && ($key === $prefix || str_starts_with($key, $prefix))) {
                $best = $line;
                $bestLen = strlen($prefix);
            }
        }

        if ($best !== null) {
            return $best;
        }

        $area = explode('/', $key)[0] ?? $key;

        return $areas[$area] ?? ['pose' => 'point-right', 'title' => 'Tap n Track', 'text' => 'Ask me anything about this page.'];
    }
}

if (! function_exists('mascot_say')) {
    /**
     * A speech bubble next to a mascot, for the places a floating dock cannot go.
     *
     * Tappy reads as speaking when there is a bubble he is attached to, with a
     * tail pointing back at him, rather than when text appears in a box near the
     * corner of the screen.
     *
     * Everything degrades to plain readable text with JavaScript off: the full
     * sentence is in the DOM and the typewriter only ever *reveals* it.
     *
     * @param array{text?:string,title?:string,pose?:string,size?:int,align?:string,
     *              class?:string,type?:bool,actions?:array<int,array{label:string,action:string}>} $options
     *   text     the line; required, and the whole component disappears without it
     *   title    optional bold heading above the line
     *   pose     which pose to use (defaults to the line's own pose)
     *   size     px for the artwork (default 140), or a CSS custom property
     *            such as 'var(--mascot-hero)' to keep it responsive
     *   align    'left' or 'right' - which side the character stands on
     *   type     reveal the text character by character (default true)
     *   actions  buttons under the line, each ['label' => ..., 'action' => 'tour'|'close']
     */
    function mascot_say(array $options): string
    {
        $text = trim((string) ($options['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $title  = trim((string) ($options['title'] ?? ''));
        $align  = ($options['align'] ?? 'right') === 'left' ? 'left' : 'right';
        $rawSize = $options['size'] ?? 140;
        // Same pass-through as mascot_img(): a CSS custom property has to survive
        // as text, or the int cast below turns it into 0 and the bubble art
        // collapses to the 48px floor.
        $size   = is_string($rawSize) && str_starts_with(trim($rawSize), 'var(')
            ? trim($rawSize)
            : max(48, (int) $rawSize);
        $pose   = (string) ($options['pose'] ?? 'point-right');
        $extra  = trim((string) ($options['class'] ?? ''));
        $type   = array_key_exists('type', $options) ? (bool) $options['type'] : true;

        $classes = 'mascot-say mascot-say--' . $align;
        if ($extra !== '') {
            $classes .= ' ' . $extra;
        }

        // Only these two are recognised, so a caller cannot inject an arbitrary
        // attribute name into the markup.
        $known = [
            'tour'   => 'data-mascot-tour',
            'close'  => 'data-mascot-say-close',
        ];

        $buttons = '';

        foreach ((array) ($options['actions'] ?? []) as $action) {
            $label = trim((string) ($action['label'] ?? ''));
            $key   = (string) ($action['action'] ?? '');

            if ($label === '' || ! isset($known[$key])) {
                continue;
            }

            $buttons .= '<button type="button" class="mascot-say__action" ' . $known[$key] . '>'
                . esc($label)
                . '</button>';
        }

        $actionsHtml = $buttons === ''
            ? ''
            : '<div class="mascot-say__actions">' . $buttons . '</div>';

        $titleHtml = $title === ''
            ? ''
            : '<p class="mascot-say__title">' . esc($title) . '</p>';

        return '<div class="' . esc($classes) . '" data-mascot-say>'
            . '<div class="mascot-say__bubble" role="status">'
            . $titleHtml
            . '<p class="mascot-say__text"' . ($type ? ' data-mascot-type' : '') . '>' . esc($text) . '</p>'
            . $actionsHtml
            . '</div>'
            . mascot_img([
                'name'    => $pose,
                'size'    => $size,
                'class'   => 'mascot-say__art',
                'loading' => 'eager',
            ])
            . '</div>';
    }
}


if (! function_exists('mascot_welcome_settings')) {
    /**
     * Read a handful of system_settings keys in one query, keyed by setting_key.
     *
     * Every read is guarded. The welcome modal is a nicety, and it must never be
     * able to take a dashboard down because the settings table is unreachable or
     * a key has not been written yet.
     *
     * @param string[] $keys
     * @return array<string,string>
     */
    function mascot_welcome_settings(array $keys): array
    {
        $out = array_fill_keys($keys, '');

        try {
            $rows = \Config\Database::connect()
                ->table('system_settings')
                ->select('setting_key, setting_value')
                ->whereIn('setting_key', $keys)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $out[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Mascot welcome settings read failed: ' . $e->getMessage());
        }

        return $out;
    }
}

if (! function_exists('mascot_welcome_copy')) {
    /**
     * Welcome-modal content for a role, with defaults for anything unset.
     *
     * The admin can override the title, the body, the dialogue line and the poster
     * per role from admin/settings. Everything falls back to a sensible default so
     * the modal works on a fresh install with no settings written at all.
     *
     * @return array{role:string,label:string,title:string,body:string,dialogue:string,
     *               poster:string,bullets:string[],enabled:bool}
     */
    function mascot_welcome_copy(?string $role = null): array
    {
        $role = $role ?? mascot_role_key();

        if (! in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
            $role = 'admin';
        }

        $labels = [
            'admin'   => 'Admin dashboard',
            'teacher' => 'Teacher dashboard',
            'student' => 'Student dashboard',
            'parent'  => 'Parent dashboard',
        ];

        $bullets = [
            'admin' => [
                'Add students, teachers and sections',
                'Record grades, attendance and announcements',
                'School settings, ID cards and backups',
            ],
            'teacher' => [
                'Mark attendance for each of your sections',
                'Encode grades and review your gradebook',
                'Upload materials and message parents',
            ],
            'student' => [
                'Check your grades and general average',
                'See your daily schedule and print your ID card',
                'Read announcements from your teachers',
            ],
            'parent' => [
                "Follow your child's attendance",
                'See quarterly grades as they are encoded',
                'Read announcements from the school office',
            ],
        ];

        $keys = [
            'welcome_enabled',
            'welcome_title_' . $role,
            'welcome_body_' . $role,
            'welcome_dialogue_' . $role,
            'welcome_poster_' . $role,
        ];

        $settings = mascot_welcome_settings($keys);

        $label = $labels[$role];
        $title = trim($settings['welcome_title_' . $role] ?? '');
        $body  = trim($settings['welcome_body_' . $role] ?? '');
        $line  = trim($settings['welcome_dialogue_' . $role] ?? '');
        $poster = trim($settings['welcome_poster_' . $role] ?? '');

        return [
            'role'     => $role,
            'label'    => $label,
            'title'    => $title !== '' ? $title : 'This is the ' . $label,
            'body'     => $body !== '' ? $body : 'Everything you need is on this page and in the menu on the left.',
            'dialogue' => $line !== '' ? $line : 'Tap "Show me around" and I will point out the parts you use most.',
            'poster'   => $poster !== '' && function_exists('featured_poster_url')
                ? featured_poster_url($poster)
                : '',
            'bullets'  => $bullets[$role],
            // Absent means on: a fresh install has no key at all, and a modal
            // nobody asked for is worse than one that greets them.
            'enabled'  => trim($settings['welcome_enabled'] ?? '') !== '0',
        ];
    }
}

