<?php

declare(strict_types=1);

/**
 * Mascot artwork optimiser.
 *
 * The 16 character poses are generated at 1024x1536 and land in
 * public/assets/mascot at roughly 1.7-2.3 MB each, which is about 31 MB of PNG
 * on disk. Two things follow from that, and both are the reason this tool
 * exists:
 *
 *   1. Every page that shows Tappy pays for the poses it uses. At 220-320px of
 *      on-screen size a 1024x1536 source carries 3-4x more pixels than the
 *      display can show, so the extra weight buys nothing.
 *   2. "Bigger mascot" is not affordable until the weight comes down. This tool
 *      is what makes the larger placements in mascot.css cheap.
 *
 * What it does, per file:
 *   - finds the bounding box of the non-transparent pixels and crops the dead
 *     margin the generator leaves around the character;
 *   - resamples the long edge down to --max (default 640px), still ~2x the
 *     largest size we render (320px), so it stays crisp on high-DPI screens;
 *   - re-encodes as RGBA PNG with maximum compression.
 *
 * A file is re-encoded when it is too big, too heavy, or both. Weight matters on
 * its own: a hand-pasted file can arrive at exactly the target dimensions and
 * still be several megabytes, and a dimension-only check skips those forever.
 *
 * Two kinds of file must NOT be cropped to their bounding box:
 *
 *   - patterns (pattern-*). A tiling texture has to keep every pixel of its
 *     canvas, or the tile no longer tiles. Trimming it to its alpha bounds
 *     produces a small motif with a hard edge that repeats as a visible box.
 *     Detected by name so the correct behaviour is the default, not a flag
 *     someone has to remember to pass.
 *   - anything passed with --no-trim, for artwork whose canvas is the artwork.
 *
 * The alpha channel is preserved exactly as found: pixels are only dropped when
 * they are fully transparent, and the crop is driven by the alpha channel
 * alone. Metadata is not carried over, which is deliberate - the artwork is
 * drawn by hand and has no metadata worth keeping.
 *
 * Usage:
 *   php tools/mascot_optimize.php              optimise every pose in place
 *   php tools/mascot_optimize.php --dry-run    report only, write nothing
 *   php tools/mascot_optimize.php --max=768    keep a longer edge (default 640)
 *   php tools/mascot_optimize.php --one=hero   only this file (repeatable)
 *   php tools/mascot_optimize.php --no-trim    re-encode without cropping
 *   php tools/mascot_optimize.php --max-weight=300000   force a re-encode (bytes)
 *
 * Safe to run twice: a file already at or under both targets is skipped.
 */

const MASCOT_DIR = __DIR__ . '/../public/assets/mascot';
const DEFAULT_MAX_EDGE = 640;

/**
 * Re-encode any file heavier than this, even when its dimensions are fine.
 *
 * Dimensions alone are not a weight test. A hand-pasted PNG can arrive at
 * exactly the target size and still be several megabytes, and a dimension-only
 * rule skips those forever while the site pays for them.
 *
 * 500 KB is deliberately above bust.png, which sits at 461 KB and is the
 * heaviest legitimately-optimised file here. It is not bloated: at 525x639 it
 * carries 1.4x the pixels of reading.png (373x640) and weighs 1.4x as much.
 * Re-encoding it measures 457 KB, a 1% gain that does not justify a resample
 * round-trip on the artwork used for every modal header - especially since
 * imagecopyresampled at 1:1 is not guaranteed bit-identical, so the trade is
 * generation loss for 4 KB.
 *
 * The threshold is there to catch genuinely bloated files, not to shave the
 * largest correct one. Lower it only if a new file arrives that is heavy for a
 * reason this reasoning would not explain.
 */
const DEFAULT_MAX_WEIGHT = 500 * 1024;

/**
 * Files whose canvas is meaningful and must never be cropped to alpha bounds.
 *
 * A tiling pattern is the important case: trimming it leaves a small motif with
 * a hard rectangular edge that repeats across the page as a visible box, which
 * looks far worse than the pattern ever did. Matching on the name prefix means
 * the safe behaviour is the default rather than a flag someone has to remember.
 */
const MASCOT_NO_TRIM_PREFIXES = ['pattern-'];

/** Alpha below this counts as "empty" when measuring the crop box. */
const ALPHA_CUTOFF = 110;

