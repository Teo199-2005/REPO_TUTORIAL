<?php
namespace App\Models;

use CodeIgniter\Model;

class TeacherModel extends Model
{
    protected $table = 'teachers';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'employee_id', 'user_id', 'first_name', 'middle_name', 'last_name', 'suffix',
        'gender', 'date_of_birth', 'contact_number', 'email', 'address',
        'department', 'position', 'specialization', 'date_hired',
        'employment_status', 'personnel_edit_enabled', 'photo_path', 'license_number',
        'tin', 'personnel_category', 'fund_source', 'designation', 'nature_of_appointment',
        'baccalaureate_degree', 'prc_specialization', 'prc_major_units_percent', 'minor',
        'masters_degree', 'government_employee_no', 'hiring_arrangement', 'religion',
        'ethnic_group', 'item_status', 'civil_status', 'philsys_number', 'eligibility',
        'date_first_service', 'date_first_service_new_station',
        'registration_status', 'registration_notes',
        'registration_reviewed_at', 'registration_reviewed_by',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'personnel_edit_enabled' => 'boolean'
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'employee_id' => 'permit_empty|is_unique[teachers.employee_id,id,{id}]',
        'first_name' => 'required|max_length[100]',
        'last_name' => 'required|max_length[100]',
        'gender' => 'permit_empty|in_list[Male,Female]',
        'email' => 'required|valid_email|is_unique[teachers.email,id,{id}]',
        'employment_status' => 'permit_empty|in_list[active,inactive,on_leave,resigned,terminated]',
        'position' => 'permit_empty|max_length[100]',
        'license_number' => 'permit_empty|max_length[20]'
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    
    /**
     * Update validation rules for profile updates
     */
    public function getProfileUpdateRules($teacherId)
    {
        return [
            'first_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'middle_name' => 'permit_empty|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'email' => "required|valid_email|max_length[255]|is_unique[teachers.email,id,{$teacherId}]",
            'contact_number' => phone_validation_rule(),
            'address' => 'permit_empty|max_length[255]'
        ];
    }
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateEmployeeId'];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Generate unique employee ID before insert
     */
    protected function generateEmployeeId(array $data)
    {
        if (empty($data['data']['employee_id'])) {
            $data['data']['employee_id'] = $this->createUniqueEmployeeId();
        }
        return $data;
    }

    /**
     * Create a unique employee ID
     */
    public function createUniqueEmployeeId(): string
    {
        $year = date('Y');
        $lastTeacher = $this->select('employee_id')
            ->where('employee_id LIKE', 'T' . $year . '%')
            ->orderBy('employee_id', 'DESC')
            ->first();

        if ($lastTeacher) {
            $lastNumber = (int) substr($lastTeacher['employee_id'], 5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'T' . $year . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get active teachers
     */
    public function getActiveTeachers()
    {
        return $this->where('employment_status', 'active')->findAll();
    }

    /**
     * Get teachers by department
     */
    public function getByDepartment($department)
    {
        return $this->where('department', $department)
            ->where('employment_status', 'active')
            ->findAll();
    }

    /**
     * Get full name of teacher
     */
    public function getFullName($teacher): string
    {
        $name = $teacher['first_name'];
        if (!empty($teacher['middle_name'])) {
            $name .= ' ' . $teacher['middle_name'];
        }
        $name .= ' ' . $teacher['last_name'];
        if (!empty($teacher['suffix'])) {
            $name .= ' ' . $teacher['suffix'];
        }
        return $name;
    }

    /**
     * Get teacher with sections they advise
     */
    public function getTeacherWithSections($id)
    {
        return $this->select('teachers.*, sections.section_name, sections.grade_level')
            ->join('sections', 'sections.adviser_id = teachers.id', 'left')
            ->where('teachers.id', $id)
            ->first();
    }

    /**
     * Get teachers available for section advising
     */
    public function getAvailableAdvisers()
    {
        // DEBUG: Show all teachers with their assignment status
        $db = \Config\Database::connect();
        
        $query = "SELECT 
                    t.id, 
                    t.first_name, 
                    t.last_name, 
                    t.email, 
                    t.license_number, 
                    t.employment_status,
                    s.section_name as assigned_section,
                    s.grade_level as assigned_grade
                  FROM teachers t
                  LEFT JOIN sections s ON s.adviser_id = t.id
                  WHERE t.employment_status = 'active' 
                  AND t.deleted_at IS NULL
                  ORDER BY t.first_name ASC, t.last_name ASC";
        
        return $db->query($query)->getResultArray();
    }
}
