<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The shared admin UI standard.
 *
 * These tests are the contract for public/css/admin-ui.css and the
 * views/admin/partials/* components. They fail loudly if a page drifts away
 * from the standard (an Apply button reappears, a page hand-rolls its own
 * header, a table loses .admin-table, the JavaScript pager stops being
 * opt-in), because that drift is exactly what made the admin area hard to
 * learn for non-technical staff.
 *
 * @internal
 */
final class AdminUiStandardTest extends CIUnitTestCase
{
    /** Pages migrated onto the shared header + filter bar. */
    private const MIGRATED = [
        'sections', 'students', 'teachers', 'audit_log',
        'password_resets', 'student_nutrition', 'analytics', 'platform_ratings',
    ];

    /** Subset of the above that has a filter bar. */
    private const FILTER_PAGES = [
        'sections', 'students', 'teachers', 'audit_log', 'password_resets', 'student_nutrition',
    ];

    private function view(string $name): string
    {
        $path = APPPATH . 'Views/admin/' . $name . '.php';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * @dataProvider provideMigratedPages
     */
    public function testMigratedPagesUseTheSharedPageHeader(string $page): void
    {
        $this->assertStringContainsString(
            'admin/partials/page_header',
            $this->view($page),
            $page . ' must render the shared page header'
        );
    }

    /**
     * A hand-rolled "flex row with an h1 next to the buttons" header is the
     * pattern the standard replaces, so none may remain.
     *
     * @dataProvider provideMigratedPages
     */
    public function testMigratedPagesHaveNoAdHocHeader(string $page): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="d-flex justify-content-between[^"]*"\s*>\s*<h1/',
            $this->view($page),
            $page . ' still has an ad-hoc page header'
        );
    }

    /**
     * @dataProvider provideFilterPages
     */
    public function testFilterPagesUseTheSharedFilterBar(string $page): void
    {
        $html = $this->view($page);

        $this->assertStringContainsString('admin/partials/filter_bar', $html, $page . ' must use the shared filter bar');
        $this->assertStringNotContainsString('<form class="row g-2 mb-3"', $html, $page . ' still has hand-rolled filter markup');
    }

    /**
     * The standard is auto-submit, so there is no Apply / Filter button.
     *
     * @dataProvider provideFilterPages
     */
    public function testFilterPagesHaveNoApplyButton(string $page): void
    {
        $html = $this->view($page);

        $this->assertStringNotContainsString('>Apply<', $html, $page . ' still has an Apply button');
        $this->assertStringNotContainsString('>Apply filters<', $html, $page . ' still has an Apply button');
        $this->assertStringNotContainsString('>Filter<', $html, $page . ' still has a Filter button');
    }

    /**
     * Auto-submit is centralised in admin-filter-bar.js, so no page should
     * still be wiring `onchange="this.form.submit()"` by hand.
     *
     * @dataProvider provideMigratedPages
     */
    public function testNoInlineOnChangeSubmitRemains(string $page): void
    {
        $this->assertStringNotContainsString(
            'onchange="this.form.submit()"',
            $this->view($page),
            $page . ' still submits inline instead of using admin-filter-bar.js'
        );
    }

    /**
     * The first <table> on a list page is its data grid. Later tables are
     * modal sub-grids or detail panels, which are legitimately different.
     *
     * @dataProvider provideMigratedPages
     */
    public function testMainListTableUsesTheStandardClass(string $page): void
    {
        $html = $this->view($page);

        if (preg_match('/<table[^>]*class="([^"]*)"/', $html, $m) !== 1) {
            $this->markTestSkipped($page . ' has no table on this page');
        }

        $this->assertStringContainsString('admin-table', $m[1], $page . ' main list table is not .admin-table');
    }

    /**
     * @dataProvider provideMigratedPages
     */
    public function testNoStripedTablesRemain(string $page): void
    {
        $this->assertStringNotContainsString(
            'table-striped',
            $this->view($page),
            $page . ' still uses .table-striped; the standard is .admin-table'
        );
    }

    /**
     * The empty state must be the shared component so the wording and the
     * call-to-action are consistent.
     *
     * @dataProvider providePagesWithLists
     */
    public function testEmptyStateUsesSharedComponent(string $page): void
    {
        $this->assertStringContainsString('admin/partials/empty_state', $this->view($page));
    }

    public function testJavaScriptPagerIsOptInOnly(): void
    {
        $js = (string) file_get_contents(ROOTPATH . 'public/js/admin-table-enhancements.js');

        $this->assertStringContainsString("dataset.jsPaged === '1'", $js, 'the JS pager must be opt-in');
        $this->assertStringContainsString('if (!wantsJsPager) return;', $js, 'the JS pager must not be injected by default');
    }

    /**
     * A page that renders its own server-side pager must never opt in, or the
     * user ends up with two competing pagers.
     *
     * @dataProvider provideServerPagedPages
     */
    public function testServerPagedPagesDoNotOptInToJsPager(string $page): void
    {
        $this->assertStringNotContainsString('data-js-paged="1"', $this->view($page));
    }

    /**
     * A list page with no server-side pager still needs one, so it opts in.
     *
     * @dataProvider provideJsPagedPages
     */
    public function testUnservedPagesOptInToJsPager(string $page): void
    {
        $this->assertStringContainsString('data-js-paged="1"', $this->view($page));
    }

    public function testTheStandardStylesheetAndScriptAreWiredIn(): void
    {
        $layout = (string) file_get_contents(APPPATH . 'Views/dashboard_layout.php');

        $this->assertStringContainsString('css/admin-ui.css', $layout, 'admin-ui.css must be linked in the layout');
        $this->assertStringContainsString('js/admin-filter-bar.js', $layout, 'admin-filter-bar.js must be loaded');

        $this->assertFileExists(ROOTPATH . 'public/css/admin-ui.css');
        $this->assertFileExists(ROOTPATH . 'public/js/admin-filter-bar.js');
    }

    public static function provideMigratedPages(): array
    {
        return array_map(static fn (string $p): array => [$p], self::MIGRATED);
    }

    public static function provideFilterPages(): array
    {
        return array_map(static fn (string $p): array => [$p], self::FILTER_PAGES);
    }

    public static function providePagesWithLists(): array
    {
        return [['students'], ['teachers']];
    }

    public static function provideServerPagedPages(): array
    {
        return [['students'], ['audit_log'], ['sections'], ['password_resets']];
    }

    public static function provideJsPagedPages(): array
    {
        return [['teachers'], ['materials'], ['students_archived'], ['student_nutrition'], ['platform_ratings'], ['teachers_pending']];
    }
}
