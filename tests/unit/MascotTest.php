<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The Tappy mascot system.
 *
 * Two guarantees matter here and both are enforced by tests:
 *
 *  1. The site is fully functional with NO artwork present. The artwork is
 *     AI-generated and pasted in by hand, so every mascot must degrade to
 *     nothing rather than leaving a broken image or an empty gap.
 *  2. window.confirm must never be overridden. modal-system.js documents a
 *     real bug: a promise-based confirm is always truthy, so synchronous
 *     `if (confirm(msg)) {...}` callers silently auto-approve. That deleted
 *     announcements. The mascot work must not reintroduce it.
 *
 * @internal
 */
final class MascotTest extends CIUnitTestCase
{
    private function asset(string $path): string
    {
        return (string) file_get_contents(ROOTPATH . $path);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The framework memoises the autoloaded-helper list, and other tests in
        // this suite swap that list out. Loading explicitly keeps this test
        // independent of ordering.
        helper('mascot');
        helper('asset');
    }

    private function layout(): string
    {
        return $this->asset('app/Views/dashboard_layout.php');
    }

    private function publicLayout(): string
    {
        return $this->asset('app/Views/layout.php');
    }

    // ---------------------------------------------------------------- names

    public function testFileNameIsNormalised(): void
    {
        $this->assertSame('point-right.png', mascot_file_name('point-right'));
        $this->assertSame('point-right.png', mascot_file_name('  point_right  '));
        $this->assertSame('point-right.png', mascot_file_name('Point Right'));
    }

    /**
     * A name must never be able to escape the mascot folder.
     */
    public function testFileNameRejectsTraversal(): void
    {
        $this->assertSame('', mascot_file_name(''));
        $this->assertSame('', mascot_file_name('   '));
        $this->assertSame('', mascot_file_name('..'));
        $this->assertSame('', mascot_file_name('!!!'));

        foreach (['../../etc/passwd', '..\\..\\windows', '/absolute/path'] as $evil) {
            $file = mascot_file_name($evil);
            $this->assertStringNotContainsString('/', $file);
            $this->assertStringNotContainsString('\\', $file);
            $this->assertStringNotContainsString('..', $file);
        }
    }

    public function testUrlPointsAtTheMascotFolder(): void
    {
        $url = mascot_url('point-right');
        $this->assertStringContainsString('assets/mascot/point-right.png', $url);
    }

    public function testAssetDirectoryIsStable(): void
    {
        // The prompts document the folder; the code and the docs must agree.
        $this->assertSame('assets/mascot', mascot_asset_dir());
        $this->assertDirectoryExists(ROOTPATH . 'public/assets/mascot');
    }

    public function testMascotNameIsUsed(): void
    {
        $this->assertNotSame('', mascot_name());
    }

    // ------------------------------------------------- graceful degradation

    /**
     * mascot_img() must always produce a wrapper, never a bare <img>, because
     * the CSS hides the wrapper when the image cannot load.
     *
     * The width/height pair follows the artwork's real proportions rather than
     * being a square. That is deliberate: the poses are 2:3 portrait (hero is
     * about 1:2.3), so a square pair both letterboxed the character and made the
     * browser reserve the wrong box while the image loaded.
     */
    public function testImageIsAlwaysWrappedForGracefulHiding(): void
    {
        $html = mascot_img(['name' => 'hero', 'alt' => 'Tappy waving', 'size' => 150]);

        $this->assertStringContainsString('class="mascot mascot--hero"', $html);
        $this->assertStringContainsString('alt="Tappy waving"', $html);
        $this->assertStringContainsString('decoding="async"', $html);

        // The long edge is the requested size, the short edge comes from the art.
        $this->assertStringContainsString('--mascot-size:150px', $html);
        $this->assertMatchesRegularExpression(
            '/width="(\d+)" height="(\d+)"/',
            $html,
            'the <img> must carry width/height so the layout does not shift'
        );

        preg_match('/width="(\d+)" height="(\d+)"/', $html, $m);
        $this->assertSame(150, (int) $m[2], 'the long edge must be the requested size');
        $this->assertLessThan(150, (int) $m[1], 'a portrait pose must not be given a square box');

        $dims = mascot_dimensions('hero');
        $this->assertNotNull($dims, 'hero.png should be readable from disk');
        $this->assertSame(
            (int) round(150 * $dims['width'] / $dims['height']),
            (int) $m[1],
            'the short edge must match the artwork aspect ratio'
        );
    }

