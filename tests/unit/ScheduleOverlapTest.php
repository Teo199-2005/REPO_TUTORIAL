<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Time maths behind every schedule conflict check.
 *
 * The section page, the batch save and the teacher grid all decide "does this
 * block collide?" through schedule_times_overlap(), which mirrors the SQL
 * predicate `ts.start_time < :newEnd AND ts.end_time > :newStart`. These tests
 * pin the boundary cases that make the difference between a legal timetable and
 * a corrupted one - in particular that back-to-back blocks are allowed while
 * any shared minute is not, and that the zero-length assignment placeholder
 * rows (00:00:00-00:00:00) occupy no time at all.
 *
 * @internal
 */
final class ScheduleOverlapTest extends CIUnitTestCase
{
    /**
     * @dataProvider overlapProvider
     */
    public function testScheduleTimesOverlap(
        string $startA,
        string $endA,
        string $startB,
        string $endB,
        bool $expected,
        string $because
    ): void {
        $this->assertSame(
            $expected,
            schedule_times_overlap($startA, $endA, $startB, $endB),
            $because
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: string}>
     */
    public static function overlapProvider(): array
    {
        return [
            // --- Identical windows -------------------------------------------
            ['08:00', '09:00', '08:00', '09:00', true, 'the same window conflicts with itself'],

            // --- Partial overlap --------------------------------------------
            ['08:00', '10:00', '09:00', '11:00', true, 'a shared 09:00-10:00 block conflicts'],
            ['09:00', '11:00', '08:00', '10:00', true, 'overlap is symmetric'],

            // --- Containment -------------------------------------------------
            ['08:00', '12:00', '09:00', '10:00', true, 'a block inside another conflicts'],
            ['09:00', '10:00', '08:00', '12:00', true, 'containment is symmetric'],

            // --- Adjacent: the boundary case that must stay legal ------------
            ['08:00', '09:00', '09:00', '10:00', false, 'back-to-back blocks are adjacent, not overlapping'],
            ['09:00', '10:00', '08:00', '09:00', false, 'adjacency is symmetric'],

            // --- Disjoint ----------------------------------------------------
            ['08:00', '09:00', '10:00', '11:00', false, 'windows separated by a gap do not overlap'],
            ['10:00', '11:00', '08:00', '09:00', false, 'disjointness is symmetric'],

            // --- Zero-length placeholders ------------------------------------
            ['00:00', '00:00', '08:00', '09:00', false, 'an unassigned placeholder occupies no time'],
            ['08:00', '09:00', '00:00', '00:00', false, 'an unassigned placeholder occupies no time'],
            ['00:00', '00:00', '00:00', '00:00', false, 'two placeholders never conflict'],

            // --- Malformed input cannot invent a conflict ---------------------
            ['', '09:00', '08:00', '09:00', false, 'an empty time is not a conflict'],
            ['08:00', '09:00', '', '', false, 'an empty time is not a conflict'],
            ['08:00', '07:00', '07:30', '08:00', false, 'a reversed window is ignored'],
            ['25:00', '26:00', '08:00', '09:00', false, 'an out-of-range time is ignored'],
        ];
    }

    /**
     * The fixed-width normalisation every overlap comparison depends on: an
     * 'HH:MM' posted by <input type="time"> must compare correctly against the
     * 'HH:MM:SS' the database returns, or a real conflict slips through.
     */
    public function testNormalizeTimePadsToFixedWidth(): void
    {
        $this->assertSame('08:00:00', schedule_normalize_time('08:00'));
        $this->assertSame('08:30:00', schedule_normalize_time('8:30'));
        $this->assertSame('09:15:00', schedule_normalize_time('09:15:00'));
        $this->assertSame('23:59:59', schedule_normalize_time('23:59:59'));
        $this->assertNull(schedule_normalize_time(''));
        $this->assertNull(schedule_normalize_time('24:00'));
        $this->assertNull(schedule_normalize_time('08:60'));
        $this->assertNull(schedule_normalize_time('noon'));
    }

    /**
     * A native time input posts 'HH:MM' and the column stores 'HH:MM:SS'; the
     * pair below is the exact case that used to be missed by comparing the two
     * raw strings.
     */
    public function testMixedPrecisionTimesStillOverlap(): void
    {
        $this->assertTrue(
            schedule_times_overlap('09:00', '11:00', '09:00:00', '10:00:00'),
            'a stored 09:00:00-10:00:00 block must block a posted 09:00-11:00 block'
        );
    }

    /**
     * One block may span several hours, so a long block must reject anything
     * landing inside it, not just a block of the same length.
     */
    public function testLongBlockSwallowsShortOnes(): void
    {
        foreach ([['08:00', '08:01'], ['12:59', '13:00'], ['09:00', '09:30']] as [$start, $end]) {
            $this->assertTrue(
                schedule_times_overlap($start, $end, '08:00', '13:00'),
                sprintf('%s-%s sits inside 08:00-13:00 and must conflict', $start, $end)
            );
        }
    }

    /**
     * The schedule writers share one validator so the same bad input cannot be
     * accepted on one page and rejected on another.
     */
    public function testValidateSlotRejectsBlockingInputProblems(): void
    {
        $valid = [
            'section_id'  => 21,
            'subject_id'  => 3,
            'teacher_id'  => 7,
            'day_of_week' => 'Monday',
            'start_time'  => '08:00',
            'end_time'    => '09:00',
            'room'        => 'Room 1',
        ];

        $parsed = schedule_validate_slot($valid);
        $this->assertTrue($parsed['ok'], 'a well-formed block is accepted');
        $this->assertSame('08:00:00', $parsed['slot']['start_time'], 'times are normalised before writing');
        $this->assertSame('09:00:00', $parsed['slot']['end_time'], 'times are normalised before writing');

        foreach (['section_id' => 0, 'subject_id' => 0, 'teacher_id' => 0] as $field => $value) {
            $input         = $valid;
            $input[$field] = $value;
            $this->assertFalse(
                schedule_validate_slot($input)['ok'],
                sprintf('a block without a %s is rejected instead of being written as 0/NULL', $field)
            );
        }

        // day_of_week is an ENUM and the server runs without STRICT_TRANS_TABLES,
        // so an invalid day would be stored as '' and become invisible.
        foreach (['', 'Cunday', 'Saturday', 'MONDAY'] as $day) {
            $input                = $valid;
            $input['day_of_week'] = $day;
            $this->assertFalse(
                schedule_validate_slot($input)['ok'],
                sprintf('day "%s" is rejected', $day)
            );
        }

        // Surrounding whitespace is not a wrong day, it is the same day typed
        // sloppily, so it is trimmed into the exact ENUM value instead of
        // costing the admin a save.
        $input                = $valid;
        $input['day_of_week'] = ' Monday ';
        $trimmed              = schedule_validate_slot($input);
        $this->assertTrue($trimmed['ok'], 'a padded day is accepted');
        $this->assertSame('Monday', $trimmed['slot']['day_of_week'], 'and is trimmed to the ENUM value');

        $input             = $valid;
        $input['end_time'] = '08:00';
        $this->assertFalse(schedule_validate_slot($input)['ok'], 'a zero-length block is rejected');

        $input             = $valid;
        $input['end_time'] = '07:00';
        $this->assertFalse(schedule_validate_slot($input)['ok'], 'an end before the start is rejected');

        $input               = $valid;
        $input['start_time'] = 'soon';
        $this->assertFalse(schedule_validate_slot($input)['ok'], 'a non-time string is rejected');

        $input         = $valid;
        $input['room'] = str_repeat('R', schedule_room_max_length() + 1);
        $this->assertFalse(schedule_validate_slot($input)['ok'], 'an over-long room is rejected, not truncated');

        $input         = $valid;
        $input['room'] = str_repeat('R', schedule_room_max_length());
        $this->assertTrue(schedule_validate_slot($input)['ok'], 'a room at the column limit is accepted');
    }

    /**
     * Every day the grid renders must be schedulable, and nothing else - the
     * two lists drifting apart is how Saturday rows could be written into a
     * timetable that has no Saturday tab.
     */
    public function testAllowedDaysMatchTheGrid(): void
    {
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            schedule_allowed_days()
        );
        $this->assertSame(schedule_allowed_days(), schedule_conflict_weekdays());
    }

