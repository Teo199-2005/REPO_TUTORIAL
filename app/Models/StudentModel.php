<?php
namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'student_id', 'user_id', 'lrn', 'student_type', 'previous_school', 'previous_school_year', 'first_name', 'middle_name', 'last_name', 'suffix',
        'gender', 'date_of_birth', 'place_of_birth', 'nationality', 'religion',
        'height_cm', 'weight_kg', 'ethnicity', 'bmi', 'nutrition_status',
        'contact_number', 'phone', 'email', 'address', 'emergency_contact_name',
        'emergency_contact_number', 'emergency_contact_relationship', 'photo_path', 'id_photo_path',
        'enrollment_status', 'grade_level', 'section_id', 'school_year', 'temp_password', 'can_view_report_card'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = false;

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
        'first_name' => 'required|max_length[100]',
        'last_name' => 'required|max_length[100]',
        'gender' => 'required|in_list[Male,Female]',
        'date_of_birth' => 'required|valid_date',
        'enrollment_status' => 'permit_empty|in_list[pending,approved,rejected,enrolled,graduated,dropped,transferred]',
        'grade_level' => 'permit_empty|in_list[0,1,2,3,4,5,6]',
        'email' => 'permit_empty|valid_email',
        'lrn' => 'permit_empty|max_length[20]|is_unique[students.lrn,id,{id}]'
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateStudentId', 'setDefaultReportCardAccess'];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Generate unique student ID before insert
     */
    protected function generateStudentId(array $data)
    {
        if (isset($data['data']['enrollment_status']) && $data['data']['enrollment_status'] === 'approved') {
            if (empty($data['data']['student_id'])) {
                $data['data']['student_id'] = $this->createUniqueStudentId();
            }
        }
        return $data;
    }

    /**
     * Set default report card access to disabled for new students
     */
    protected function setDefaultReportCardAccess(array $data)
    {
        if (!isset($data['data']['can_view_report_card'])) {
            $data['data']['can_view_report_card'] = 0;
        }
        return $data;
    }

    /**
     * Create a unique student ID
     */
    public function createUniqueStudentId(): string
    {
        $year = date('Y');
        $lastStudent = $this->select('student_id')
            ->where('student_id LIKE', $year . '%')
            ->orderBy('student_id', 'DESC')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent['student_id'], 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get student with section information
     */
    public function getStudentWithSection($id)
    {
        return $this->select('students.*, sections.section_name, sections.grade_level as section_grade')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('students.id', $id)
            ->first();
    }

    /**
     * Get students by enrollment status
     */
    public function getByEnrollmentStatus($status)
    {
        return $this->where('enrollment_status', $status)->findAll();
    }

    /**
     * Get students by grade level
     */
    public function getByGradeLevel($gradeLevel)
    {
        return $this->where('grade_level', $gradeLevel)->findAll();
    }

    /**
     * Get students by section
     */
    public function getBySection($sectionId)
    {
        return $this->where('section_id', $sectionId)->findAll();
    }

    /**
     * Height, weight, and ethnicity required for principal nutrition report.
     */
    public static function isNutritionProfileComplete(?array $student): bool
    {
        if ($student === null) {
            return false;
        }

        $h = $student['height_cm'] ?? null;
        $w = $student['weight_kg'] ?? null;
        $e = $student['ethnicity'] ?? null;

        return $h !== null && $h !== '' && (float) $h > 0
            && $w !== null && $w !== '' && (float) $w > 0
            && $e !== null && trim((string) $e) !== '';
    }

    /**
     * Get full name of student
     */
    public function getFullName($student): string
    {
        $name = $student['first_name'];
        if (!empty($student['middle_name'])) {
            $name .= ' ' . $student['middle_name'];
        }
        $name .= ' ' . $student['last_name'];
        if (!empty($student['suffix'])) {
            $name .= ' ' . $student['suffix'];
        }
        return $name;
    }

    /**
     * Approve student enrollment
     */
    public function approveEnrollment($id, $sectionId = null)
    {
        $data = [
            'enrollment_status' => 'approved',
            'student_id' => $this->createUniqueStudentId()
        ];

        // Auto-assign section if not provided
        if (!$sectionId) {
            $student = $this->find($id);
            if ($student && !empty($student['grade_level'])) {
                $sectionModel = new \App\Models\SectionModel();
                $best = $sectionModel->selectBestAvailableSection((int) $student['grade_level'], $student['school_year'] ?? null);
                if ($best) {
                    $sectionId = (int) $best['id'];
                }
            }
        }

        if ($sectionId) {
            $data['section_id'] = $sectionId;
        }

        return $this->update($id, $data);
    }

    /**
     * Reject student enrollment
     */
    public function rejectEnrollment($id, $reason = null)
    {
        return $this->update($id, [
            'enrollment_status' => 'rejected'
        ]);
    }

    /**
     * Enroll approved student
     */
    public function enrollStudent($id, $sectionId, $schoolYear)
    {
        return $this->update($id, [
            'enrollment_status' => 'enrolled',
            'section_id' => $sectionId,
            'school_year' => $schoolYear
        ]);
    }
}
