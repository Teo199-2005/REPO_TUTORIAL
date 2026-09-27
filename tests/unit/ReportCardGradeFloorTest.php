<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The 60 report card floor for teacher-entered grades.
 *
 * Grades are typed by hand on the Enter Grades page, and teachers do type 44,
 * 59 or even 0 - which used to be filed as-is and then dragged term averages
 * and final grades on the report card down with them. The floor is applied in
 * three places that must never disagree: the box the teacher types in (raised
 * when the field loses focus or the form is submitted), the two controller save
 * paths, and GradeModel as the last gate before a row exists.
 *
 * These tests pin the pure part of that rule - clamp_report_card_grade() - plus
 * the model callback, and then assert that the page and the controller still
 * call into it, so a future edit cannot quietly drop the floor.
 *
 * @internal
 */
final class ReportCardGradeFloorTest extends CIUnitTestCase
{
    public function testFloorIsSixtyAndTheScaleTopIsAHundred(): void
    {
        $this->assertSame(60.0, min_report_card_grade(), 'the report card floor is 60');
        $this->assertSame(100.0, max_report_card_grade(), 'the scale still tops out at 100');
    }

    /**
     * @dataProvider clampProvider
     */
    public function testClampReportCardGrade($entered, ?float $expected, string $because): void
    {
        $this->assertSame($expected, clamp_report_card_grade($entered), $because);
    }

    /**
     * @return list<array{0: mixed, 1: float|null, 2: string}>
     */
    public static function clampProvider(): array
    {
        return [
            // --- Below the floor: raised, never rejected ----------------------
            [44, 60.0, 'a teacher who types 44 gets 60'],
            [59, 60.0, '59 is one short of the floor and is raised'],
            ['44', 60.0, 'the value arrives as a string from the form'],
            [59.9, 60.0, 'a tenth below the floor is still raised'],
            [0, 60.0, 'zero cannot be filed as a report card grade'],
            [-12, 60.0, 'a negative entry cannot punch a hole in an average'],

            // --- On scale: untouched ------------------------------------------
            [60, 60.0, 'the floor itself passes through'],
            ['60.0', 60.0, 'a numeric string keeps its value'],
            ['78.5', 78.5, 'decimals survive the clamp'],
            [100, 100.0, 'the top of the scale passes through'],
            [120, 100.0, 'an over-scale entry is pulled back to 100'],

            // --- Nothing entered: no grade is invented ------------------------
            ['', null, 'an empty box means "no grade", not 60'],
            [null, null, 'a missing field means "no grade"'],
            ['P', null, 'symbol grades belong to the non-numerical path'],
            ['NO/NA', null, 'symbol grades belong to the non-numerical path'],
            ['abc', null, 'free text is not silently turned into a grade'],
            [true, null, 'a boolean is not a grade'],
            [[], null, 'an array is not a grade'],
        ];
    }

    public function testEveryClampedValueLandsOnTheReportCardScale(): void
    {
        foreach ([0, 1, 43.9, 59, 60, 74.5, 100, 1000, '88'] as $entered) {
            $grade = clamp_report_card_grade($entered);

            $this->assertNotNull($grade, "{$entered} is a number and must produce a grade");
            $this->assertGreaterThanOrEqual(min_report_card_grade(), $grade, "{$entered} must not stay below the floor");
            $this->assertLessThanOrEqual(max_report_card_grade(), $grade, "{$entered} must not exceed the scale");
        }
    }

    /**
     * The Enter Grades page also posts through saveGrades() and through grade
     * recommendations, and both bypass the page's own JavaScript clamp, so the
     * rule has to hold on the server as well.
     */
    public function testGradesModelRaisesOffScaleGradesOnTheWayIn(): void
    {
        // Built without its constructor on purpose: the callback is pure, and
        // `new GradeModel()` would connect to the test database group, which
        // this suite deliberately does not configure.
        $model = (new ReflectionClass(App\Models\GradeModel::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(App\Models\GradeModel::class, 'applyReportCardFloor');
        $method->setAccessible(true);

        $grade = static fn (array $data): mixed => $method->invoke($model, $data)['data']['grade'];

        $this->assertSame(60.0, $grade(['data' => ['grade' => 44]]), 'a 44 reaching the model is stored as 60');
        $this->assertSame(60.0, $grade(['data' => ['grade' => '59']]), 'a posted 59 is stored as 60');
        $this->assertSame(60.0, $grade(['data' => ['grade' => 0]]), 'a zeroed grade is raised, not filed');
        $this->assertSame(82.5, $grade(['data' => ['grade' => 82.5]]), 'an on-scale grade is untouched');
        $this->assertSame('P', $grade(['data' => ['grade' => 'P']]), 'a symbol grade passes through');
        $this->assertNull($grade(['data' => ['grade' => null]]), 'an empty grade stays empty');

        $untouched = ['student_id' => 1, 'subject_id' => 2];
        $this->assertSame(
            ['data' => $untouched],
            $method->invoke($model, ['data' => $untouched]),
            'data without a grade is returned unchanged, envelope and all'
        );
    }

    /**
     * The enforcement points must stay wired together: the page's own floor and
     * the two save paths that bypass it.
     */
    public function testThePageAndBothSavePathsApplyTheFloor(): void
    {
        $view = file_get_contents($this->appPath('app/Views/teacher/grades_table.php'));

        $this->assertStringContainsString(
            'onsubmit="clampAllGrades(this);',
            $view,
            'the bulk form must clamp every box before it posts'
        );
        $this->assertSame(
            2,
            substr_count($view, 'min="<?= (float) min_report_card_grade() ?>"'),
            'both numerical grade inputs must carry the floor as their min attribute'
        );
        $this->assertStringNotContainsString(
            'min="0" max="100" step="0.1"',
            $view,
            'no grade input may keep the old zero floor'
        );

        $controller = file_get_contents($this->appPath('app/Controllers/Teacher/Dashboard.php'));

        $this->assertStringContainsString(
            'clamp_report_card_grade($gradeFloat)',
            $controller,
            'the bulk save must raise a typed 44 to 60 before storing it'
        );
        $this->assertStringContainsString(
            "\$grade = clamp_report_card_grade(\$this->request->getPost('grade'));",
            $controller,
            'the single-grade save must clamp the posted value'
        );
        $this->assertStringContainsString(
            '$value = clamp_report_card_grade($value);',
            $controller,
            'submitted recommendations obey the floor too'
        );
        $this->assertStringNotContainsString(
            "'grade' => 'required|decimal|greater_than_equal_to[60]",
            $controller,
            'the single-grade save must clamp a low grade instead of rejecting it'
        );
    }

    private function appPath(string $relative): string
    {
        $root = defined('ROOTPATH') ? ROOTPATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $this->assertFileExists($path, 'expected to inspect ' . $relative);

        return $path;
    }
}