    /**
     * The day predicates split teacher_schedules rows into the two kinds the
     * section page cares about: rows that occupy a timetable slot, and the
     * assignment rows that only record WHO teaches an item.
     */
    public function testDayPredicatesSeparateSlotsFromAssignments(): void
    {
        $list = "'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'";

        $this->assertSame($list, schedule_day_list_sql());
        $this->assertSame('ts.day_of_week IN (' . $list . ')', schedule_weekday_day_sql());
        $this->assertSame('ts.day_of_week NOT IN (' . $list . ')', schedule_assignment_day_sql());

        // The alias is honoured, because the assignment query qualifies rows.
        $this->assertSame(
            'sched.day_of_week NOT IN (' . $list . ')',
            schedule_assignment_day_sql('sched')
        );

        // 'TBD' is what the assignment flow writes and '' is what MySQL stores
        // for an invalid ENUM value on this non-strict server. Neither may be a
        // schedulable day, or an assignment row would look like a timetable row.
        $this->assertNotContains('TBD', schedule_allowed_days(), 'a TBD day is never schedulable');
        $this->assertNotContains('', schedule_allowed_days(), 'a blank day is never schedulable');
    }

    /**
     * Candidate teachers for one subject.
     *
     * The section page pre-selects the FIRST entry, so this list decides which
     * teacher appears in the Teacher field. It used to come straight from a UNION
     * whose order MySQL never promised, which is how the field could show the
     * section adviser or an arbitrary active teacher instead of the assignee.
     *
     * The model is built without its constructor on purpose: teacherChoices() is
     * pure, and `new TeacherScheduleModel()` would connect to the test database
     * group, which this suite deliberately does not configure.
     */
    public function testTeacherChoicesLeadWithTheAssignedTeacher(): void
    {
        /** @var \App\Models\TeacherScheduleModel $model */
        $model = (new \ReflectionClass(\App\Models\TeacherScheduleModel::class))->newInstanceWithoutConstructor();

        $teachers = [
            ['id' => 1, 'name' => 'Adviser Alpha', 'employment_status' => 'active'],
            ['id' => 2, 'name' => 'Assigned Bravo', 'employment_status' => 'active'],
            ['id' => 3, 'name' => 'Other Charlie', 'employment_status' => 'active'],
            ['id' => 4, 'name' => 'Retired Delta', 'employment_status' => 'inactive'],
        ];

        $choices = $model->teacherChoices(2, 1, $teachers);

        $this->assertSame(2, $choices[0]['id'], 'the assigned teacher heads the list');
        $this->assertTrue($choices[0]['assigned'], 'and is flagged as the assignment');
        $this->assertSame(1, $choices[1]['id'], 'the section adviser comes next');
        $this->assertTrue($choices[1]['adviser'], 'and is flagged as the adviser');

        $this->assertSame(
            [2, 1, 3],
            array_column($choices, 'id'),
            'every active teacher stays selectable, inactive staff do not, and nobody repeats'
        );
        $this->assertSame(
            1,
            count(array_filter($choices, static fn (array $choice): bool => $choice['assigned'])),
            'exactly one entry is presented as the assignment'
        );

        // No assignment: nothing may be presented as the assigned teacher, but
        // the active staff must still be offered so the admin can pick by hand.
        $unassigned = $model->teacherChoices(null, 0, $teachers);

        $this->assertSame(
            [],
            array_filter($unassigned, static fn (array $choice): bool => $choice['assigned']),
            'an unassigned subject must not flag anybody'
        );
        // Every active teacher is still offered when nobody is assigned, so the
        // admin can pick a teacher by hand.
        $this->assertSame(
            [1, 2, 3],
            array_column($unassigned, 'id'),
            'an unassigned subject still lists the whole active staff'
        );

        // An assignee who has since left still has to label the block.
        $inactive = $model->teacherChoices(4, 0, $teachers);

        $this->assertSame(4, $inactive[0]['id'], 'an inactive assignee is still shown first');
        $this->assertSame('Retired Delta', $inactive[0]['name']);

        // A stale id pointing at a deleted teacher must still render something.
        $missing = $model->teacherChoices(9, 0, $teachers);

        $this->assertSame(9, $missing[0]['id']);
        $this->assertSame('Teacher #9', $missing[0]['name'], 'a stale assignee still gets a label');

        // The adviser is only a fallback label when nobody is assigned: it must
        // never outrank the assignee.
        $adviserOnly = $model->teacherChoices(null, 1, $teachers);

        $this->assertSame(1, $adviserOnly[0]['id'], 'the adviser leads only when unassigned');
        $this->assertFalse($adviserOnly[0]['assigned'], 'and is not mislabelled as the assignment');
    }
}
