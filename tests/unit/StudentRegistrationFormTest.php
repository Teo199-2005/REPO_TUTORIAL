<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The public student registration form (auth/register.php).
 *
 * Two reported problems live here:
 *
 *  1. Choosing "Transferee" revealed a tall, narrow stack nested inside the
 *     Student Type column, so that one third of the row grew to several times
 *     the height of its neighbours and the step visibly jumped. Religion's
 *     "Other" box did the same thing, and worse: a stylesheet rule forced its
 *     column to 100% width, reflowing the whole row.
 *  2. Nationality offered an "Other" entry that did nothing at all - no box
 *     appeared and the literal word "Other" was all that could ever be stored.
 *
 * The fix is a single full-width .form-extras band below the row that holds every
 * revealed field, so a column's height can never depend on what was selected.
 *
 * @internal
 */
final class StudentRegistrationFormTest extends CIUnitTestCase
{
    private function view(): string
    {
        return (string) file_get_contents(APPPATH . 'Views/auth/register.php');
    }

    /**
     * Nationality needs the same "Other" escape hatch religion already had.
     */
    public function testNationalityHasAnOtherCompanionLikeReligion(): void
    {
        $this->assertSame('Other', nationality_other_option());
        $this->assertTrue(nationality_is_other('Other'));
        $this->assertTrue(nationality_is_other('other'));
        $this->assertFalse(nationality_is_other('Filipino'));
        $this->assertFalse(nationality_is_other(null));

        // The list has to actually offer the choice, or the companion box is
        // unreachable and the whole feature is dead code.
        $this->assertContains('Other', nationality_options());
    }

    public function testOtherChoiceResolvesToTheTypedText(): void
    {
        $resolved = other_choice_normalize_fields(
            ['nationality' => 'Other', 'nationality_other' => '  Bolivian  '],
            'nationality',
            'nationality_other'
        );

        // Trimmed, and the literal "Other" never survives into the database.
        $this->assertSame('Bolivian', $resolved['nationality']);

        // A listed value is left exactly as posted.
        $untouched = other_choice_normalize_fields(
            ['nationality' => 'Filipino', 'nationality_other' => 'ignored'],
            'nationality',
            'nationality_other'
        );
        $this->assertSame('Filipino', $untouched['nationality']);

        // "Other" with an empty box becomes empty, so `required` rejects it with a
        // message pointing at the box rather than silently storing "Other".
        $blank = other_choice_normalize_fields(
            ['nationality' => 'Other', 'nationality_other' => '   '],
            'nationality',
            'nationality_other'
        );
        $this->assertSame('', $blank['nationality']);

        // A non-string payload must not blow up or pass an array through to SQL.
        $array = other_choice_normalize_fields(
            ['nationality' => 'Other', 'nationality_other' => ['x']],
            'nationality',
            'nationality_other'
        );
        $this->assertSame('', $array['nationality']);
    }

    /** Religion keeps behaving exactly as it did before the refactor. */
    public function testReligionStillResolvesThroughTheSharedHelper(): void
    {
        $resolved = religion_normalize_fields(
            ['religion' => 'Other', 'religion_other' => 'Aglipayan Church']
        );

        $this->assertSame('Aglipayan Church', $resolved['religion']);
        $this->assertSame('Islam', religion_normalize_fields(['religion' => 'Islam'])['religion']);
    }

    /**
     * The reported layout bug: no revealed field may sit inside the column that
     * triggers it, because that is what made one column taller than its
     * neighbours and made the step jump.
     */
    public function testRevealedFieldsLiveOutsideTheirTriggeringColumn(): void
    {
        $view = $this->view();

        $columnStart = strpos($view, 'id="studentTypeField"');
        $this->assertIsInt($columnStart, 'the student type column must exist');

        $nextColumn = strpos($view, 'id="nationalityField"', $columnStart);
        $this->assertIsInt($nextColumn);

        $studentTypeColumn = substr($view, $columnStart, $nextColumn - $columnStart);

        $this->assertStringNotContainsString(
            'id="previous_school"',
            $studentTypeColumn,
            'the previous-school fields must not be nested in the student type column'
        );
        $this->assertStringNotContainsString(
            'id="transfereeFields"',
            $studentTypeColumn,
            'the transferee group must not be nested in the student type column'
        );

        $religionStart = strpos($view, 'id="religionField"');
        $this->assertIsInt($religionStart);
        // The extras band is the boundary: everything up to it is still the
        // three-column row, and only after it do the revealed fields begin.
        $extrasStart = strpos($view, 'class="form-extras"', $religionStart);
        $this->assertIsInt($extrasStart, 'the shared extras band must exist');

        $religionColumn = substr($view, $religionStart, $extrasStart - $religionStart);
        $this->assertStringNotContainsString(
            'id="religion_other"',
            $religionColumn,
            'the religion "Other" box must not be nested in the religion column'
        );

        // The band must come after the whole row, not inside it.
        $this->assertGreaterThan(
            strpos($view, 'id="religionField"'),
            $extrasStart,
            'the revealed fields belong below the row'
        );

        // The stylesheet must not force the religion column to full width, which
        // is what reflowed the whole row and pushed Nationality onto its own line.
        $this->assertStringNotContainsString(
            'religion-field.is-other',
            $view,
            'the 100%-width religion column hack must stay removed'
        );

        foreach (['transfereeFields', 'nationalityOtherWrap', 'religionOtherWrap'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $view);
        }
    }

    /**
     * "Other" must actually reveal a textbox, and while hidden it must be disabled
     * so a stale value is never submitted against a choice that was deselected.
     */
    public function testEachOtherChoiceRevealsADisabledUntilUsedTextbox(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('name="nationality_other"', $view);
        $this->assertStringContainsString('onchange="toggleNationalityOther()"', $view);
        $this->assertStringContainsString('function toggleNationalityOther()', $view);
        $this->assertStringContainsString('function toggleOtherChoice(', $view);

        // The two toggles must not each grow their own copy of the logic.
        $this->assertSame(
            1,
            substr_count($view, "wrapper.classList.toggle('is-visible', isOther)"),
            'the reveal logic should exist once, in the shared helper'
        );

        $this->assertMatchesRegularExpression(
            '/input\.required\s*=\s*isOther/',
            $view,
            'the companion box must gain `required` while visible'
        );
        $this->assertMatchesRegularExpression(
            '/input\.disabled\s*=\s*!isOther/',
            $view,
            'the companion box must be disabled while hidden'
        );
    }
}