    /**
     * A CSS custom property has to survive mascot_img() as text. An inline pixel
     * value would beat the responsive media query, which is how the dock, the
     * dialog art and the login speech bubble stay shrinkable on a phone.
     */
    public function testSizeAcceptsACssCustomProperty(): void
    {
        $html = mascot_img(['name' => 'hero', 'size' => 'var(--mascot-dock)']);

        $this->assertStringContainsString('--mascot-size:var(--mascot-dock)', $html);
        $this->assertStringNotContainsString('--mascot-size:0px', $html);
    }

    public function testUnknownArtworkFallsBackToASquareBox(): void
    {
        $html = mascot_img(['name' => 'no-such-pose', 'size' => 120]);

        $this->assertStringContainsString('--mascot-ratio:1', $html);
        $this->assertStringContainsString('width="120" height="120"', $html);
    }

    public function testDecorativeArtIsHiddenFromAssistiveTech(): void
    {
        $html = mascot_img(['name' => 'sleeping']);

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testEmptyNameProducesNothing(): void
    {
        $this->assertSame('', mascot_img([]));
        $this->assertSame('', mascot_img(['name' => '']));
        $this->assertSame('', mascot_img(['name' => '!!!']));
    }

    public function testMissingArtworkIsReportedNotAnError(): void
    {
        // No image has been pasted in yet, so this must be false — and it must
        // be safe to call.
        $this->assertFalse(mascot_exists('definitely-not-a-real-pose'));
        $this->assertFalse(mascot_exists(''));
    }

    // ------------------------------------------------------------- wiring

    public function testBothLayoutsLoadTheStylesheetAndScript(): void
    {
        foreach (['dashboard' => $this->layout(), 'public' => $this->publicLayout()] as $name => $html) {
            $this->assertStringContainsString('css/mascot.css', $html, $name . ' layout must load mascot.css');
            $this->assertStringContainsString('js/mascot.js', $html, $name . ' layout must load mascot.js');
        }
    }

    public function testOnlyTheDashboardLayoutLoadsTheTour(): void
    {
        // The tour is a dashboard feature; the public pages must not load it.
        $this->assertStringContainsString('js/mascot-tour.js', $this->layout());
        $this->assertStringNotContainsString('mascot-tour.js', $this->publicLayout());
    }

    /**
     * The "Take a tour" button used to live in the top bar. It was removed on
     * purpose: the tour starts by itself on a role's first visit, and Tappy in the
     * floating dock is now the way to replay it. This test exists so the button
     * cannot quietly come back, and so the replacement path stays wired.
     */
    public function testTopBarTourButtonIsGoneAndTheDockTakesOver(): void
    {
        $layout = $this->layout();

        $this->assertStringNotContainsString('id="mascotTourButton"', $layout);
        $this->assertStringNotContainsString('Take a tour', $layout);

        // The replay now lives on the mascot himself.
        $dock = $this->asset('app/Views/partials/mascot_dock.php');
        $this->assertStringContainsString('data-mascot-tour', $dock);
        $this->assertStringContainsString('Show me around', $dock);
        $this->assertStringContainsString('data-mascot-trigger', $dock);

        // ...and the dock button must no-op safely if the tour script failed.
        $js = $this->asset('public/js/mascot.js');
        $this->assertStringContainsString('__startMascotTour', $js);
        $this->assertStringContainsString("typeof window.__startMascotTour === 'function'", $js);
    }

    public function testHelperIsAutoloaded(): void
    {
        $this->assertStringContainsString("'mascot'", $this->asset('app/Config/Autoload.php'));
    }

    public function testPromptsDocumentEveryPoseTheCodeReferences(): void
    {
        $prompts  = $this->asset('public/assets/mascot/PROMPTS.md');
        $contract = $this->asset('tools/mascot_assets.php');

        // The contract in tools/mascot_assets.php is the authoritative list of
        // every file the app can reference, whether or not it exists yet. It is
        // what the validator checks against, so a file missing from here would
        // be reported as "undocumented" the moment it was pasted in.
        foreach ([
            'hero', 'point-right', 'point-up', 'point-down', 'thumbs-up', 'celebrate',
            'thinking', 'worried', 'sleeping', 'reading', 'laptop', 'tap-card',
            'chart', 'search', 'star', 'bust', 'megaphone', 'envelope', 'medal',
        ] as $pose) {
            $this->assertStringContainsString(
                $pose,
                $contract,
                $pose . ' is used by the app but missing from the contract'
            );
        }

        foreach (['new', 'tip', 'done', 'star', 'grade-a', 'locked', 'alert', 'wow',
            'saved', 'upload', 'question', 'error', 'pending', 'settings',
            'audit', 'backup',
        ] as $sticker) {
            $this->assertStringContainsString(
                $sticker,
                $contract,
                'sticker-' . $sticker . ' is missing from the contract'
            );
        }

        // PROMPTS.md deliberately documents ONLY the outstanding artwork - the
        // already-generated poses and stickers have no prompt lines left to
        // copy, which is the entire point of the trim. So what it must carry is
        // every file still to be produced: that is the checklist someone
        // generating art actually works from.
        foreach ([
            'megaphone.png', 'envelope.png', 'medal.png', 'footer-peek.png',
            'logo-mark.png', 'logo-mark-mono.png',
            'poster-enroll.png', 'poster-about.png', 'poster-announce.png',
            'poster-support.png', 'divider-band.png', 'pattern-doodle.png',
            'sticker-saved.png', 'sticker-upload.png', 'sticker-question.png',
            'sticker-error.png', 'sticker-pending.png', 'sticker-settings.png',
            'sticker-audit.png', 'sticker-backup.png',
        ] as $file) {
            $this->assertStringContainsString(
                $file,
                $prompts,
                $file . ' is still to be generated but missing from PROMPTS.md'
            );
        }

        // The master prompt has to stay: every character class is generated from
        // it, and it is the one thing that cannot be reconstructed.
        $this->assertStringContainsString('The master prompt', $prompts);
        $this->assertStringContainsString('CHARACTER - a cheerful Filipino elementary school student', $prompts);
    }

    // ------------------------------------------------- hard constraints

    /**
     * THE regression guard. modal-system.js must not assign window.confirm.
     */
    public function testWindowConfirmIsNeverOverridden(): void
    {
        $js = $this->asset('public/js/modal-system.js');

        $this->assertStringNotContainsString(
            'window.confirm =',
            $js,
            'window.confirm must not be overridden — a promise-based confirm is always truthy and silently auto-approves'
        );
        $this->assertStringNotContainsString('confirm = function', $js);
    }

    public function testNoScriptFileOverridesConfirm(): void
    {
        foreach (glob(ROOTPATH . 'public/js/*.js') ?: [] as $file) {
            $js = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                'window.confirm =',
                $js,
                basename($file) . ' must not override window.confirm'
            );
        }
    }

