<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The first-login welcome modal, the interactive dock, and the poster/dialogue
 * settings behind them.
 *
 * The behaviour these tests pin down is the behaviour that used to be missing:
 *
 *  1. Tappy says something different on every page, instead of being the same
 *     silent pointing hand on all of them.
 *  2. He is reachable. The top-bar tour button was removed, so the dock is now
 *     the only way to replay the tour, and it has to be a real control.
 *  3. The welcome modal gates the tour. Without that handover the tour either
 *     opened behind the modal or recorded itself as seen before being seen.
 *  4. The modal ships hidden and disappears entirely when switched off, because
 *     an undismissable modal is worse than no modal.
 *
 * @internal
 */
final class MascotWelcomeTest extends CIUnitTestCase
{
    private function asset(string $path): string
    {
        return (string) file_get_contents(ROOTPATH . $path);
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper('mascot');
        helper('asset');
    }

    private function modal(): string
    {
        return $this->asset('app/Views/partials/welcome_modal.php');
    }

    private function welcomeJs(): string
    {
        return $this->asset('public/js/mascot-welcome.js');
    }

    private function tourJs(): string
    {
        return $this->asset('public/js/mascot-tour.js');
    }

    /**
     * The reported bug: pressing X on the bubble did nothing, and there was no
     * button to bring Tappy back. Two separate pieces were missing.
     *
     * This test covers the markup and the wiring, which is where the feature
     * actually broke - the server half was in place but nothing ever called it.
     */
    public function testTheDockOffersAWayToHideAndToRestoreTappy(): void
    {
        $dock = $this->dock();

        // 1. The X has to be wired to hiding the character, not just closing the
        //    bubble. data-mascot-dismiss only ever hid the panel, which is why
        //    pressing it looked like it did nothing.
        $this->assertStringContainsString('data-mascot-hide', $dock, 'the X must hide Tappy himself');
        $this->assertStringContainsString(
            'data-mascot-dismiss',
            $dock,
            '"Not now" should still close the bubble without hiding the character'
        );

        // 2. The restore button must EXIST and be a sibling of the dock. Inside the
        //    dock it would carry the same `hidden` attribute as Tappy and there
        //    would be no way back.
        $this->assertStringContainsString('data-mascot-restore', $dock, 'there must be a restore button');

        $dockOpen = strpos($dock, 'class="mascot-dock');
        $dockEnd  = strpos($dock, '</div>', $dockOpen);
        $restore  = strpos($dock, 'data-mascot-restore');

        $this->assertIsInt($restore);
        $this->assertGreaterThan(
            $dockEnd,
            $restore,
            'the restore button must sit outside .mascot-dock, or hiding Tappy hides the way back'
        );
    }

    /** Every part of the chain the browser needs has to be published on the dock. */
    public function testTheDockPublishesWhatTheScriptNeedsToPersistTheChoice(): void
    {
        $dock = $this->dock();

        foreach ([
            'data-mascot-hidden',
            'data-mascot-user',
            'data-mascot-visibility-url',
            'data-mascot-csrf-name',
            'data-mascot-csrf-hash',
        ] as $attribute) {
            $this->assertStringContainsString(
                $attribute,
                $dock,
                $attribute . ' must be on the dock or mascot.js cannot reach the server'
            );
        }
    }

    /**
     * Three defects that left the toggle looking broken while the code "worked".
     *
     * Each was invisible in review because the happy path looked fine; they only
     * show up on a shared computer, a slow connection, or a late image load.
     */
    public function testTheChoiceSurvivesArtworkLoadingAndFailingSaves(): void
    {
        $js = $this->asset('public/js/mascot.js');

        // 1. applyHidden() has to write back to startsHidden. artReady() runs when
        //    the image finishes loading, and used to read a variable frozen at
        //    page-render time - so hiding Tappy during a slow load brought him
        //    straight back a moment later.
        $this->assertMatchesRegularExpression(
            '/function applyHidden\(hidden\)\s*\{[\s\S]{0,600}?startsHidden\s*=\s*hidden/',
            $js,
            'applyHidden() must update startsHidden or artReady() re-shows a hidden Tappy'
        );

        // Declared once, and only once.
        $this->assertSame(
            1,
            preg_match_all('/var\s+startsHidden\s*=/', $js),
            'startsHidden must be declared once; a second declaration overwrites the live one'
        );

        // 2. A signed-in user must take the ACCOUNT's answer. Reading the browser
        //    copy here is what let one person's hidden flag follow the next
        //    person onto the same machine.
        $this->assertMatchesRegularExpression(
            '/var startsHidden\s*=\s*visibilityUrl\s*\?/',
            $js,
            'when there is an account to ask, the account must win over localStorage'
        );
        $this->assertStringContainsString(
            "dock.getAttribute('data-mascot-user')",
            $js,
            'the browser-side mirror must be keyed per account'
        );

        // 3. A non-2xx response is a failed save, not a success. fetch only
        //    rejects on a network error, so without res.ok the rejection handler
        //    never runs and a 500 looks exactly like a working button.
        $this->assertStringContainsString('res.ok', $js, 'the save response status is never checked');
        $this->assertStringContainsString(
            'applyHidden(!hidden)',
            $js,
            'a failed save must put the page back the way the account actually describes it'
        );
    }

    /** The per-account scope has to come from a helper that handles guests. */
    public function testTheAccountScopeHelperFallsBackForGuests(): void
    {
        $helper = $this->asset('app/Helpers/mascot_helper.php');

        $this->assertStringContainsString('function mascot_user_scope_key', $helper);
        // Guests have no id, and a scope of "0" would silently merge them with
        // whoever does have id 0.
        $this->assertStringContainsString("if (! auth()->loggedIn())", $helper);
        $this->assertStringContainsString("return '';", $helper);
    }

