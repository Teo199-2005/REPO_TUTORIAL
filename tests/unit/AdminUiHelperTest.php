<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The helper behind views/admin/partials/filter_bar.php.
 *
 * The important behaviour is that a filter which is "not set" is recognised as
 * inactive — that is what keeps the Reset button and the "More filters (n)"
 * badge honest.
 *
 * @internal
 */
final class AdminUiHelperTest extends CIUnitTestCase
{
    public function testOptionShape(): void
    {
        $this->assertSame(['value' => '7', 'label' => 'Grade 7'], admin_filter_option('7', 'Grade 7'));
    }

    public function testEmptyAndSentinelValuesAreNotActive(): void
    {
        $values = ['q' => 'ana', 'grade' => '7', 'status' => 'all', 'section' => '', 'gender' => '   '];

        $result = admin_filter_count_active($values, ['q', 'grade', 'status', 'section', 'gender']);

        $this->assertSame(2, $result['total']);
        $this->assertSame(['q', 'grade'], $result['names']);
    }

    public function testCustomSentinelsAreHonoured(): void
    {
        // 'name' and 'asc' are the teachers page's defaults, not filters.
        $values = ['sort_by' => 'name', 'sort_order' => 'asc', 'status' => 'active'];
        $result = admin_filter_count_active($values, ['sort_by', 'sort_order', 'status'], ['', 'all', 'name', 'asc']);

        $this->assertSame(1, $result['total']);
        $this->assertSame(['status'], $result['names']);
    }

    public function testArrayValuesAreIgnoredRatherThanThrowing(): void
    {
        $result = admin_filter_count_active(['q' => ['a', 'b'], 'grade' => '7'], ['q', 'grade']);

        $this->assertSame(['grade'], $result['names']);
    }

    public function testQueryDropsEmptyAndSentinelValues(): void
    {
        $values = ['q' => 'ana', 'grade' => '7', 'status' => 'all', 'section' => ''];

        $this->assertSame(
            ['q' => 'ana', 'grade' => '7'],
            admin_filter_query($values, ['q', 'grade', 'status', 'section'])
        );
    }

    public function testQueryTrimsWhitespace(): void
    {
        $this->assertSame(['q' => 'ana'], admin_filter_query(['q' => '  ana  '], ['q']));
    }

    public function testQueryKeepsZeroBecauseItIsARealFilterValue(): void
    {
        $this->assertSame(['page' => '0'], admin_filter_query(['page' => '0'], ['page']));
    }

    public function testValueIsAlwaysAString(): void
    {
        $this->assertSame('5', admin_filter_value(['p' => 5], 'p'));
        $this->assertSame('', admin_filter_value([], 'missing'));
        $this->assertSame('fallback', admin_filter_value([], 'missing', 'fallback'));
        $this->assertSame('fallback', admin_filter_value(['x' => ['a']], 'x', 'fallback'));
    }

    public function testIconClassHelper(): void
    {
        $this->assertSame('bi-people-fill', admin_page_icon_class('students'));
        // An unknown key still yields a usable class rather than an empty one.
        $this->assertNotSame('', admin_page_icon_class('not_a_real_page'));
    }

    public function testRowActionIsIconOnlyAndAccessible(): void
    {
        $html = admin_row_action([
            'url'   => 'admin/x/1',
            'icon'  => 'bi-eye',
            'label' => 'View details',
        ]);

        $this->assertStringContainsString('title="View details"', $html);
        $this->assertStringContainsString('aria-label="View details"', $html);
        $this->assertStringContainsString('visually-hidden', $html, 'the icon button needs a text label for screen readers');
        $this->assertStringContainsString('bi-eye', $html);
    }

    public function testRowActionEscapesUrlAndLabel(): void
    {
        $html = admin_row_action([
            'url'   => 'admin/x?a=1&b=2',
            'icon'  => 'bi-eye',
            'label' => '<script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('a=1&amp;b=2', $html);
    }
}