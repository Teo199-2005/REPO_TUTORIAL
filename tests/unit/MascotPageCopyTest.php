<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tappy has to KNOW the pages, not just exist on them.
 *
 * Two reported problems live here.
 *
 *  1. Fifteen pages had no line at all. Anything missing from mascot_line_for()'s
 *     map falls through to a per-area line - and that fallback is `point-right`
 *     with the same sentence on every page in the area. Seven of the twenty-two
 *     admin sidebar pages, five student pages, one parent page, `about` and
 *     `projects` were all showing the identical pointing hand and the identical
 *     sentence. "He only knows 60% of the pages" is this test, failing.
 *
 *  2. The map was not spreading. Four poses (chart, reading, laptop, bust) covered
 *     57% of pages while hero, celebrate and worried were used on none of them, so
 *     the artwork looked decorative rather than chosen.
 *
 * The test derives its page list from the navigation definitions rather than a
 * hand-written list. A hand-written list is exactly how fifteen pages went missing
 * in the first place - it drifts from the sidebar the moment a page is added, and
 * MascotWelcomeTest's own hand-written list had already drifted.
 * admin_portal_nav_definition() is the single source of truth for admin pages and
 * portal_nav_*_sections() for the other three roles, so a new sidebar entry is
 * automatically covered by this test on the day it is added.
 *
 * @internal
 */
final class MascotPageCopyTest extends CIUnitTestCase
{
    /**
     * The sentences a page falls back to when nothing is mapped. A page showing
     * one of these is the bug this whole file exists for.
     *
     * @return list<string>
     */
    private function genericFallbacks(): array
    {
        return [
            'Everything for running the school lives in this menu.',
            'Everything you need for school is in this menu.',
            "Follow your child's progress from this menu.",
            'Ask me anything about this page.',
        ];
    }

    /**
     * The public pages, which are reachable by anyone and so have to be in here
     * too: there is no navigation definition for them to derive from.
     *
     * @return list<string>
     */
    private function publicPages(): array
    {
        return [
            'home', 'about', 'gad', 'childpro', 'programs', 'projects',
            'register', 'teacher/register', 'forgot-password', 'school-materials',
        ];
    }

    /**
     * Every page a person can actually reach, keyed the way mascot_segment_key()
     * keys it: the first two path segments.
     *
     * @return list<string>
     */
    private function navigablePages(): array
    {
        helper(['admin_access', 'portal_nav']);

        $key = static function (string $url): string {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            if ($path === '') {
                return '';
            }

            $segments = explode('/', $path);

            return count($segments) > 1 ? $segments[0] . '/' . $segments[1] : $segments[0];
        };

        $pages = [];

        foreach (admin_portal_nav_definition() as $definition) {
            $pages[] = $key($definition['href']);
        }

        foreach ([
            portal_nav_student_sections(),
            portal_nav_teacher_sections(null),
            portal_nav_parent_sections(null),
        ] as $sections) {
            foreach ($sections as $section) {
                foreach ($section['items'] as $item) {
                    $pages[] = $key($item['href']);
                }
            }
        }

        // The public pages, which are reachable by anyone and so have to be in
        // here too: there is no navigation definition for them to derive from.
        foreach ($this->publicPages() as $page) {
            $pages[] = $page;
        }

        return array_values(array_unique(array_filter($pages, static fn ($p) => $p !== '')));
    }