    /**
     * Every pose the JS injects must be in the documented set, otherwise an
     * alert would silently end up with a broken image.
     */
    public function testPosesInjectedByJavascriptAreDocumented(): void
    {
        $js = $this->asset('public/js/mascot.js');

        foreach (['thumbs-up', 'worried', 'thinking', 'bust'] as $pose) {
            $this->assertStringContainsString("'" . $pose . "'", $js, 'mascot.js injects the ' . $pose . ' pose');
        }

        $this->assertStringContainsString('.mascot--missing', $this->asset('public/css/mascot.css'));
    }

    public function testAlertStylingExcludesModals(): void
    {
        $css = $this->asset('public/css/mascot.css');

        // Eight alerts live inside modal bodies and would otherwise get a
        // second mascot next to the dialog's own.
        $this->assertStringContainsString('modal-body', $css, 'alert styling must exclude alerts inside modals');
        $this->assertStringContainsString('.mascot-alert', $css);
    }

    public function testStylesAreReducedMotionAndPrintSafe(): void
    {
        $css = $this->asset('public/css/mascot.css');

        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('@media print', $css);
    }

    // ------------------------------------------------- transparency contract

    /**
     * The artwork has to be cut-outs, not flat tiles.
     *
     * Tappy is composited over a dark blue sign-in card, a dark navigation
     * sidebar and tinted alert banners, so any opaque background shows up as a
     * pale rectangle. The prompt must therefore demand a real alpha channel
     * rather than merely permitting one.
     */
    public function testPromptsRequireTransparentBackgrounds(): void
    {
        $prompts = $this->asset('public/assets/mascot/PROMPTS.md');

        $this->assertStringContainsString(
            'TRANSPARENT PNG',
            $prompts,
            'the master prompt must require a transparent background'
        );
        $this->assertStringContainsString(
            'alpha channel',
            $prompts,
            'the prompt must state that the alpha channel must survive'
        );
        $this->assertStringContainsString(
            'Not a JPG',
            $prompts,
            'the prompt must warn that JPG cannot carry transparency'
        );
        // The chroma-key fallback is what makes the requirement achievable on
        // generators that cannot emit alpha.
        $this->assertStringContainsString('#00FF00', $prompts, 'a chroma-key fallback must be documented');
    }