    /**
     * The script half. These are the exact failures that made the button inert:
     * no handler bound, and artReady() revealing the dock on every page load and
     * quietly undoing the choice.
     */
    public function testTheScriptBindsHideAndRestoreAndRespectsTheStoredChoice(): void
    {
        $js = $this->asset('public/js/mascot.js');

        $this->assertStringContainsString('data-mascot-restore', $js, 'the restore button is never wired up');
        $this->assertStringContainsString('data-mascot-hide', $js, 'the hide button is never wired up');

        // Looked up on the document, because it is a sibling of the dock and
        // dock.querySelector() would never find it.
        $this->assertMatchesRegularExpression(
            '/document\.querySelector\(\s*\'\[data-mascot-restore\]\'\s*\)/',
            $js,
            'the restore button must be looked up on the document, not inside the dock'
        );

        // Both directions have to reach the server, with the CSRF pair, or the
        // choice dies with the session.
        $this->assertStringContainsString('visibilityUrl', $js);
        $this->assertStringContainsString('method: \'POST\'', $js);
        $this->assertStringContainsString('body.append(csrfName, csrfHash)', $js);

        // The regression that would silently break it all again.
        $this->assertMatchesRegularExpression(
            '/function artReady\(\)\s*\{[\s\S]{0,400}?if \(startsHidden\)/',
            $js,
            'artReady() must not reveal the dock when the account has hidden Tappy'
        );
    }

    /** The server half has to exist, reject nonsense, and refuse guests. */
    public function testTheVisibilityEndpointIsWiredAndGuarded(): void
    {
        $routes = $this->asset('app/Config/Routes.php');
        $ctrl   = $this->asset('app/Controllers/Api/MascotVisibility.php');

        $this->assertStringContainsString('api/mascot/visibility', $routes);
        $this->assertStringContainsString('Api\MascotVisibility::update', $routes);

        // A guest must be told no, not silently "succeed" - otherwise the local
        // copy claims a preference was saved when no account exists to hold it.
        $this->assertStringContainsString('Unauthorized', $ctrl);

        // Only 0 and 1 are accepted, so a malformed request cannot quietly put
        // Tappy back on screen for someone who dismissed him.
        $this->assertStringContainsString("in_array((string) \$raw, ['0', '1'], true)", $ctrl);
    }

    /**
     * The preference has to be per user, which means a column on users. Without
     * the migration the helper catches the error and reports "not hidden", so
     * the feature silently does nothing - which is exactly the symptom reported.
     */
    public function testTheAccountLevelPreferenceIsBackedByAMigration(): void
    {
        $migrations = glob(APPPATH . 'Database/Migrations/*AddMascotHiddenToUsers.php');

        $this->assertNotEmpty($migrations, 'users.mascot_hidden needs a migration');
        $this->assertStringContainsString("addColumn('users'", (string) file_get_contents($migrations[0]));

        // The helper must read the account, not a browser-scoped value.
        $this->assertStringContainsString('mascot_hidden', $this->asset('app/Helpers/mascot_helper.php'));
    }

    /**
     * The new artwork classes have to be declared in BOTH places the project
     * keeps them: the validator's contract and the helper that maps meaning to
     * file. A file in one and not the other is either reported as undocumented or
     * silently never rendered.
     */
    public function testTheNewArtworkClassesAreDeclaredInTheContractAndTheHelper(): void
    {
        $tool    = $this->asset('tools/mascot_assets.php');
        $helper  = $this->asset('app/Helpers/mascot_helper.php');

        // Contract lists.
        $this->assertStringContainsString("'megaphone', 'envelope', 'medal'", $tool);
        $this->assertStringContainsString("'audit', 'backup'", $tool);

        // Every new class has to be represented, or the validator reports the
        // files as "undocumented" the moment they are pasted in.
        foreach (['logo' => 'logo-', 'poster' => 'poster-', 'band' => 'divider-', 'pattern' => 'pattern-'] as $kind => $prefix) {
            $this->assertStringContainsString("'{$prefix}'", $tool, "no prefix for {$kind} files");
            $this->assertStringContainsString("'{$kind}'", $tool, "the {$kind} class is missing from the contract");
        }

        // The meaning-to-file maps, so a view can ask for 'backup' without
        // knowing the file is called sticker-backup.png.
        foreach (['saved', 'upload', 'question', 'error', 'pending', 'settings', 'audit', 'backup'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $helper,
                "mascot_sticker_for('{$key}') is not mapped to a file"
            );
        }

        foreach (['megaphone', 'envelope', 'medal'] as $pose) {
            $this->assertStringContainsString(
                "'{$pose}'",
                $helper,
                "the {$pose} pose is not offered to the admin custom-message editor"
            );
        }
    }

    /**
     * The new accessors, once the artwork exists.
     *
     * Written before the art was generated, when every one of these correctly
     * returned ''. The empty-string behaviour is still required, but it can no
     * longer be proved with the real names, so it is proved with names that do
     * not exist - the same code path.
     */
    public function testTheNewAccessorsResolveTheArtworkAndRefuseUnknownKeys(): void
    {
        $this->assertStringContainsString('logo-mark.png', mascot_logo_for('mark'));
        $this->assertStringContainsString('logo-mark-mono.png', mascot_logo_for('mono'));

        foreach (['enroll', 'about', 'announce', 'support'] as $poster) {
            $this->assertStringContainsString(
                'poster-' . $poster . '.png',
                mascot_poster_for($poster),
                $poster . ' poster is not resolving'
            );
        }

        $this->assertStringContainsString('divider-band.png', mascot_band_url());
        $this->assertStringContainsString('pattern-doodle.png', mascot_pattern_url());

        // The band wrapper is decorative, carries the URL as a custom property,
        // and is never announced.
        $band = mascot_band_divider();
        $this->assertStringContainsString('--mascot-band-image:url(', $band);
        $this->assertStringContainsString('aria-hidden="true"', $band);

        // A poster banner is a stacked block, never a background for text.
        $this->assertStringContainsString('mascot-poster-banner', mascot_poster_banner('enroll'));
        $this->assertStringContainsString('mascot-poster-art', mascot_poster_banner('enroll'));

        // The mono mark is a different file from the colour one, not the same
        // request rendered twice.
        $this->assertNotSame(mascot_logo_for('mark'), mascot_logo_for('mono'));

        // An unknown key returns nothing rather than falling back to something
        // arbitrary. This is the same path that returned '' for every name while
        // the artwork was missing, and it is why the site degrades cleanly.
        $this->assertSame('', mascot_poster_for('nonsense'));
        $this->assertSame('', mascot_dialog_art('nonsense'));
        $this->assertSame('', mascot_sticker_for('nonsense'));
        $this->assertSame('', mascot_poster_banner('nonsense'));

        // A known key whose file is not installed: the "artwork not pasted in
        // yet" case, which has to be silent.
        $this->assertSame('', mascot_band_url('nonexistent'));
        $this->assertSame('', mascot_pattern_url('nonexistent'));
        $this->assertSame('', mascot_logo_for('nonexistent'));
    }

