<?php
namespace App\Models;

use CodeIgniter\Model;

class GradeModel extends Model
{
    protected $table = 'grades';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'student_id', 'subject_id', 'teacher_id', 'school_year',
        'term', 'grade', 'remarks', 'date_recorded'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'student_id' => 'required|integer',
        'subject_id' => 'required|integer',
        'teacher_id' => 'required|integer',
        'school_year' => 'required|max_length[9]',
        'term' => 'required|integer|greater_than[0]|less_than[4]',
        'grade' => 'permit_empty|decimal|greater_than_equal_to[60]|less_than_equal_to[100]'
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['setDateRecorded', 'applyReportCardFloor'];
    protected $afterInsert = [];
    protected $beforeUpdate = ['setDateRecorded', 'applyReportCardFloor'];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Set date recorded before insert/update
     */
    protected function setDateRecorded(array $data)
    {
        if (isset($data['data']['grade']) && !empty($data['data']['grade'])) {
            $data['data']['date_recorded'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    /**
     * Keep every stored numeric grade on the report card scale.
     *
     * The Enter Grades page clamps the box as the teacher leaves it and the
     * controller clamps again, but the model is the last gate before the row
     * exists: a stray 44 or 59 becomes 60 (and anything over 100 becomes 100)
     * so no writer - including a future one - can file an off-scale grade.
     * Symbol grades (P, AP, D, B, NO/NA) are not numeric and pass untouched.
     */
    protected function applyReportCardFloor(array $data)
    {
        if (! isset($data['data']['grade'])) {
            return $data;
        }

        if (! function_exists('clamp_report_card_grade')) {
            helper('grade');
        }

        $floored = clamp_report_card_grade($data['data']['grade']);

        if ($floored !== null) {
            $data['data']['grade'] = $floored;
        }

        return $data;
    }

    /**
     * Get grades for a student in a specific term
     */
    public function getStudentTermGrades($studentId, $schoolYear, $term)
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT g.*, s.subject_name, s.subject_code, s.units
            FROM grades g
            JOIN subjects s ON s.id = g.subject_id
            JOIN students st ON st.id = g.student_id
            JOIN section_subjects ss ON ss.subject_id = s.id AND ss.section_id = st.section_id
            WHERE g.student_id = ? 
            AND g.school_year = ? 
            AND g.term = ?
            AND g.deleted_at IS NULL
            ORDER BY s.subject_name ASC
        ", [$studentId, $schoolYear, $term])->getResultArray();
    }

    /**
     * Get all grades for a student in a school year
     */
    public function getStudentYearGrades($studentId, $schoolYear)
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT g.*, s.subject_name, s.subject_code, s.units
            FROM grades g
            JOIN subjects s ON s.id = g.subject_id
            JOIN students st ON st.id = g.student_id
            JOIN section_subjects ss ON ss.subject_id = s.id AND ss.section_id = st.section_id
            WHERE g.student_id = ? 
            AND g.school_year = ?
            AND g.deleted_at IS NULL
            ORDER BY s.subject_name ASC, g.term ASC
        ", [$studentId, $schoolYear])->getResultArray();
    }

    /**
     * Calculate term average for a student
     */
    public function getTermAverage($studentId, $schoolYear, $term)
    {
        $grades = $this->select('grades.grade, subjects.units')
            ->join('subjects', 'subjects.id = grades.subject_id')
            ->where('grades.student_id', $studentId)
            ->where('grades.school_year', $schoolYear)
            ->where('grades.term', $term)
            ->where('grades.grade IS NOT NULL')
            ->findAll();

        if (empty($grades)) {
            return null;
        }

        $totalWeightedGrades = 0;
        $totalUnits = 0;

        foreach ($grades as $grade) {
            $totalWeightedGrades += $grade['grade'] * $grade['units'];
            $totalUnits += $grade['units'];
        }

        $average = $totalUnits > 0 ? round($totalWeightedGrades / $totalUnits, 2) : null;

        log_message('debug', "Term Average Calculation - Student: $studentId, Term: $term, Total Weighted: $totalWeightedGrades, Total Units: $totalUnits, Average: $average");

        return $average;
    }

    /**
     * Calculate final average for a student in a school year
     */
    public function getFinalAverage($studentId, $schoolYear)
    {
        $termAverages = [];

        for ($term = 1; $term <= 3; $term++) {
            $average = $this->getTermAverage($studentId, $schoolYear, $term);
            if ($average !== null) {
                $termAverages[] = $average;
            }
        }

        if (empty($termAverages)) {
            return null;
        }

        return round(array_sum($termAverages) / count($termAverages), 2);
    }

    /**
     * Get grades for a teacher's subject
     */
    public function getTeacherSubjectGrades($teacherId, $subjectId, $schoolYear, $term = null)
    {
        $builder = $this->select('grades.*, students.first_name, students.last_name, students.student_id as student_number')
            ->join('students', 'students.id = grades.student_id')
            ->where('grades.teacher_id', $teacherId)
            ->where('grades.subject_id', $subjectId)
            ->where('grades.school_year', $schoolYear);

        if ($term) {
            $builder->where('grades.term', $term);
        }

        return $builder->orderBy('students.last_name', 'ASC')
            ->orderBy('students.first_name', 'ASC')
            ->findAll();
    }

    /**
     * Get class average for a subject and term
     */
    public function getClassAverage($subjectId, $schoolYear, $term)
    {
        $result = $this->select('AVG(grade) as average')
            ->where('subject_id', $subjectId)
            ->where('school_year', $schoolYear)
            ->where('term', $term)
            ->where('grade IS NOT NULL')
            ->first();

        return $result ? round($result['average'], 2) : null;
    }

    /**
     * Get grade distribution for a subject
     */
    public function getGradeDistribution($subjectId, $schoolYear, $term)
    {
        $grades = $this->select('grade')
            ->where('subject_id', $subjectId)
            ->where('school_year', $schoolYear)
            ->where('term', $term)
            ->where('grade IS NOT NULL')
            ->findAll();

        $distribution = [
            'excellent' => 0, // 90-100
            'very_good' => 0, // 85-89
            'good' => 0,      // 80-84
            'fair' => 0,      // 75-79
            'failing' => 0    // Below 75
        ];

        foreach ($grades as $grade) {
            $gradeValue = $grade['grade'];

            if ($gradeValue >= 90) {
                $distribution['excellent']++;
            } elseif ($gradeValue >= 85) {
                $distribution['very_good']++;
            } elseif ($gradeValue >= 80) {
                $distribution['good']++;
            } elseif ($gradeValue >= 75) {
                $distribution['fair']++;
            } else {
                $distribution['failing']++;
            }
        }

        return $distribution;
    }

    /**
     * Check if grade exists for student, subject, and term
     */
    public function gradeExists($studentId, $subjectId, $schoolYear, $term)
    {
        return $this->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('school_year', $schoolYear)
            ->where('term', $term)
            ->first() !== null;
    }

    /**
     * Update or insert grade
     */
    public function upsertGrade($data)
    {
        $existing = $this->where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('school_year', $data['school_year'])
            ->where('term', $data['term'])
            ->first();

        if ($existing) {
            // Update existing grade
            $updateData = array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]);
            return $this->update($existing['id'], $updateData);
        } else {
            // Insert new grade
            $insertData = array_merge($data, [
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return $this->insert($insertData);
        }
    }

    /**
     * Get student ranking in class for a term
     */
    public function getStudentRanking($studentId, $schoolYear, $term)
    {
        // Get all students in the same grade level and section
        $studentModel = new StudentModel();
        $student = $studentModel->find($studentId);

        if (!$student) {
            return null;
        }

        // Get all students in the same section
        $classmates = $studentModel->where('section_id', $student['section_id'])
            ->where('enrollment_status', 'enrolled')
            ->findAll();

        $rankings = [];

        foreach ($classmates as $classmate) {
            $average = $this->getTermAverage($classmate['id'], $schoolYear, $term);
            if ($average !== null) {
                $rankings[] = [
                    'student_id' => $classmate['id'],
                    'average' => $average
                ];
            }
        }

        // Sort by average descending
        usort($rankings, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        // Find student's rank
        foreach ($rankings as $index => $ranking) {
            if ($ranking['student_id'] == $studentId) {
                return [
                    'rank' => $index + 1,
                    'total_students' => count($rankings),
                    'average' => $ranking['average']
                ];
            }
        }

        return null;
    }
}