$options = [
    'dryRun' => false,
    'maxEdge' => DEFAULT_MAX_EDGE,
    'maxWeight' => DEFAULT_MAX_WEIGHT,
    'noTrim' => false,
    'only' => [],
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
        $options['dryRun'] = true;
    } elseif (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
        if ($max < 64) {
            fwrite(STDERR, "--max must be at least 64\n");
            exit(1);
        }
        $options['maxEdge'] = $max;
    } elseif (str_starts_with($arg, '--max-weight=')) {
        $bytes = (int) substr($arg, 13);
        if ($bytes < 1024) {
            fwrite(STDERR, "--max-weight must be at least 1024\n");
            exit(1);
        }
        $options['maxWeight'] = $bytes;
    } elseif ($arg === '--no-trim') {
        $options['noTrim'] = true;
    } elseif (str_starts_with($arg, '--one=')) {
        $name = trim((string) substr($arg, 6));
        if ($name !== '') {
            $options['only'][] = $name;
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php tools/mascot_optimize.php [--dry-run] [--max=N] [--max-weight=B] [--no-trim] [--one=name]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "The GD extension is required to resample the artwork.\n");
    exit(1);
}

if (! is_dir(MASCOT_DIR)) {
    fwrite(STDERR, 'Mascot directory not found: ' . MASCOT_DIR . "\n");
    exit(1);
}

$names = $options['only'];
if ($names === []) {
    // Use the file names exactly as they are on disk. Re-slugifying here would
    // break on any name that is not already a clean slug, and the file would then
    // be reported as missing when it is sitting right there.
    foreach ((glob(MASCOT_DIR . '/*.png') ?: []) as $path) {
        $names[] = basename($path, '.png');
    }
}

if ($names === []) {
    echo "No PNG files found - nothing to do.\n";
    exit(0);
}


/**
 * True when this file's full canvas is part of the artwork and must survive.
 *
 * @see MASCOT_NO_TRIM_PREFIXES for why a tiling pattern cannot be cropped.
 */