    /**
     * A tiling pattern must never be cropped to its alpha bounds, and this is
     * the guard that stops that happening silently.
     */
    public function testTheOptimiserWillNotCropAPattern(): void
    {
        $tool = $this->asset('tools/mascot_optimize.php');

        $this->assertStringContainsString("MASCOT_NO_TRIM_PREFIXES = ['pattern-']", $tool);
        $this->assertStringContainsString('function mascot_keeps_full_canvas', $tool);
        $this->assertStringContainsString("if (\$keepCanvas) {", $tool);

        // A heavy file that is already within the dimension target has to be
        // re-encoded, not skipped - that was how 9 MB of stickers got shipped
        // rendered at 28-64px.
        $this->assertStringContainsString('DEFAULT_MAX_WEIGHT', $tool);
        $this->assertStringContainsString('$tooHeavy = $before > $maxWeight', $tool);

        // And it must only ever scale down. A heavy but correctly sized file
        // would otherwise be upscaled by the old dimension-only arithmetic.
        $this->assertStringContainsString('$scale = min(1.0,', $tool);
    }

    /**
     * The border check was sampling the wrong pixels entirely.
     *
     * It walked the outer ring of a 24x24 sampling grid, which sits about 2%
     * inside the image, so before the optimiser trimmed the transparent margin
     * it read "ok" and afterwards it landed on the stickers' own white outline
     * and reported a painted checkerboard on three clean files.
     */
    public function testTheCheckerboardCheckSamplesTheRealBorder(): void
    {
        $tool = $this->asset('tools/mascot_assets.php');

        $this->assertStringContainsString('$borderTotal', $tool);
        $this->assertStringContainsString('$borderLight', $tool);
        // A proportion of the border, not a raw count: a 640x409 sticker and a
        // 548x640 one have very different perimeter lengths.
        $this->assertStringContainsString(
            '$borderLight / $borderTotal * 100',
            $tool,
            'the verdict must be a percentage so it means the same for every aspect ratio'
        );
    }

    private function dock(): string
    {
        return $this->asset('app/Views/partials/mascot_dock.php');
    }

    /**
     * system_settings ships with the base schema rather than a migration, so the
     * throwaway test database does not have it. The custom-message store is
     * genuinely database backed, so the table is created here to exercise the
     * real read/write path instead of stubbing the model out.
     */
    /**
     * The public dock used to show one hard-coded greeting on every public page,
     * so Tappy had nothing to say on registration or on CHILDPRO and GAD. Each of
     * those now needs a line of its own.
     */
    public function testPublicPagesHaveTheirOwnLine(): void
    {
        foreach (['register', 'teacher/register', 'childpro', 'gad'] as $segment) {
            $line = mascot_line_for($segment);

            $this->assertNotSame(
                'Ask me anything about this page.',
                $line['text'],
                $segment . ' should have a line written for it, not the generic fallback'
            );
            $this->assertNotSame('', trim($line['title']), $segment . ' needs a heading');
            $this->assertNotSame('', trim($line['text']), $segment . ' needs a message');
            // mascot_exists(), not mascot_file_name(): the latter only builds a
            // filename and would happily "confirm" a pose that was never drawn.
            $this->assertTrue(
                mascot_exists($line['pose']),
                $segment . ' points at a pose whose artwork does not exist'
            );
        }
    }

    /**
     * The front page is the root URL, so it has no first path segment for
     * mascot_segment_key() to report. The dock normalises that to "home"; without
     * it the landing page would quietly fall through to the generic fallback.
     */
    public function testTheFrontPageResolvesToItsOwnLine(): void
    {
        $this->assertNotSame(
            'Ask me anything about this page.',
            mascot_line_for('home')['text'],
            'the landing page needs a line of its own'
        );

        $this->assertStringContainsString(
            "'home'",
            $this->dock(),
            'the dock must normalise the empty segment to "home"'
        );
    }

    /** The admin content manager gets a line that says what the page is for. */
    public function testTheChildproGadAdminPageHasItsOwnLine(): void
    {
        $line = mascot_line_for('admin/childpro-gad');

        $this->assertSame('CHILDPRO / GAD', $line['title']);
        $this->assertStringContainsString('content', strtolower($line['text']));
    }

    /**
     * An administrator's wording must win over the built-in line, and only for the
     * exact page it was written for: a message set on /gad must not leak onto
     * other pages through the longest-prefix matching in mascot_line_for().
     *
     * The normalisation rules are exercised directly rather than through the
     * database, so these hold without a settings table being present.
     */
    public function testAnAdministratorMessageIsAcceptedAndTrimmed(): void
    {
        $stored = mascot_line_override_normalise([
            'pose'  => 'celebrate',
            'title' => '  Nutrition Week  ',
            'text'  => '  Healthy lunches all term.  ',
        ]);

        $this->assertNotNull($stored);
        $this->assertSame('celebrate', $stored['pose']);
        $this->assertSame('Nutrition Week', $stored['title'], 'the heading should be trimmed');
        $this->assertSame('Healthy lunches all term.', $stored['text'], 'the message should be trimmed');
    }

    /** Blank fields mean "no override", so an empty bubble can never be shown. */
    public function testAnEmptyMessageIsTreatedAsUnset(): void
    {
        $this->assertNull(mascot_line_override_normalise(['pose' => 'hero', 'title' => '   ', 'text' => '']));
        $this->assertNull(mascot_line_override_normalise(['pose' => 'hero', 'title' => '', 'text' => "  \n "]));

        // A message with only a body is still worth showing; the heading is filled
        // in rather than left blank.
        $bodyOnly = mascot_line_override_normalise(['title' => '', 'text' => 'Something to say.']);
        $this->assertNotNull($bodyOnly);
        $this->assertSame('Tap n Track', $bodyOnly['title']);
        $this->assertSame('Something to say.', $bodyOnly['text']);
    }