    /**
     * The regression itself. This is the assertion that would have caught the
     * fifteen missing pages instead of a person noticing them in a browser.
     */
    public function testEveryReachablePageHasItsOwnLine(): void
    {
        $fallbacks = $this->genericFallbacks();
        $offenders = [];

        foreach ($this->navigablePages() as $page) {
            $line = mascot_line_for($page);

            if (in_array($line['text'], $fallbacks, true)) {
                $offenders[] = $page;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These pages have no line of their own and fall through to the generic '
            . 'area sentence. Add each one to $lines in mascot_line_for().'
        );
    }

    /** A line that names the page is better than one that could sit anywhere. */
    public function testEveryReachablePageSaysSomethingSpecific(): void
    {
        foreach ($this->navigablePages() as $page) {
            $line = mascot_line_for($page);

            $this->assertNotSame('', $line['title'], $page . ' has no title');
            $this->assertNotSame('', $line['text'], $page . ' has nothing to say');
            $this->assertGreaterThanOrEqual(
                20,
                mb_strlen($line['text']),
                $page . ' has a line too short to tell the user anything'
            );
        }
    }

    /**
     * A pose that is not installed renders as an empty bubble, so the map and the
     * artwork folder have to agree.
     */
    public function testEveryPoseOnEveryReachablePageExistsAsArtwork(): void
    {
        foreach ($this->navigablePages() as $page) {
            $line = mascot_line_for($page);

            $this->assertTrue(
                mascot_exists($line['pose']),
                $page . ' refers to ' . $line['pose'] . '.png, which is not installed'
            );
        }
    }

    /**
     * The spreading test. A generous ceiling, because some poses genuinely suit
     * several pages - but a pose used on most of the app is the "same shrug on
     * every screen" problem, and 6 catches a pile-up without dictating the map.
     */
    public function testNoOnePoseTakesOverTheSite(): void
    {
        $counts = [];

        foreach ($this->navigablePages() as $page) {
            $pose          = mascot_line_for($page)['pose'];
            $counts[$pose] = ($counts[$pose] ?? 0) + 1;
        }

        foreach ($counts as $pose => $count) {
            $this->assertLessThanOrEqual(
                6,
                $count,
                $pose . ' is used on ' . $count . ' pages, which is the repetition this map exists to avoid'
            );
        }

        // And the other direction: plenty of distinct characters, so the artwork
        // reads as chosen rather than random.
        $this->assertGreaterThanOrEqual(
            10,
            count($counts),
            'the dock should use a wide spread of poses, not a handful'
        );
    }

    /**
     * point-right means "here is the menu". It was also the catch-all fallback, so
     * it ended up on every unmapped page at once - which is precisely what made it
     * the most-seen pose in the app.
     */
    public function testPointRightIsNotUsedAsAContentPose(): void
    {
        foreach ($this->navigablePages() as $page) {
            $line = mascot_line_for($page);

            if ($line['text'] === 'Everything for running the school lives in this menu.') {
                // The area fallback is allowed to point at the menu; that is what
                // it is for.
                continue;
            }

            $this->assertNotSame(
                'point-right',
                $line['pose'],
                $page . ' uses point-right, which is reserved for pointing at the menu'
            );
        }
    }

    /**
     * The three pages that publish to the public site. A line that does not say
     * WHERE the content goes is the original complaint: an administrator editing
     * a page they are not standing on needs to be told who will see it.
     */
    public function testThePublishingPagesExplainWhereTheContentGoes(): void
    {
        foreach ([
            'admin/childpro-gad'      => ['gad', 'childpro'],
            'admin/programs-projects' => ['programs', 'projects'],
        ] as $page => $publicPages) {
            $line = mascot_line_for($page);

            $this->assertMatchesRegularExpression(
                '/header|section/i',
                $line['text'],
                $page . ' should say what can be uploaded there'
            );
            $this->assertMatchesRegularExpression(
                '/public page|appears/i',
                $line['text'],
                $page . ' should say that this content is published, and where'
            );

            foreach ($publicPages as $public) {
                // Case-insensitive: the copy writes the proper nouns CHILDPRO and
                // GAD in caps, the path segments are lowercase.
                $this->assertStringContainsStringIgnoringCase(
                    $public,
                    $line['text'],
                    $page . ' should name the public page it feeds (' . $public . ')'
                );
            }
        }

        $landing = mascot_line_for('admin/landing-page');
        $this->assertMatchesRegularExpression(
            '/hero slide|front page|home page|first thing/i',
            $landing['text'],
            'the landing page line should say that this is what visitors see first'
        );
    }

    /**
     * Each public tab needs its OWN key. One shared "Programs and Projects" entry
     * would be wrong on a page called "Projects", and would also make the per-tab
     * override editor write to the wrong page.
     */
    public function testEachPublishableTabHasItsOwnPublicKey(): void
    {
        $titles = [];

        foreach (['childpro', 'gad', 'programs', 'projects'] as $tab) {
            $line = mascot_line_for($tab);

            $this->assertNotSame(
                'Ask me anything about this page.',
                $line['text'],
                '/' . $tab . ' has no line of its own'
            );
            $this->assertTrue(
                mascot_exists($line['pose']),
                '/' . $tab . ' refers to a missing pose'
            );

            $titles[$tab] = $line['title'];
        }

        $this->assertSame(
            count($titles),
            count(array_unique($titles)),
            'each publishable tab should have its own heading: ' . json_encode($titles)
        );
    }

    /**
     * The tour keys a person can actually arrive at. The lookup used to match the
     * FULL pathname, so `admin/schedules` missed the declared `admin/schedule` and
     * every sub-page missed everything - all of which fell through to GENERIC.
     */
    public function testTheTourResolvesStepsBySegmentNotFullPath(): void
    {
        $tour = (string) file_get_contents(ROOTPATH . 'public/js/mascot-tour.js');

        preg_match_all("/^\s*'([a-z0-9\/_-]+)':\s*\[/m", $tour, $matches);

        $this->assertNotEmpty($matches[1], 'the tour should still declare steps');

        foreach ($matches[1] as $key) {
            $this->assertLessThanOrEqual(
                2,
                count(explode('/', $key)),
                $key . ' is a tour key with more than two segments, but the lookup only ever uses two'
            );
        }

        $this->assertStringContainsString(
            'routeKey()',
            $tour,
            'the tour should resolve its steps through routeKey() so sub-pages match'
        );
        $this->assertStringNotContainsString(
            'steps()[path()]',
            $tour,
            'the tour must not match on the full pathname again: that is what left sub-pages with no tour'
        );
    }

    /**
     * Every page the navigation offers also needs a tour key, or opening it shows
     * the generic three-step sidebar tour instead of anything about that page.
     */
    public function testEveryReachablePortalPageHasATour(): void
    {
        $tour = (string) file_get_contents(ROOTPATH . 'public/js/mascot-tour.js');

        $missing = [];

        foreach ($this->navigablePages() as $page) {
            // The tour is for the signed-in portal. Public pages are not in it,
            // including the two-segment 'teacher/register' sign-up form, which is
            // a public page and not a teacher route.
            if (! str_contains($page, '/') || in_array($page, $this->publicPages(), true)) {
                continue;
            }

            if (strpos($tour, "'" . $page . "': [") === false) {
                $missing[] = $page;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These pages have no tour steps and would show the generic tour instead.'
        );
    }

    /**
     * A subdirectory install would break a raw pathname comparison outright, so
     * both layouts have to publish the base path for the tour to strip.
     */
    public function testBothLayoutsPublishTheBasePathForTheTour(): void
    {
        foreach (['layout.php', 'dashboard_layout.php'] as $layout) {
            $markup = (string) file_get_contents(APPPATH . 'Views/' . $layout);

            $this->assertStringContainsString(
                'data-mascot-basepath',
                $markup,
                $layout . ' must publish data-mascot-basepath or the tour matches nothing in a subdirectory install'
            );
        }

        $this->assertTrue(
            function_exists('mascot_base_path'),
            'mascot_base_path() is missing'
        );
    }

    /**
     * The admin pages that publish something need somewhere to write the public
     * page's own greeting. CHILDPRO/GAD had it; Programs & Projects and the
     * Landing Page did not, so those public pages could only ever show built-in
     * copy.
     */
    public function testEveryPublishingAdminPageOffersTheMessageEditor(): void
    {
        $programs = (string) file_get_contents(APPPATH . 'Controllers/Admin/ProgramsProjects.php');

        $this->assertStringContainsString(
            'mascot_line_override_save',
            $programs,
            'Programs & Projects cannot write Tappy\'s message for /programs or /projects'
        );
        $this->assertStringContainsString(
            'mascot_pose_choices',
            $programs,
            'Programs & Projects does not offer the pose picker'
        );

        $landing = (string) file_get_contents(APPPATH . 'Controllers/Admin/LandingPage.php');

        $this->assertStringContainsString(
            "mascot_line_override_save('home'",
            $landing,
            'the Landing Page cannot write Tappy\'s message for the front page'
        );

        foreach (['admin/programs_projects.php', 'admin/landing_page.php'] as $view) {
            $markup = (string) file_get_contents(APPPATH . 'Views/' . $view);

            $this->assertStringContainsString(
                'name="mascot_text"',
                $markup,
                $view . ' has no message box'
            );
            $this->assertStringContainsString(
                'name="mascot_pose"',
                $markup,
                $view . ' has no pose picker'
            );
        }
    }

    /**
     * Programs & Projects renders two tabs, and the view used to hard-code the
     * Programs one - so /projects was a live public page with no editor at all.
     */
    public function testTheProjectsTabIsActuallyEditable(): void
    {
        $markup = (string) file_get_contents(APPPATH . 'Views/admin/programs_projects.php');

        $this->assertStringNotContainsString(
            "\$tab = 'programs';",
            $markup,
            'the view must not pin a single tab, or /projects can never be edited'
        );
        $this->assertStringContainsString(
            'foreach ($ppTabNames as',
            $markup,
            'the view should render one pane per tab'
        );
        $this->assertStringContainsString(
            'ppAddSection(',
            $markup,
            'the add-section control should be scoped to a tab container'
        );
    }
}