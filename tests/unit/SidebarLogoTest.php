<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The school logo in the portal sidebar header.
 *
 * The medallion markup must stay wrapped around the canonical logo returned by
 * school_logo_url() — the same accessor the ID cards and student profiles use —
 * so the branding cannot drift onto a different image on one screen only.
 *
 * @internal
 */
final class SidebarLogoTest extends CIUnitTestCase
{
    private function layout(): string
    {
        return (string) file_get_contents(APPPATH . 'Views/dashboard_layout.php');
    }

    private function css(): string
    {
        return (string) file_get_contents(ROOTPATH . 'public/css/dashboard.css');
    }

    /**
     * The medallion size declared in the stylesheet, in pixels.
     *
     * Read rather than hard-coded so that changing the logo size is a one-line
     * CSS edit plus the matching width/height attributes, not a hunt through the
     * tests for every literal that used to mention the old number.
     */
    private function logoSize(): int
    {
        $matched = preg_match('/\.app-brand-logo\s*\{[^}]*?width:\s*(\d+)px/s', $this->css(), $m);

        $this->assertSame(1, $matched, 'the medallion needs an explicit pixel width in dashboard.css');

        return (int) $m[1];
    }

    /** The same size, read from the width/height attributes in the markup. */
    private function markupLogoSize(): int
    {
        // Note the [\s\S] window rather than a "no angle bracket" character class:
        // the attributes are interleaved with PHP short-echo tags, and that
        // construct ends in an angle bracket, so the class stops before width.
        $matched = preg_match(
            '/class="app-brand-logo">[\s\S]{0,600}?width="(\d+)"/',
            $this->layout(),
            $m
        );

        $this->assertSame(1, $matched, 'the medallion image needs a width attribute');

        return (int) $m[1];
    }

    public function testSidebarHeaderRendersTheSchoolLogo(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('app-brand-logo', $layout, 'the sidebar must render the logo medallion');
        $this->assertStringContainsString('school_logo_url()', $layout, 'the logo must come from school_logo_url()');
    }

    /**
     * The medallion has to sit on the LEFT, inside the brand link, before the
     * title text.
     */
    public function testLogoIsOnTheLeftInsideTheBrandLink(): void
    {
        $layout = $this->layout();

        $linkOpen = strpos($layout, 'app-brand-link');
        $logo     = strpos($layout, '<span class="app-brand-logo">');
        $linkEnd  = strpos($layout, '</a>', (int) $linkOpen);
        $text     = strpos($layout, 'app-brand-text');

        $this->assertIsInt($linkOpen, 'the brand link must exist');
        $this->assertIsInt($logo, 'the medallion must exist');
        $this->assertIsInt($linkEnd);
        $this->assertIsInt($text);

        $this->assertGreaterThan($linkOpen, $logo, 'the medallion must be inside the brand link');
        $this->assertLessThan($linkEnd, $logo, 'the medallion must be inside the brand link');
        $this->assertLessThan($text, $logo, 'the logo must come before the brand text (i.e. on the left)');
    }

    public function testLogoImageIsAccessibleAndDoesNotShiftLayout(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('alt="', $layout, 'the logo needs alt text');
        // Intrinsic width/height prevent the header jumping while the image loads.
        // They must agree with the medallion size in the stylesheet: hard-coding a
        // number in two places is how the two drift apart and the header starts
        // shifting again, so the value is read from the CSS and compared.
        $this->assertMatchesRegularExpression(
            '/width="(\d+)" height="\1"/',
            $layout,
            'the logo needs matching intrinsic width/height attributes'
        );

        $this->assertSame(
            $this->logoSize(),
            $this->markupLogoSize(),
            'the intrinsic size must match .app-brand-logo so the header does not shift on load'
        );
        $this->assertStringContainsString('decoding="async"', $layout);
    }

    /**
     * A missing logo file must not leave a broken-image icon in the header.
     */
    public function testLogoFallsBackToTheDepEdSealThenDisappears(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('onerror=', $layout, 'the logo needs a fallback');
        $this->assertStringContainsString('DepEd_Official_Seal.png', $layout, 'the fallback should be the DepEd seal');
        // Clearing onerror first is what stops an infinite fallback loop.
        $this->assertStringContainsString('this.onerror=null', $layout);
    }

    public function testMedallionStylesExist(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('.app-brand-logo {', $css);
        $this->assertStringContainsString('.app-brand-logo img {', $css);
        $this->assertStringContainsString('.app-brand-link {', $css);

        // The medallion is a fixed square, not a percentage: a percentage would
        // let it collapse inside the narrow collapsed rail.
        $this->assertMatchesRegularExpression(
            '/\.app-brand-logo\s*\{[^}]*width:\s*\d+px[^}]*height:\s*\d+px/s',
            $css,
            'the medallion needs a fixed square size'
        );
    }

    /**
     * The reported bug: "CSCS Tap n Track" sat flush against the school seal.
     *
     * The seal and the wordmark are siblings inside a Bootstrap .d-flex, and
     * .d-flex sets no gap of its own. .app-sidebar-header has a gap, but that
     * only separates the header's own children - it does nothing for the two
     * elements nested inside the brand link, so they ended up touching.
     */
    public function testTheSealIsSpacedAwayFromTheBrandText(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.app-brand-link\s*\{[^}]*gap:\s*\d+px/s',
            $css,
            '.app-brand-link needs a gap, or the medallion touches the wordmark'
        );
    }

    /**
     * A duplicate .app-brand-text rule was the cause of a real layout bug (the
     * collapsed sidebar pushed the medallion out of view), so guard the count.
     */
    public function testBrandTextIsDeclaredOnlyOnce(): void
    {
        $this->assertSame(
            1,
            substr_count($this->css(), '.app-brand-text {'),
            '.app-brand-text must be declared exactly once so min-width:0 is not lost'
        );
    }

    /**
     * Collapsing the sidebar hides the text but must keep the logo visible, and
     * centred — that is the only branding left in a collapsed rail.
     */
    public function testCollapsedSidebarKeepsTheLogoAndHidesTheText(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('.app-sidebar-wrapper.collapsed .app-brand-text', $css);
        $this->assertStringContainsString('.app-sidebar-wrapper.collapsed .app-sidebar-header', $css);
        // The medallion must not be in the list of things the collapsed rail hides.
        $hiddenBlock = '';
        if (preg_match('/collapsed \.app-brand-text,[^}]*\}/s', $css, $m) === 1) {
            $hiddenBlock = $m[0];
        }
        $this->assertStringNotContainsString('app-brand-logo', $hiddenBlock, 'the logo must stay visible when collapsed');
    }

    public function testTheLogoFileActuallyShips(): void
    {
        $this->assertFileExists(ROOTPATH . 'public/LPHS2.png', 'the school logo must ship with the app');
    }
}