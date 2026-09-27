<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The landing page announcement strip.
 *
 * The strip is a marquee driven by a single admin-typed setting, so two things
 * have to hold at once:
 *
 *  1. A URL in that setting is a real link that opens in a new tab. The homepage
 *     used to escape the text and print it, which left the URL as inert text --
 *     the helper that linkifies existed but was never wired into landing_hero.php.
 *  2. The setting is untrusted input from the database's point of view, because
 *     an admin types it. The linkify helper has to escape the text itself, and
 *     that ordering is the only thing standing between a stored <script> and every
 *     visitor. childpro_gad.php and programs_projects.php already echoed this
 *     helper's output unescaped, so the hole was live on two pages before this fix.
 *
 * @internal
 */
final class LandingAnnouncementStripTest extends CIUnitTestCase
{
    private function viewSource(string $relative): string
    {
        return (string) file_get_contents(ROOTPATH . $relative);
    }

    /** The URLs in the live setting are what staff actually paste. */
    public function testUrlBecomesClickableLinkOpeningNewTab(): void
    {
        $html = landing_announcement_strip_linkify(
            'HELP US TO SERVE YOU BETTER! CLICK the LINK: https://forms.office.com/r/aUuWAbgwrx?origin=lprLink'
        );

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href="https://forms.office.com/r/aUuWAbgwrx?origin=lprLink"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('HELP US TO SERVE YOU BETTER!', $html);
    }

    public function testBareUrlWithNoSurroundingTextStillLinks(): void
    {
        $html = landing_announcement_strip_linkify('https://gemini.google.com/app');

        $this->assertStringContainsString('href="https://gemini.google.com/app"', $html);
    }

    public function testEveryUrlInAMultiUrlSettingIsLinked(): void
    {
        $html = landing_announcement_strip_linkify(
            'https://a.example.com/one https://b.example.com/two'
        );

        $this->assertSame(2, substr_count($html, '<a '));
        $this->assertStringContainsString('href="https://a.example.com/one"', $html);
        $this->assertStringContainsString('href="https://b.example.com/two"', $html);
    }

    /**
     * The regression that matters most: the helper's output is echoed without
     * esc(), so escaping has to happen inside it.
     */
    public function testMarkupInTheSettingIsEscapedNotExecuted(): void
    {
        $html = landing_announcement_strip_linkify('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testImageOnErrorPayloadCannotEscapeTheAnchor(): void
    {
        $html = landing_announcement_strip_linkify(
            '<img src=x onerror=alert(1)> https://ok.example.com/'
        );

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror=alert(1)>', $html);
        // The legitimate URL still survives next to the hostile text.
        $this->assertStringContainsString('href="https://ok.example.com/"', $html);
    }

    /** A quote must not be able to terminate the href and add its own attribute. */
    public function testQuoteInTheSettingCannotBreakOutOfTheHref(): void
    {
        $html = landing_announcement_strip_linkify('https://ok.example.com/" onmouseover="alert(1)');

        $this->assertStringNotContainsString('onmouseover="alert(1)"', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    /** The homepage is the page that was broken, so pin its wiring specifically. */
    public function testHomepageRendersTheLinkifiedStripRatherThanEscapedText(): void
    {
        $hero = $this->viewSource('app/Views/partials/landing_hero.php');

        $this->assertStringContainsString('landing_announcement_strip_linkify(', $hero);
        $this->assertStringNotContainsString(
            'landing-announcement-strip__text"><?= esc($stripText)',
            $hero,
            'Homepage strip reverted to printing the URL as inert escaped text.'
        );
    }

    /**
     * A moving target is not really clickable, so the marquee has to hold still
     * once the pointer is over the strip.
     */
    public function testMarqueePausesOnHoverAndFocusSoTheLinkCanBeClicked(): void
    {
        $landing = $this->viewSource('app/Views/landing.php');

        $this->assertStringContainsString(
            '.landing-announcement-strip:hover .landing-announcement-strip__track',
            $landing
        );
        $this->assertStringContainsString(
            '.landing-announcement-strip:focus-within .landing-announcement-strip__track',
            $landing
        );
        $this->assertStringContainsString('animation-play-state: paused', $landing);
    }

    /** Only real web schemes become links; javascript: stays inert text. */
    public function testDangerousSchemesAreNotTurnedIntoLinks(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>'] as $payload) {
            $html = landing_announcement_strip_linkify($payload);

            $this->assertStringNotContainsString('<a ', $html, "Linkified a non-web scheme: {$payload}");
        }
    }

    /**
     * Escaping happens before linkifying, so the URL is already escaped when the
     * anchor is built. Escaping it a second time would corrupt any URL that
     * legitimately carries an ampersand in its query string.
     */
    public function testAmpersandInQueryStringIsNotDoubleEscaped(): void
    {
        $html = landing_announcement_strip_linkify('https://forms.example.com/r/abc?origin=lpr&id=7');

        $this->assertStringContainsString('origin=lpr&amp;id=7', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testEmptyAndWhitespaceOnlyTextProduceNothing(): void
    {
        $this->assertSame('', landing_announcement_strip_linkify(''));
        $this->assertSame('', landing_announcement_strip_linkify("   \n\t  "));
    }
}
