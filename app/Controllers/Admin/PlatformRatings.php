<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\TeacherModel;

class PlatformRatings extends BaseController
{
    public function index()
    {
        helper('admin_access');

        if (! auth()->loggedIn() || ! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $user = auth()->user();
        if (! admin_staff_can_view_page($user, 'platform_ratings')) {
            return redirect()->to(base_url('/'))->with('error', 'You do not have access to this admin area.');
        }

        $db = \Config\Database::connect();

        $studentModel = model(StudentModel::class);
        $eligibleStudents = $studentModel
            ->whereIn('enrollment_status', ['approved', 'enrolled'])
            ->where('user_id >', 0)
            ->countAllResults();

        $teacherModel = model(TeacherModel::class);
        $eligibleTeachers = $teacherModel
            ->where('user_id >', 0)
            ->countAllResults();

        $emptyViewData = [
            'title'             => 'Platform feedback - CSCS Tap n Track',
            'eligibleStudents'  => $eligibleStudents,
            'eligibleTeachers'  => $eligibleTeachers,
            'ratedStudents'     => 0,
            'ratedTeachers'     => 0,
            'totalRatings'      => 0,
            'avgRating'         => null,
            'breakdown'         => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'pctStudents'       => null,
            'pctTeachers'       => null,
            'responses'         => [],
            'roleFilter'        => null,
            'hasTermColumns'    => false,
            'setupRequired'     => false,
        ];

        if (! $db->tableExists('platform_ratings')) {
            $emptyViewData['setupRequired'] = true;

            return view('admin/platform_ratings', $emptyViewData);
        }

        $hasTermColumns = $db->fieldExists('school_year', 'platform_ratings')
            && $db->fieldExists('term', 'platform_ratings');

        $ratingsTable = $db->table('platform_ratings');
        $totalRatings  = $ratingsTable->countAllResults();
        $ratedStudents = $db->table('platform_ratings')->where('responder_role', 'student')->countAllResults();
        $ratedTeachers = $db->table('platform_ratings')->where('responder_role', 'teacher')->countAllResults();

        $avgRow = $db->query('SELECT AVG(rating) AS avg_rating FROM platform_ratings')->getRowArray();
        $avg    = isset($avgRow['avg_rating']) && $avgRow['avg_rating'] !== null
            ? round((float) $avgRow['avg_rating'], 2)
            : null;

        $breakdownRows = $db->query(
            'SELECT rating, COUNT(*) AS cnt FROM platform_ratings GROUP BY rating ORDER BY rating ASC'
        )->getResultArray();
        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($breakdownRows as $row) {
            $r = (int) $row['rating'];
            if ($r >= 1 && $r <= 5) {
                $breakdown[$r] = (int) $row['cnt'];
            }
        }

        $pctStudents = $eligibleStudents > 0
            ? round(100 * $ratedStudents / $eligibleStudents, 1)
            : null;
        $pctTeachers = $eligibleTeachers > 0
            ? round(100 * $ratedTeachers / $eligibleTeachers, 1)
            : null;

        $roleGet = $this->request->getGet('role');
        $roleFilter = in_array($roleGet, ['student', 'teacher'], true) ? $roleGet : null;

        $responses = $this->fetchResponsesWithNames($db, $roleFilter, $hasTermColumns);

        return view('admin/platform_ratings', [
            'title'             => 'Platform feedback - CSCS Tap n Track',
            'eligibleStudents'  => $eligibleStudents,
            'eligibleTeachers'  => $eligibleTeachers,
            'ratedStudents'     => $ratedStudents,
            'ratedTeachers'     => $ratedTeachers,
            'totalRatings'      => $totalRatings,
            'avgRating'         => $avg,
            'breakdown'         => $breakdown,
            'pctStudents'       => $pctStudents,
            'pctTeachers'       => $pctTeachers,
            'responses'         => $responses,
            'roleFilter'        => $roleFilter,
            'hasTermColumns'    => $hasTermColumns,
            'setupRequired'     => false,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchResponsesWithNames(
        \CodeIgniter\Database\BaseConnection $db,
        ?string $roleFilter,
        bool $hasTermColumns
    ): array {
        $termSelect = $hasTermColumns
            ? "pr.school_year,\n    pr.term,"
            : "NULL AS school_year,\n    NULL AS term,";

        $sql = <<<SQL
SELECT
    pr.id,
    pr.user_id,
    {$termSelect}
    pr.responder_role,
    pr.rating,
    pr.comment,
    pr.created_at,
    pr.updated_at,
    u.username,
    u.email AS user_email,
    TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student_name,
    TRIM(CONCAT(COALESCE(t.first_name, ''), ' ', COALESCE(t.last_name, ''))) AS teacher_name
FROM platform_ratings pr
INNER JOIN users u ON u.id = pr.user_id
LEFT JOIN students s ON s.user_id = pr.user_id AND pr.responder_role = 'student'
LEFT JOIN teachers t ON t.user_id = pr.user_id AND pr.responder_role = 'teacher'
SQL;

        if ($roleFilter !== null) {
            $sql .= ' WHERE pr.responder_role = ? ORDER BY pr.updated_at DESC';
            $rows = $db->query($sql, [$roleFilter])->getResultArray();
        } else {
            $sql .= ' ORDER BY pr.updated_at DESC';
            $rows = $db->query($sql)->getResultArray();
        }
        foreach ($rows as &$row) {
            $name = '';
            if ($row['responder_role'] === 'student') {
                $name = trim((string) $row['student_name']);
            } else {
                $name = trim((string) $row['teacher_name']);
            }
            if ($name === '') {
                $name = (string) $row['username'];
            }
            $row['display_name'] = $name;
        }
        unset($row);

        return $rows;
    }
}