    /**
     * The old advice — generate on flat #f8fafc and remove it afterwards, or
     * keep a flat copy of the hero — would produce exactly the pale box this
     * system has to avoid.
     */
    public function testPromptsNoLongerRecommendAFlatBackground(): void
    {
        $prompts = $this->asset('public/assets/mascot/PROMPTS.md');

        $this->assertStringNotContainsString(
            'flat-background',
            $prompts,
            'a flat-background variant would show as a pale box on the dark login card'
        );
        $this->assertStringNotContainsString(
            'is acceptable',
            $prompts,
            'transparency must be required, not merely "acceptable"'
        );
    }

    /**
     * A cast drop shadow is baked into the pixels and cannot be removed without
     * touching the character, so the prompt must not ask for one.
     */
    public function testPromptDoesNotRequestACastShadow(): void
    {
        $prompts = $this->asset('public/assets/mascot/PROMPTS.md');

        $this->assertStringNotContainsString(
            'soft neutral drop shadow',
            $prompts,
            'a background drop shadow cannot be keyed out cleanly'
        );
        $this->assertStringContainsString(
            'no cast shadow onto the background',
            $prompts,
            'the prompt must say the shadow is internal only'
        );
    }

    /**
     * The checklist is the gate a human runs before pasting a file in, so it has
     * to test transparency first.
     */
    public function testChecklistTestsTransparencyFirst(): void
    {
        $prompts = $this->asset('public/assets/mascot/PROMPTS.md');

        $checklistPos = strpos($prompts, 'Consistency checklist');
        $this->assertIsInt($checklistPos);

        $transparencyPos = strpos($prompts, 'genuinely transparent');
        $this->assertIsInt($transparencyPos);
        $this->assertGreaterThan($checklistPos, $transparencyPos, 'the transparency check belongs in the checklist');

        $this->assertStringContainsString('green fringing', $prompts);
        $this->assertStringContainsString('dark grey or black', $prompts, 'the dark-background check must be documented');
    }