    /** Corrupt or unexpected stored data must fall back, never render. */
    public function testUnusableStoredDataFallsBackToTheDefault(): void
    {
        $this->assertNull(mascot_line_override_normalise(null));
        $this->assertNull(mascot_line_override_normalise('just a string'));
        $this->assertNull(mascot_line_override_normalise(42));
        $this->assertNull(mascot_line_override_normalise(['a', 'b']));

        // Non-string title/text are ignored rather than cast into a type error.
        $this->assertNull(mascot_line_override_normalise(['title' => ['x'], 'text' => ['y']]));
    }

    /**
     * A pose that no longer exists must not be honoured, or the public page
     * renders an empty bubble with no obvious cause.
     */
    public function testAnUnknownPoseFallsBackRatherThanBreakingThePage(): void
    {
        $stored = mascot_line_override_normalise([
            'pose'  => 'definitely-not-a-real-pose',
            'title' => 'Hello',
            'text'  => 'Something to say.',
        ]);

        $this->assertNotNull($stored);
        $this->assertNotSame('definitely-not-a-real-pose', $stored['pose']);
        $this->assertTrue(mascot_exists($stored['pose']), 'the fallback pose must have artwork');
    }

    /** The segment comes from the URL and ends up in a settings key. */
    public function testOverrideKeysAreSanitised(): void
    {
        $this->assertSame('mascot_line_gad', mascot_line_override_key('gad'));
        $this->assertSame('mascot_line_admin_students', mascot_line_override_key('admin/students'));

        // Anything that could break out of the key is flattened away: lowercased,
        // every run of unsupported characters collapsed to a dash, surrounding
        // dashes trimmed. The quote and the semicolon cannot survive.
        $hostile = mascot_line_override_key("A'; DROP TABLE--");

        $this->assertStringStartsWith('mascot_line_', $hostile);
        $this->assertStringNotContainsString("'", $hostile);
        $this->assertStringNotContainsString(';', $hostile);
        $this->assertStringNotContainsString(' ', $hostile);
        $this->assertMatchesRegularExpression('/^mascot_line_[a-z0-9_\-]+$/', $hostile);

        // A segment made only of separators carries no page identity, so it is
        // rejected rather than stored as a junk key.
        $this->assertSame('', mascot_line_override_key('///'));
        $this->assertSame('', mascot_line_override_key('---'));
        $this->assertSame('', mascot_line_override_key('   '));
    }

    /** The admin pose picker must only offer artwork that exists. */
    public function testEveryOfferedPoseHasArtwork(): void
    {
        $choices = mascot_pose_choices();

        $this->assertNotEmpty($choices, 'the pose picker must never be empty');

        foreach (array_keys($choices) as $pose) {
            $this->assertTrue(
                mascot_exists($pose),
                $pose . ' is offered to the admin but has no artwork'
            );
        }
    }

    /**
     * The content manager has to be able to reach the editor, so the form fields
     * and the save handler must both exist.
     */
    public function testTheAdminContentManagerCanEditTappysMessage(): void
    {
        $view = $this->asset('app/Views/admin/childpro_gad.php');
        $ctrl = $this->asset('app/Controllers/Admin/ChildProGad.php');

        foreach (['mascot_title', 'mascot_text', 'mascot_pose'] as $field) {
            $this->assertStringContainsString(
                'name="' . $field . '"',
                $view,
                $field . ' must be editable in the admin form'
            );
            $this->assertStringContainsString(
                "getPost('" . $field . "')",
                $ctrl,
                $field . ' must be read and saved by the controller'
            );
        }

        // Both public tabs get an editor, not just one.
        $this->assertSame(
            2,
            substr_count($view, 'name="mascot_title"'),
            'CHILDPRO and GAD each need their own message editor'
        );

        $this->assertStringContainsString('mascot_line_override_save', $ctrl);
    }

    private function mascotCss(): string
    {
        return $this->asset('public/css/mascot.css');
    }

    // ------------------------------------------------- contextual dialogue

    /**
     * Every mapped page must resolve to a real, installed pose and non-empty copy.
     *
     * A typo in the map would otherwise only show up as a broken image in the
     * corner of one screen.
     */
    public function testEveryPageGetsARealPoseAndARealLine(): void
    {
        $pages = [
            'admin/dashboard', 'admin/students', 'admin/teachers', 'admin/sections',
            'admin/subjects', 'admin/grades', 'admin/analytics', 'admin/schedule',
            'admin/announcements', 'admin/materials', 'admin/audit-log',
            'admin/id-cards', 'admin/settings', 'admin/backups',
            'student/dashboard', 'student/grades', 'student/schedule',
            'student/id-card', 'student/materials', 'student/announcements',
            'teacher/dashboard', 'teacher/attendance', 'teacher/grades',
            'teacher/sections', 'teacher/schedule', 'teacher/messages', 'teacher/sned',
            'parent/dashboard', 'parent/grades', 'parent/announcements',
            'notifications', 'profile',
        ];

        $seenPoses = [];

        foreach ($pages as $page) {
            $line = mascot_line_for($page);

            $this->assertNotSame('', $line['pose'], $page . ' must have a pose');
            $this->assertNotSame('', $line['title'], $page . ' must have a title');
            $this->assertNotSame('', $line['text'], $page . ' must have something to say');
            $this->assertTrue(
                mascot_exists($line['pose']),
                $page . ' refers to ' . $line['pose'] . '.png, which is not installed'
            );

            $seenPoses[$line['pose']] = true;
        }

        // The map is supposed to spread across the character, not point one way.
        $this->assertGreaterThanOrEqual(6, count($seenPoses), 'the dock map should use several poses');
    }

    /**
     * An unmapped page still has to get a line. A slightly wrong line is fine; an
     * empty speech bubble is not.
     */
    public function testUnknownPagesStillGetAnAreaFallback(): void
    {
        $mapped = mascot_line_for('admin/analytics');
        $unmapped = mascot_line_for('admin/some-page-nobody-wrote-steps-for');

        $this->assertNotSame($mapped['text'], $unmapped['text']);
        $this->assertNotSame('', $unmapped['text']);
        $this->assertTrue(mascot_exists($unmapped['pose']));

        // A page with no area at all still must not come back empty.
        $nowhere = mascot_line_for('completely/unknown');
        $this->assertNotSame('', $nowhere['text']);
        $this->assertTrue(mascot_exists($nowhere['pose']));
    }