function mascot_keeps_full_canvas(string $name): bool
{
    foreach (MASCOT_NO_TRIM_PREFIXES as $prefix) {
        if (str_starts_with($name, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Bounding box of the pixels that are not (almost) transparent.
 *
 * Stepped sampling rather than every pixel: a 1024x1536 image is 1.5M pixels and
 * this only needs an approximate margin, so a step of 2 is fast enough and lands
 * within a pixel or two of the true edge in practice.
 *
 * @return array{0:int,1:int,2:int,3:int}|null null when the image is empty
 */
function alpha_bounds(\GdImage $im): ?array
{
    $w = imagesx($im);
    $h = imagesy($im);
    $minX = $w;
    $minY = $h;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $h; $y += 2) {
        for ($x = 0; $x < $w; $x += 2) {
            $alpha = (imagecolorat($im, $x, $y) >> 24) & 0x7F;
            if ($alpha >= ALPHA_CUTOFF) {
                continue;
            }
            if ($x < $minX) { $minX = $x; }
            if ($x > $maxX) { $maxX = $x; }
            if ($y < $minY) { $minY = $y; }
            if ($y > $maxY) { $maxY = $y; }
        }
    }

    return $maxX < 0 ? null : [$minX, $minY, $maxX, $maxY];
}


$maxEdge   = $options['maxEdge'];
$maxWeight = $options['maxWeight'];
$noTrim    = $options['noTrim'];
$dryRun    = $options['dryRun'];
$totalBefore = 0;
$totalAfter = 0;
$changed = 0;
$skipped = 0;
$failed = 0;

echo "Optimising mascot artwork in " . realpath(MASCOT_DIR) . "\n";
echo $dryRun ? "(dry run - no files will be written)\n\n" : "\n";
printf("%-16s %-12s %-12s %-10s %s\n", 'file', 'before', 'after', 'change', 'action');
echo str_repeat('-', 66) . "\n";

foreach ($names as $name) {
    $file = mascot_optimize_path($name);
    if (! is_file($file)) {
        printf("%-16s %s\n", $name, 'missing - skipped');
        $failed++;
        continue;
    }

    $before = filesize($file);
    $totalBefore += $before;

    $info = @getimagesize($file);
    $srcW = $info ? (int) $info[0] : 0;
    $srcH = $info ? (int) $info[1] : 0;
    $longest = max($srcW, $srcH);

    // Two independent reasons to rewrite: the long edge is over target, or the
    // file is simply heavy. Testing only the dimensions meant a file could sit
    // at exactly the right size and megabytes of weight forever, which is how
    // the eight stickers ended up at 1254px and ~1.1 MB each while rendering at
    // 28-64px on screen.
    $tooBig   = $longest > $maxEdge;
    $tooHeavy = $before > $maxWeight;
    $keepCanvas = $noTrim || mascot_keeps_full_canvas($name);

    if (! $tooBig && ! $tooHeavy) {
        $totalAfter += $before;
        $skipped++;
        printf("%-16s %-12s %-12s %-10s %s\n", $name, format_bytes($before), format_bytes($before), '-', 'already ' . $longest . 'px');
        continue;
    }

    $src = @imagecreatefrompng($file);
    if ($src === false) {
        $totalAfter += $before;
        $failed++;
        printf("%-16s %-12s %-12s %-10s %s\n", $name, format_bytes($before), format_bytes($before), '-', 'UNREADABLE - skipped');
        continue;
    }

    if ($keepCanvas) {
        // The canvas IS the artwork. Cropping a tile to its alpha bounds leaves a
        // small motif with a hard edge that repeats as a visible box, so the
        // whole frame is kept and only the encoding changes.
        $minX = $minY = 0;
        $cropW = $srcW;
        $cropH = $srcH;
    } else {
        $bounds = alpha_bounds($src);
        if ($bounds === null) {
            imagedestroy($src);
            $totalAfter += $before;
            $failed++;
            printf("%-16s %-12s %-12s %-10s %s\n", $name, format_bytes($before), format_bytes($before), '-', 'fully transparent - skipped');
            continue;
        }

        [$minX, $minY, $maxX, $maxY] = $bounds;
        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;
    }

    // Only ever scale DOWN. Clamping at 1.0 matters for a heavy file that is
    // already within the dimension target: without it, the old dimension-only
    // path would have divided by a small crop and blown the artwork up.
    $scale = min(1.0, $maxEdge / max($cropW, $cropH));
    $dstW = max(1, (int) round($cropW * $scale));
    $dstH = max(1, (int) round($cropH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    // Transparent destination, then resample: with blending off the copied
    // pixels replace the target outright and the alpha channel survives.
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, $minX, $minY, $dstW, $dstH, $cropW, $cropH);

    $after = optimise_png_bytes($dst);
    imagedestroy($src);
    imagedestroy($dst);

    if ($after === null) {
        $totalAfter += $before;
        $failed++;
        printf("%-16s %-12s %-12s %-10s %s\n", $name, format_bytes($before), format_bytes($before), '-', 'ENCODE FAILED - kept original');
        continue;
    }

    if (! $dryRun) {
        // Write to a sibling temp file and swap, so a failure mid-write can
        // never leave a half-written PNG where the artwork used to be.
        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $after) === false || ! rename($tmp, $file)) {
            @unlink($tmp);
            $totalAfter += $before;
            $failed++;
            printf("%-16s %-12s %-12s %-10s %s\n", $name, format_bytes($before), format_bytes($before), '-', 'WRITE FAILED - kept original');
            continue;
        }
    }



    // optimise_png_bytes() hands back the encoded file as a string, so take the
    // byte count once and do the arithmetic on that.
    $afterLen = strlen($after);
    $totalAfter += $afterLen;
    $changed++;

    // Say what actually happened. A heavy file that was already the right size
    // gets re-encoded without being resampled, and "640x640 -> 640x640" would
    // read as a pointless round trip rather than the fix it is.
    $action = ($dstW === $cropW && $dstH === $cropH)
        ? sprintf('re-encoded only, canvas kept %dx%d', $cropW, $cropH)
        : sprintf('%dx%d -> %dx%d', $cropW, $cropH, $dstW, $dstH);

    printf(
        "%-16s %-12s %-12s %-10s %s\n",
        $name,
        format_bytes($before),
        format_bytes($afterLen),
        ($afterLen < $before ? '-' : '+') . round(abs($afterLen - $before) / max(1, $before) * 100) . '%',
        $action
    );
}

echo str_repeat('-', 66) . "\n";
printf("%d rewritten, %d already fine, %d problem(s)\n", $changed, $skipped, $failed);
printf(
    "total %s -> %s (%s)\n",
    format_bytes($totalBefore),
    format_bytes($totalAfter),
    $totalAfter < $totalBefore
        ? 'saved ' . format_bytes($totalBefore - $totalAfter)
        : 'grew ' . format_bytes($totalAfter - $totalBefore)
);

if ($totalAfter > 0) {
    printf("all poses together: %.2f MB -> %.2f MB\n", $totalBefore / 1048576, $totalAfter / 1048576);
}

exit($failed > 0 ? 1 : 0);

// ---------------------------------------------------------------------------

/**
 * Resolve a name to a file inside the mascot folder.
 *
 * Tries the name as given first, then as a slug, so both a real file name from the
 * directory scan and a loosely typed --one=point right both work. basename() keeps
 * the first branch from being walked out of the folder.
 */
function mascot_optimize_path(string $name): string
{
    $literal = MASCOT_DIR . '/' . basename($name, '.png') . '.png';
    if (is_file($literal)) {
        return $literal;
    }

    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return MASCOT_DIR . '/' . ($slug === '' ? '' : $slug . '.png');
}

/** Compress a truecolor image as hard as PNG will go. */
function optimise_png_bytes(\GdImage $im): ?string
{
    ob_start();
    $ok = imagepng($im, null, 9, PNG_NO_FILTER);
    $bytes = ob_get_clean();

    return ($ok && is_string($bytes) && $bytes !== '') ? $bytes : null;
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }

    return $bytes . ' B';
}