    /**
     * The default wrapper must stay transparent.
     *
     * A background fill on `.mascot` is drawn *behind* the artwork, so even a
     * perfect cut-out would still sit in a visible pale box — worst of all on
     * the dark blue login card. Only the opt-in .mascot--ring / --circle may
     * paint a backdrop.
     */
    public function testDefaultWrapperDoesNotPaintABackdrop(): void
    {
        $css = $this->asset('public/css/mascot.css');

        $this->assertSame(
            1,
            preg_match('/\.mascot\s*\{([^}]*)\}/s', $css, $m),
            'the .mascot base rule must exist'
        );
        $this->assertStringNotContainsString(
            'background',
            $m[1],
            '.mascot must not set a background — it would show through the transparency'
        );
    }

    public function testMedallionBackgroundsAreOptInOnly(): void
    {
        $css = $this->asset('public/css/mascot.css');

        $this->assertStringContainsString('.mascot--ring', $css);
        $this->assertStringContainsString('.mascot--circle', $css);
    }

    // ------------------------------------------------------------ the dock

    public function testBothLayoutsRenderTheDockAtTheEndOfTheBody(): void
    {
        foreach (['dashboard' => $this->layout(), 'public' => $this->publicLayout()] as $name => $html) {
            $this->assertStringContainsString(
                "view('partials/mascot_dock'",
                $html,
                $name . ' layout must include the floating dock'
            );

            // A mascot nested inside a dashboard container is clipped by that
            // container's overflow, which is why it has to be a body child.
            $this->assertMatchesRegularExpression(
                '/mascot_dock.*<\/body>/s',
                $html,
                $name . ' layout must render the dock outside every container, just before </body>'
            );
        }
    }

    /**
     * The dock must not be able to appear without artwork behind it: an empty
     * speech bubble in the corner of the page is worse than no mascot at all.
     */
    public function testDockShipsHiddenAndRevealsItselfOnlyViaScript(): void
    {
        $dock = $this->asset('app/Views/partials/mascot_dock.php');

        $this->assertStringContainsString('data-mascot-dock', $dock);
        $this->assertStringContainsString('data-mascot-dismiss', $dock, 'the greeting needs a dismiss control');
        $this->assertStringContainsString('role="status"', $dock, 'the greeting should be announced');
        $this->assertStringContainsString('hidden', $dock, 'the dock must ship hidden');

        $js = $this->asset('public/js/mascot.js');
        $this->assertStringContainsString("dock.removeAttribute('hidden')", $js);
        $this->assertStringContainsString("img.addEventListener('error'", $js);

        // Once per browser session, and never again once dismissed: the whole
        // point of the dock is that it is not a chat widget.
        $this->assertStringContainsString("storageGet('sessionStorage', greetedKey)", $js);
        $this->assertStringContainsString("storageSet('localStorage', dismissedKey, '1')", $js);
    }

    public function testDockFloatsAboveStickyContentAndBelowTheTour(): void
    {
        $css = $this->asset('public/css/mascot.css');

        $this->assertMatchesRegularExpression('/\.mascot-dock\s*\{[^}]*position:\s*fixed/s', $css);
        $this->assertMatchesRegularExpression('/\.mascot-dock\s*\{[^}]*z-index:\s*1050/s', $css);

        // The sticky top bar is 1045 and the guided tour is 1080, so the dock
        // floats over the former and tucks under the latter.
        $this->assertStringContainsString('z-index: 1080', $this->asset('public/css/mascot-tour.css'));
    }

    /**
     * The wrapper must stay transparent for the same reason as .mascot itself:
     * a backdrop behind the artwork is drawn over the page, not under it.
     */
    public function testDockArtworkWrapperDoesNotPaintABackdrop(): void
    {
        $css = $this->asset('public/css/mascot.css');

        $this->assertSame(
            1,
            preg_match('/\.mascot-dock__art\s*\{([^}]*)\}/s', $css, $m),
            'the .mascot-dock__art rule must exist'
        );
        $this->assertStringNotContainsString('background', $m[1]);
    }

    // ------------------------------------------------- the artwork contract