    public function testLongestPrefixWins(): void
    {
        // 'admin/grades' is more specific than the 'admin' area fallback.
        $this->assertSame('Grades', mascot_line_for('admin/grades')['title']);
        $this->assertNotSame('Grades', mascot_line_for('admin/anything-else')['title']);
    }

    // ------------------------------------------------------- speech bubbles

    public function testSayProducesASpeechBubbleWithACharacter(): void
    {
        $html = mascot_say([
            'text'  => 'Sign in and I will show you around.',
            'title' => 'Hello',
            'pose'  => 'hero',
        ]);

        $this->assertStringContainsString('data-mascot-say', $html);
        $this->assertStringContainsString('mascot-say--right', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('data-mascot-type', $html);
        $this->assertStringContainsString('mascot--hero', $html);
        $this->assertStringContainsString('mascot-say__art', $html);
    }

    public function testSayEscapesTheCopyAndDropsAnEmptyComponent(): void
    {
        $html = mascot_say(['text' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        // No line means no component at all - never an empty bubble.
        $this->assertSame('', mascot_say(['text' => '   ']));
        $this->assertSame('', mascot_say([]));
    }

    /**
     * Only the two whitelisted actions may reach the markup, so a caller cannot
     * inject an arbitrary attribute name.
     */
    public function testSayOnlyEmitsKnownActions(): void
    {
        $html = mascot_say([
            'text'    => 'Hello',
            'actions' => [
                ['label' => 'Tour', 'action' => 'tour'],
                ['label' => 'Bye', 'action' => 'close'],
                ['label' => 'Evil', 'action' => 'onclick="steal()"'],
                ['label' => '', 'action' => 'tour'],
            ],
        ]);

        $this->assertStringContainsString('data-mascot-tour', $html);
        $this->assertStringContainsString('data-mascot-say-close', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('Evil', $html);
    }

    public function testSayKeepsACssVariableSize(): void
    {
        $html = mascot_say(['text' => 'Hi', 'pose' => 'hero', 'size' => 'var(--mascot-hero)']);

        $this->assertStringContainsString('--mascot-size:var(--mascot-hero)', $html);
    }

    // ------------------------------------------------------- the dock as UI

    /**
     * The dock artwork is a <button>, not a clickable <div>. This is the only
     * route back into the tour now that the top-bar button is gone, so it has to
     * be keyboard reachable and properly labelled.
     */
    public function testDockTriggerIsARealAccessibleButton(): void
    {
        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-mascot-trigger/s',
            $this->dock(),
            'the dock artwork must be a real button'
        );
        $this->assertStringContainsString('aria-expanded="false"', $this->dock());
        $this->assertStringContainsString('aria-controls=', $this->dock());
        $this->assertStringContainsString('aria-label=', $this->dock());
    }

    public function testDockExposesThePagePoseAndKeyForScripting(): void
    {
        $dock = $this->dock();

        $this->assertStringContainsString('data-dock-pose=', $dock);
        $this->assertStringContainsString('data-dock-key=', $dock);
        // No hard-coded pose: it has to come from the per-page map.
        $this->assertStringNotContainsString("'pose'  => 'point-right'", $dock);
    }

    public function testDockOnlyOffersTheTourReplayOnDashboards(): void
    {
        $this->assertStringContainsString("\$dockContext === 'dashboard'", $this->dock());
        $this->assertStringContainsString('data-mascot-tour', $this->dock());
    }

    /**
     * The wrapper stays pointer-events: none so Tappy never swallows a click meant
     * for the page; the trigger and the panel opt back in.
     */
    public function testDockKeepsTheWrapperTransparentToClicks(): void
    {
        $css = $this->mascotCss();

        $this->assertMatchesRegularExpression('/\.mascot-dock\s*\{[^}]*pointer-events:\s*none/s', $css);
        $this->assertMatchesRegularExpression('/\.mascot-dock__trigger\s*\{[^}]*pointer-events:\s*auto/s', $css);
        $this->assertMatchesRegularExpression('/\.mascot-dock__panel\s*\{[^}]*pointer-events:\s*auto/s', $css);
    }

    public function testDockIsSizedFromTheScaleSoItCanShrinkOnAPhone(): void
    {
        $this->assertStringContainsString("'size'    => 'var(--mascot-dock)'", $this->dock());

        $css = $this->mascotCss();
        $this->assertMatchesRegularExpression('/--mascot-dock:\s*\d+px/', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\)\s*\{[^}]*--mascot-dock:\s*\d+px/s',
            $css,
            'the dock has to shrink on a phone, or it eats the content'
        );
    }


    // ------------------------------------------------------ welcome modal

    /**
     * Ships hidden. The partial is rendered on every dashboard, and a modal that
     * is visible before the script has decided whether to greet anyone would
     * flash on every single page load.
     */
    public function testWelcomeModalShipsHiddenAndIsWiredUp(): void
    {
        $modal = $this->modal();

        $this->assertStringContainsString('data-mascot-welcome', $modal);
        // A bounded lazy match rather than [^>]*: the style attribute on this very
        // tag contains a PHP close tag, and its '>' would end the pattern early.
        $this->assertMatchesRegularExpression(
            '/data-mascot-welcome\b[\s\S]{0,300}?\n\s+hidden/',
            $modal,
            'the modal must ship hidden, or it flashes on every dashboard load'
        );
        $this->assertStringContainsString('role="dialog"', $modal);
        $this->assertStringContainsString('aria-modal="true"', $modal);
        $this->assertStringContainsString('aria-labelledby=', $modal);

        $layout = $this->asset('app/Views/dashboard_layout.php');
        $this->assertStringContainsString("view('partials/welcome_modal')", $layout);
        $this->assertStringContainsString('js/mascot-welcome.js', $layout);
        $this->assertStringContainsString('css/mascot-welcome.css', $layout);
    }

    /**
     * A public page must never load the modal: it is a first-dashboard-visit thing.
     */
    public function testPublicPagesDoNotLoadTheWelcomeModal(): void
    {
        $this->assertStringNotContainsString('mascot-welcome', $this->asset('app/Views/layout.php'));
    }

    public function testWelcomeModalIsBrandedAndMultiStep(): void
    {
        $modal = $this->modal();

        $this->assertStringContainsString('LPHS2.png', $modal, 'the school logo belongs in the header');
        $this->assertStringContainsString('Cauayan South Central School', $modal);

        $this->assertSame(
            4,
            substr_count($modal, 'data-welcome-slide'),
            'the greeting should be four slides: hello, what you can do, a word, goodbye'
        );
        $this->assertStringContainsString('data-welcome-next', $modal);
        $this->assertStringContainsString('data-welcome-prev', $modal);
        $this->assertStringContainsString('data-welcome-skip', $modal);
        $this->assertStringContainsString('data-welcome-never', $modal);
    }

    /**
     * Two reported bugs, one cause each.
     *
     * 1. The backdrop tiled the school logo across the whole viewport. The
     *    sign-in card may be watermarked, but a dialog in the middle of the app
     *    has to match the other modals, so the repeating background is gone and
     *    only a plain scrim is left.
     * 2. The farewell slide drew Tappy at roughly three times the size of the
     *    opening slide. The size token was declared on .mascot-welcome__art, which
     *    only the first slide has, so on the last slide the var() resolved to
     *    nothing, the --mascot-size declaration was dropped at computed-value
     *    time, and the image fell back to its intrinsic pixel dimensions. The
     *    token now lives on .mascot-welcome, so both slides inherit one value.
     *
     * The size assertions look for the shared token rather than a pixel count on
     * purpose: what must not regress is that the two slides agree, not the exact
     * number.
     */
    public function testTheBackdropIsPlainAndEverySlideSharesOneArtSize(): void
    {
        $css    = $this->asset('public/css/mascot-welcome.css');
        $modal  = $this->modal();

        // 1. No tiled logo, and the scrim is a plain colour, not an image.
        $this->assertStringNotContainsString('--mascot-welcome-watermark', $css . $modal);
        $this->assertStringNotContainsString('background-image', $css);
        $this->assertStringNotContainsString('background-repeat', $css);
        $this->assertMatchesRegularExpression(
            '/\.mascot-welcome__backdrop\s*\{[^}]*background:\s*rgba\(/',
            $css,
            'the backdrop should be the plain scrim the other modals use'
        );

        // 2. One size token, declared on the dialog, not on a single slide.
        $this->assertMatchesRegularExpression(
            '/\.mascot-welcome\s*\{[^}]*--mascot-welcome-art:\s*\d+px/',
            $css,
            '--mascot-welcome-art must be declared on the dialog so every slide inherits it'
        );
        // The token must not be re-declared on a rule that only covers one slide,
        // which is what left the farewell image un-sized.
        $this->assertDoesNotMatchRegularExpression(
            '/\.mascot-welcome__(?:art|slide--final)\s*\{[^}]*--mascot-welcome-art/',
            $css,
            'the art token must not be scoped to a single slide'
        );

        // Both artwork slides must be sized by the shared token...
        $this->assertSame(
            2,
            substr_count($modal, "'size'    => 'var(--mascot-welcome-art)'"),
            'the hero and the farewell pose should both be sized by the shared token'
        );

        // ...and both must sit in the same .mascot-welcome__art wrapper, which is
        // what actually applies the token to them.
        $this->assertSame(
            2,
            substr_count($modal, 'class="mascot-welcome__art"'),
            'both artwork slides should use the same art wrapper'
        );
    }

    /**
     * mascot_img writes a numeric 'size' out as an inline --mascot-size, and an
     * inline declaration outranks the stylesheet. The dialog declares its own
     * scale, so no artwork in it may hard-code a pixel value: an inline 64px on a
     * list icon silently ignores the 40px rule sitting next to it, and the two
     * drift apart without anything failing.
     *
     * Passing a var() token is fine and is what the two hero poses do. That emits
     * an inline custom property, but it resolves to the same value the stylesheet
     * would have produced, so the slides cannot disagree.
     */
    public function testNoArtworkInTheDialogHardCodesASize(): void
    {
        preg_match_all('/mascot_img\(\[([^\]]*)\]\)/s', $this->modal(), $calls);

        $this->assertGreaterThan(0, count($calls[1]), 'expected the dialog to render artwork');

        foreach ($calls[1] as $call) {
            $this->assertDoesNotMatchRegularExpression(
                '/\'size\'\s*=>\s*[\'"]?\d/',
                $call,
                'sizes belong in mascot-welcome.css so the dialog has a single scale'
            );
        }
    }

    /**
     * The whole partial returns nothing when the admin has switched it off, or
     * when there is no artwork. Both are checked before any markup is emitted.
     */
    public function testWelcomeModalCanBeSwitchedOffCompletely(): void
    {
        $this->assertStringContainsString("! \$welcome['enabled']", $this->modal());
        $this->assertStringContainsString("! mascot_exists('hero')", $this->modal());
    }

    // --------------------------------------------- once-only and the handover

    public function testWelcomeModalIsKeyedByRole(): void
    {
        $js = $this->welcomeJs();

        $this->assertStringContainsString('data-welcome-role', $js);
        $this->assertStringContainsString('.seen.', $js);
        $this->assertStringContainsString('.never.', $js);
        // Both stores: localStorage for "never again", sessionStorage for "this visit".
        $this->assertStringContainsString("storageSet('localStorage'", $js);
        $this->assertStringContainsString("storageSet('sessionStorage'", $js);
    }

    /**
     * The handover. mascot-tour.js polls for this exact attribute, so the two files
     * are coupled by string and a rename in one place would silently break the
     * tour forever.
     */
    public function testTourWaitsForTheWelcomeModalToClose(): void
    {
        $tour = $this->tourJs();

        $this->assertStringContainsString('[data-mascot-welcome]', $tour);
        // The tour reads the attribute through dataset, the modal writes it with
        // setAttribute. Those are the same attribute: data-mascot-welcome-dismissed.
        $this->assertStringContainsString('mascotWelcomeDismissed', $tour);
        $this->assertStringContainsString('data-mascot-welcome-dismissed', $this->welcomeJs());

        // The wait must be bounded, or a modal script that fails to load would mean
        // no tour, ever.
        $this->assertStringContainsString('waited > 60000', $tour);
    }

    /**
     * Already-greeted users must still release the tour. If the modal returns early
     * without setting the flag, the tour would wait 60s on every later page load.
     */
    public function testAlreadyGreetedStillReleasesTheTour(): void
    {
        $js = $this->welcomeJs();

        $already = strpos($js, 'alreadyGreeted');
        $release = strpos($js, 'data-mascot-welcome-dismissed', $already);

        $this->assertNotFalse($already);
        $this->assertNotFalse($release);
        $this->assertGreaterThan($already, $release, 'the flag must be set on the early-return path too');
    }


    // ------------------------------------------------ z-index, motion, print

    /**
     * The stacking ladder: sticky bar < dock < tour < welcome modal. The welcome
     * modal has to win, or the greeting appears behind Tappy and the tour.
     */
    public function testStackingOrderIsPreserved(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.mascot-dock\s*\{[^}]*z-index:\s*1050/s',
            $this->mascotCss()
        );
        $this->assertMatchesRegularExpression(
            '/\.mascot-tour\s*\{[^}]*z-index:\s*1080/s',
            $this->asset('public/css/mascot-tour.css')
        );
        $this->assertMatchesRegularExpression(
            '/\.mascot-welcome\s*\{[^}]*z-index:\s*1090/s',
            $this->asset('public/css/mascot-welcome.css')
        );
    }

    public function testNothingMascotShapedPrintsOnPaper(): void
    {
        $this->assertMatchesRegularExpression(
            '/@media print\s*\{[\s\S]*?\.mascot-dock\s*\{[^}]*display:\s*none/',
            $this->mascotCss()
        );
        $this->assertStringContainsString('@media print', $this->asset('public/css/mascot-tour.css'));
        $this->assertStringContainsString('@media print', $this->asset('public/css/mascot-welcome.css'));
    }

    /**
     * Motion has to be optional. The reveal animates opacity, so it has to be reset
     * rather than merely un-animated, or someone who has asked their OS for less
     * movement is left looking at an invisible mascot.
     */
    public function testNewMotionIsCoveredByReducedMotion(): void
    {
        $css = $this->mascotCss();

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);

        foreach (['mascot-say__art', 'mascot-dock__trigger::before', 'mascot-say__bubble'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector . ' should be styled');
        }

        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce\)\s*\{[\s\S]*?\.mascot--reveal\s*\{[^}]*opacity:\s*1/',
            $css
        );
    }

    // -------------------------------------------------------- the sign-in page

    /**
     * The complaint that started this work: Tappy sat inside the sign-in card, on
     * the dark blue header, where he stretched it and read as decoration.
     */
    public function testMascotIsNoLongerInsideTheLoginCard(): void
    {
        $login = $this->asset('app/Views/auth/login.php');

        $this->assertStringNotContainsString('mascot-login', $login);
        $this->assertStringContainsString('login-mascot', $login);
        $this->assertStringContainsString('mascot_say(', $login, 'he should have something to say on the sign-in page');

        // Positioned inside .login-container but after .login-card has closed.
        $cardClose = strpos($login, '    </div>');
        $this->assertNotFalse($cardClose);
        $this->assertGreaterThan(
            $cardClose,
            strpos($login, 'class="login-mascot"'),
            'the mascot must be a sibling of the card, not a child of it'
        );
    }


    // -------------------------------------------------------------- settings

    public function testWelcomeContentIsEditableFromSettings(): void
    {
        $routes = $this->asset('app/Config/Routes.php');
        $this->assertStringContainsString('settings/update-welcome-modal', $routes);
        $this->assertStringContainsString('Admin\\Settings::updateWelcomeModal', $routes);

        $controller = $this->asset('app/Controllers/Admin/Settings.php');
        $this->assertStringContainsString('public function updateWelcomeModal()', $controller);
        $this->assertStringContainsString('is_any_admin()', $controller);

        $view = $this->asset('app/Views/admin/settings.php');
        $this->assertStringContainsString('admin/settings/update-welcome-modal', $view);
        $this->assertStringContainsString('welcome_enabled', $view);
        // The per-role names are built in a loop, so the literal key is asserted
        // against the controller rather than the markup.
        $this->assertStringContainsString("'welcome_' . \$field . '_' . \$role", $controller);
        $this->assertStringContainsString("'welcome_poster_' . \$role", $controller);
        $this->assertStringContainsString('name="welcome_dialogue_<?=', $view);
        $this->assertStringContainsString('name="welcome_title_<?=', $view);
        $this->assertStringContainsString('name="welcome_body_<?=', $view);
    }

    /**
     * An unset key must not overwrite a saved value with nothing, and a fresh
     * install with no rows at all must still produce a usable greeting.
     */
    public function testWelcomeCopyFallsBackToDefaults(): void
    {
        $copy = mascot_welcome_copy('admin');

        $this->assertTrue($copy['enabled'], 'a fresh install should show the greeting');
        $this->assertSame('admin', $copy['role']);
        $this->assertNotSame('', $copy['title']);
        $this->assertNotSame('', $copy['body']);
        $this->assertNotSame('', $copy['dialogue']);
        $this->assertNotEmpty($copy['bullets']);

        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            $forRole = mascot_welcome_copy($role);
            $this->assertNotSame('', $forRole['title'], $role . ' needs a title');
            $this->assertNotEmpty($forRole['bullets'], $role . ' needs bullets');
        }
    }

    public function testUnknownRoleFallsBackToTheAdminDashboard(): void
    {
        $this->assertSame('admin', mascot_welcome_copy('wizard')['role']);
    }

    public function testTheChangeIsAudited(): void
    {
        $this->assertStringContainsString('settings.welcome_modal_updated', $this->asset('app/Controllers/Admin/Settings.php'));
        $this->assertStringContainsString('settings.welcome_modal_updated', $this->asset('app/Helpers/audit_display_helper.php'));
    }

    // -------------------------------------------------------- the tour itself

    /**
     * "The tour is so mini" was the complaint. Nine page keys left most of the
     * portal with no tour at all, so the floor is a page per major screen across
     * all four roles.
     */
    public function testTourCoversEveryMajorPage(): void
    {
        $tour = $this->tourJs();

        foreach ([
            'admin/dashboard', 'admin/students', 'admin/teachers', 'admin/sections',
            'admin/subjects', 'admin/grades', 'admin/analytics', 'admin/schedules',
            'admin/announcements', 'admin/materials', 'admin/audit-log',
            'admin/id-cards', 'admin/settings', 'admin/backups',
            // The pages that publish something, plus the admin screens that had
            // no tour at all. They used to fall through to the generic three-step
            // tour, which is the complaint this list exists to prevent.
            'admin/landing-page', 'admin/childpro-gad', 'admin/programs-projects',
            'admin/records', 'admin/password-resets', 'admin/student-nutrition',
            'admin/platform-ratings', 'admin/profile',
            'student/dashboard', 'student/grades', 'student/schedule', 'student/id-cards',
            'student/profile', 'student/notifications', 'student/report-card',
            'student/learner-development-report', 'student/platform-rating',
            'teacher/dashboard', 'teacher/attendance', 'teacher/grades', 'teacher/sections',
            'teacher/messages', 'parent/dashboard', 'parent/grades', 'parent/children',
        ] as $page) {
            $this->assertStringContainsString("'" . $page . "': [", $tour, $page . ' should have tour steps');
        }

        preg_match_all("/pose: '([a-z-]+)' \}/", $tour, $m);
        $this->assertGreaterThanOrEqual(
            40,
            count($m[1]),
            'the tour should be substantially bigger than the old 15 steps'
        );
    }

    /**
     * Every pose the tour asks for has to exist, or a step opens on a broken image.
     */
    public function testEveryTourPoseIsInstalled(): void
    {
        preg_match_all("/pose: '([a-z-]+)' \}/", $this->tourJs(), $m);

        $this->assertNotEmpty($m[1]);

        foreach (array_unique($m[1]) as $pose) {
            $this->assertTrue(mascot_exists($pose), 'the tour asks for ' . $pose . '.png, which is not installed');
        }
    }

    public function testTourCardIsBigEnoughToReadAndShowsProgress(): void
    {
        $css = $this->asset('public/css/mascot-tour.css');

        $this->assertStringContainsString('min(460px', $css);
        $this->assertStringContainsString('mascot-tour__dot', $css);
        $this->assertStringContainsString('--mascot-tour', $css);
        $this->assertStringContainsString('renderDots', $this->tourJs());
    }

    // ------------------------------------------------------------- assets

    /**
     * Render both partials for real.
     *
     * A syntax check cannot catch a missing helper or an undefined variable, and
     * these two partials run on every single page load, so a fatal error in either
     * one would take the whole site down rather than one screen.
     */
    public function testBothPartialsActuallyRender(): void
    {
        $dock = (string) view('partials/mascot_dock', ['dockContext' => 'dashboard']);

        $this->assertStringContainsString('mascot-dock', $dock);
        $this->assertStringContainsString('mascot-dock__trigger', $dock);
        $this->assertNotSame('', trim($dock));

        // The modal only renders when it is enabled and the artwork exists, both
        // of which are true here.
        $modal = (string) view('partials/welcome_modal', ['welcomeRole' => 'admin']);

        $this->assertStringContainsString('mascot-welcome', $modal);
        // "Start exploring" is set by the script on the last slide, so the markup
        // only carries "Next" - check for the copy the view actually renders.
        $this->assertStringContainsString('This is the Admin dashboard', $modal);
        $this->assertStringContainsString('data-welcome-next', $modal);
        $this->assertSame(4, substr_count($modal, 'data-welcome-slide'));
        $this->assertStringNotContainsString('<?php', $modal, 'the view must not leak raw PHP');
    }

    /**
     * The optimizer is what makes the larger placements affordable, so it has to
     * stay runnable and it has to be safe to run twice.
     */
    public function testTheArtworkOptimizerExistsAndIsIdempotent(): void
    {
        $tool = $this->asset('tools/mascot_optimize.php');

        $this->assertStringContainsString('ALPHA_CUTOFF', $tool);
        $this->assertStringContainsString('--dry-run', $tool);
        // Idempotency: a file within both the dimension and the weight target has
        // to be skipped. This used to be the dimension test alone, which is how
        // nine megabyte-scale stickers survived a run.
        $this->assertStringContainsString('! $tooBig && ! $tooHeavy', $tool);
        $this->assertStringContainsString('rename($tmp, $file)', $tool);

        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOTPATH . 'tools/mascot_optimize.php') . ' --dry-run 2>&1', $out, $code);

        $this->assertSame(0, $code, implode("\n", $out) . "\n" . 'the optimizer should run clean');
        $this->assertStringContainsString('already fine', implode("\n", $out), 'every pose should already be optimised');
    }

    // -------------------------------------------- where the artwork is loaded from

    /**
     * The scripts must not guess the artwork path from the URL.
     *
     * They used to: take the current URL and strip the last path segment. That is
     * only right for a page one level deep. On /admin/teachers it produced
     * /admin/assets/mascot/, so the tour, every alert and every dialog mascot 404ed
     * and the tour card showed a broken-image box. Both layouts now publish the
     * real path from asset_url(), which knows about the public/ subfolder and the
     * flat Hostinger layout.
     */
    public function testArtworkBaseIsPublishedByBothLayoutsAndReadByTheScripts(): void
    {
        foreach (['app/Views/layout.php', 'app/Views/dashboard_layout.php'] as $path) {
            $layout = (string) file_get_contents(ROOTPATH . $path);

            $this->assertStringContainsString('data-mascot-base=', $layout, $path . ' must publish the artwork base');
            $this->assertStringContainsString("asset_url('assets/mascot/')", $layout, $path . ' must build it with asset_url');
        }

        foreach (['public/js/mascot.js', 'public/js/mascot-tour.js'] as $path) {
            $js = (string) file_get_contents(ROOTPATH . $path);

            $this->assertStringContainsString(
                'data-mascot-base',
                $js,
                $path . ' must read the published base rather than deriving one from the URL'
            );
        }
    }

    /**
     * A pose that fails to load must leave an empty slot, not the browser's
     * broken-image glyph. mascot.js has always done this for alerts; the tour
     * builds its own markup and had to be taught the same trick.
     */
    public function testTourHidesAPoseThatFailsToLoad(): void
    {
        $js = $this->tourJs();

        $this->assertStringContainsString('hideIfBroken', $js);
        $this->assertStringContainsString("holder.innerHTML = ''", $js);
        $this->assertStringContainsString('naturalWidth === 0', $js);
    }
}