    /**
     * The validator is the gate a human runs before pasting a file in, so it has
     * to know the same set of files the app asks for.
     */
    public function testTheArtworkValidatorKnowsTheSameContract(): void
    {
        $tool = $this->asset('tools/mascot_assets.php');

        foreach (['hero', 'point-right', 'point-up', 'bust', 'tap-card'] as $pose) {
            $this->assertStringContainsString("'" . $pose . "'", $tool, $pose . ' is missing from the validator contract');
        }

        foreach (['new', 'tip', 'grade-a', 'wow'] as $sticker) {
            $this->assertStringContainsString("'" . $sticker . "'", $tool, 'sticker-' . $sticker . ' is missing from the validator contract');
        }
    }

    // ------------------------------------------------- once-only tour state

    /**
     * THE regression guard for "the tour keeps popping up".
     *
     * Completion used to be keyed by role AND path, so dismissing the tour on
     * one dashboard did nothing for the next dashboard the user opened, and it
     * was only recorded on exit — closing the tab half way through replayed it
     * for ever. It is now keyed by role alone and recorded the moment an
     * automatic tour opens.
     */
    public function testTourIsRememberedPerRoleAndNotPerPage(): void
    {
        $js = $this->asset('public/js/mascot-tour.js');

        $this->assertStringContainsString(
            "return STORAGE_KEY + '.' + role();",
            $js,
            'the tour key must be scoped to the role'
        );
        $this->assertStringNotContainsString(
            "STORAGE_KEY + '.' + role() + '.' + path()",
            $js,
            'keying completion per path is what made the tour repeat on every dashboard'
        );
        $this->assertStringContainsString(
            'if (!force) { markSeen(); }',
            $js,
            'an automatic tour must be recorded as seen when it opens, not when it closes'
        );

        // A browser that blocks localStorage must not get the tour on every
        // navigation either.
        $this->assertStringContainsString('window.sessionStorage', $js);

        // The per-path keys already sitting in returning visitors' browsers
        // have to be cleaned up.
        $this->assertStringContainsString('clearLegacyKeys', $js);

        // Replay is manual only, and must ignore the stored flag.
        $this->assertStringContainsString(
            'window.__startMascotTour = function () { start(true); }',
            $js,
            'the replay button must still start the tour for someone who has already seen it'
        );
    }

    /**
     * The role has to be stated in the markup, because the class-name fallback
     * cannot tell a student dashboard from an admin one.
     */
    public function testDashboardLayoutStatesTheMascotRole(): void
    {
        $this->assertStringContainsString('data-mascot-role', $this->layout());
        $this->assertStringContainsString("'student'", $this->layout());
    }

    // -------------------------------------------------------------- stickers

    /**
     * Every semantic key has to resolve to a documented sticker file. A typo here
     * would silently render nothing, which is exactly the kind of thing that is
     * invisible until someone looks at the page.
     */
    public function testStickerMapResolvesToTheDocumentedFiles(): void
    {
        $expected = [
            'new'    => 'sticker-new',
            'tip'    => 'sticker-tip',
            'done'   => 'sticker-done',
            'award'  => 'sticker-star',
            'top'    => 'sticker-grade-a',
            'locked' => 'sticker-locked',
            'warn'   => 'sticker-alert',
            'wow'    => 'sticker-wow',
        ];

        // Read the map out of the helper rather than hardcoding it twice, so a
        // renamed key fails here instead of quietly rendering nothing.
        $source = (string) file_get_contents(ROOTPATH . 'app/Helpers/mascot_helper.php');

        foreach ($expected as $key => $file) {
            // Whitespace-insensitive: the map is column-aligned, and pinning the
            // exact padding would fail on a cosmetic realignment.
            $this->assertMatchesRegularExpression(
                "/'" . preg_quote($key, '/') . "'\s*=>\s*'" . preg_quote($file, '/') . "'/",
                $source,
                $key . ' should map to ' . $file
            );
        }
    }

    /**
     * A view asks for a meaning, never a file name, so the artwork stays
     * swappable in one place.
     *
     * Note what is *not* asserted: that the file exists. mascot_img() always emits
     * the wrapper and lets the CSS and the load error hide it, which is the
     * graceful-degradation contract the whole system is built on. mascot_exists()
     * is the separate question of whether the art is actually there.
     */
    public function testStickerForRejectsUnknownKeysAndAlwaysWrapsKnownOnes(): void
    {
        $this->assertSame('', mascot_sticker_for('no-such-sticker'), 'an unknown key must render nothing');
        $this->assertSame('', mascot_sticker_for(''));

        $html = mascot_sticker_for('tip');

        $this->assertStringContainsString('mascot mascot--sticker-tip', $html);
        $this->assertStringContainsString('mascot-sticker', $html);
        // Decorative by default: it sits next to text that already says what it
        // means, so announcing it would just repeat the sentence.
        $this->assertStringContainsString('aria-hidden="true"', $html);
        // No inline size, so the placement's own CSS class decides the size.
        $this->assertStringNotContainsString('--mascot-size', $html);
    }

    public function testStickerAnnouncesItselfWhenGivenAltText(): void
    {
        $html = mascot_sticker_for('new', ['alt' => 'New']);

        $this->assertStringContainsString('alt="New"', $html);
        $this->assertStringNotContainsString('aria-hidden="true"', $html);
    }

    /**
     * A sticker rendered without a size used to pick up the 120px default from
     * mascot_img(), which beat the .mascot-sticker CSS rule and made every badge
     * enormous. The size must come from CSS unless a caller asked for one.
     */
    public function testStickerSizeComesFromCssNotAnInlineDefault(): void
    {
        $css = (string) file_get_contents(ROOTPATH . 'public/css/mascot.css');

        $this->assertMatchesRegularExpression(
            '/\.mascot-sticker\s*\{[^}]*--mascot-size:\s*\d+px/s',
            $css,
            'a sizeless sticker needs a CSS size to fall back to'
        );

        // An explicit size still wins, because it is inline.
        $sized = mascot_sticker(['name' => 'sticker-tip', 'size' => 64]);
        $this->assertStringContainsString('--mascot-size:64px', $sized);
    }

    /**
     * The unread marker on an announcement must not disappear just because the
     * sticker artwork is missing. Each of the three announcement lists keeps a
     * text badge as the fallback.
     */
    public function testUnreadMarkerFallsBackToATextBadge(): void
    {
        foreach ([
            'app/Views/student/announcements.php',
            'app/Views/parent/announcements.php',
            'app/Views/teacher/announcements.php',
        ] as $path) {
            $view = (string) file_get_contents(ROOTPATH . $path);

            $this->assertStringContainsString('mascot_exists(\'sticker-new\')', $view, $path);
            $this->assertStringContainsString('mascot_sticker_for(\'new\'', $view, $path);
            $this->assertStringContainsString('badge bg-', $view, $path . ' must keep a text fallback');
        }
    }

    /**
     * The shared empty state runs on every admin list, so it is the single widest
     * placement: one edit gives every "nothing to show" a sticker.
     */
    public function testSharedEmptyStatePlacesASticker(): void
    {
        $partial = (string) file_get_contents(ROOTPATH . 'app/Views/admin/partials/empty_state.php');

        $this->assertStringContainsString('mascot_sticker_for(', $partial);
        $this->assertStringContainsString('mascot-chip', $partial);
        // "Nothing left to do" and "nothing here yet" must not look the same.
        $this->assertStringContainsString("\$emptySticker = \$emptyIsAllDone ? 'done' : 'tip'", $partial);
    }

    public function testStickerChipsAreNotAnimatedInALoop(): void
    {
        $css = (string) file_get_contents(ROOTPATH . 'public/css/mascot.css');

        $this->assertStringContainsString('.mascot-chip', $css);
        // A pop on arrival is fine; a breathing badge beside a sentence is noise.
        $this->assertMatchesRegularExpression(
            '/\.mascot-chip \.mascot\s*\{[^}]*animation:\s*mascot-pop[^}]*both/s',
            $css
        );
    }

}
