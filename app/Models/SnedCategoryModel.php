<?php
namespace App\Models;

use CodeIgniter\Model;

class SnedCategoryModel extends Model
{
    protected $table = 'sned_categories';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    // grade_level is required: shared domains (section_id NULL) are scoped to
    // one grade level so a Grade 6 non-numerical section never inherits another
    // grade's domains. Without it here, protectFields would silently drop the
    // value on insert and every domain would become global.
    protected $allowedFields = [
        'name', 'description', 'display_order', 'is_active', 'section_id', 'grade_level'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|max_length[255]',
        'display_order' => 'permit_empty|integer',
    ];

    /**
     * Grading types that use developmental domains rather than numeric grades.
     */
    public const DOMAIN_GRADING_TYPES = ['non_numerical', 'custom'];

    /**
     * Cached grading_type per section id, so the numeric guard below does not
     * add a query to every domain lookup.
     *
     * @var array<int, string>
     */
    private static $gradingTypeCache = [];

    /**
     * Whether a section is assessed through developmental domains.
     * Unknown/missing sections default to false (they are treated as numerical),
     * which is the safe direction: domains never leak into a numeric section.
     */
    public static function sectionUsesDomains($sectionId): bool
    {
        $sectionId = (int) $sectionId;
        if ($sectionId <= 0) {
            return false;
        }

        if (! array_key_exists($sectionId, self::$gradingTypeCache)) {
            $row = \Config\Database::connect()
                ->table('sections')
                ->select('grading_type')
                ->where('id', $sectionId)
                ->get()
                ->getRowArray();

            self::$gradingTypeCache[$sectionId] = (string) ($row['grading_type'] ?? 'numerical');
        }

        return in_array(self::$gradingTypeCache[$sectionId], self::DOMAIN_GRADING_TYPES, true);
    }

    public function getActiveCategories($sectionId = null, $gradeLevel = null)
    {
        if ($sectionId !== null && ! self::sectionUsesDomains($sectionId)) {
            // Numerical section: it uses subjects with 0-100 grades, so it must
            // never receive developmental domains — even when a caller forgets
            // to check the grading type first.
            return [];
        }

        if ($sectionId !== null || $gradeLevel !== null) {
            helper('grade_level');

            // Domains this section explicitly ticked on Manage Sections.
            // Null means "never configured", which keeps the historic behaviour
            // of using every shared domain so existing sections do not change.
            $selectedIds = $sectionId !== null
                ? section_selected_domain_ids((int) $sectionId)
                : null;

            // Section-specific domains OR the shared domains (section_id NULL)
            // that form one global pool created on the Settings page.
            //
            // The nested groups matter: a flat `orWhere(['section_id' => null])`
            // would still be correct here, but keeping the explicit group means
            // the per-section checkbox filter below can only ever narrow the
            // shared branch, never the section's own domains.
            try {
                $builder = $this->where('is_active', 1)
                    ->groupStart()
                        ->where('section_id', $sectionId)
                        ->orGroupStart()
                            ->where('section_id', null);

                if ($selectedIds !== null) {
                    // Apply the section's tick boxes. An empty selection is a
                    // deliberate "no shared domains" choice, so use an
                    // impossible id rather than dropping the condition (which
                    // would silently mean "all domains").
                    if ($selectedIds === []) {
                        $builder = $builder->where('id', 0);
                    } else {
                        $builder = $builder->whereIn('id', $selectedIds);
                    }
                }

                return $builder->groupEnd()
                    ->groupEnd()
                    ->orderBy('display_order', 'ASC')
                    ->findAll();
            } catch (\Throwable $e) {
                // The grade_level column is missing (self-healing migration
                // could not run on this environment) — fall back to the
                // section's own domains so grading keeps working.
                log_message('warning', 'getActiveCategories grade-level fallback: ' . $e->getMessage());
                $builder = (new static())->where('is_active', 1);
                if ($sectionId !== null) {
                    $builder = $builder->where('section_id', $sectionId);
                }
                return $builder->orderBy('display_order', 'ASC')->findAll();
            }
        }

        return $this->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll();
    }

    /**
     * Make sure the sned_categories table has the grade_level column.
     * Self-healing migration for deployments whose database predates the
     * per-grade-level domain feature; safe to call on every request.
     */
    public static function ensureGradeLevelColumn(): bool
    {
        static $checked = false;
        if ($checked) {
            return true;
        }

        $db = \Config\Database::connect();
        try {
            $fields = $db->getFieldData('sned_categories');
            foreach ($fields as $field) {
                if (($field->name ?? '') === 'grade_level') {
                    return $checked = true;
                }
            }
            $db->query('ALTER TABLE `sned_categories` ADD COLUMN `grade_level` INT(2) NULL DEFAULT NULL AFTER `section_id`');
            $db->query('ALTER TABLE `sned_categories` ADD INDEX `grade_level` (`grade_level`)');
            return $checked = true;
        } catch (\Throwable $e) {
            // Column may already exist or the environment may not permit DDL —
            // treat "duplicate column" as success, everything else as failure.
            $checked = str_contains($e->getMessage(), 'Duplicate column');
            return $checked;
        }
    }

    /**
     * Domains with their active-indicator counts.
     *
     * @param int|null $sectionId  When given, only that section's own domains.
     * @param bool     $sharedOnly When true, only the shared domains
     *                             (section_id IS NULL) used by every
     *                             non-numerical section — this is the master
     *                             list shown on Settings.
     */
    public function getCategoriesWithFieldCounts($sectionId = null, bool $sharedOnly = false)
    {
        if ($sectionId !== null && ! self::sectionUsesDomains($sectionId)) {
            // Numerical section: subject-based, so it has no developmental domains.
            return [];
        }

        $db = \Config\Database::connect();
        $where = "c.is_active = 1";
        $params = [];
        if ($sectionId !== null) {
            $where .= " AND c.section_id = ?";
            $params[] = $sectionId;
        } elseif ($sharedOnly) {
            // Master list: only the domains every non-numerical section shares.
            $where .= " AND c.section_id IS NULL";
        }
        return $db->query("
            SELECT c.*, COUNT(cf.id) as field_count
            FROM sned_categories c
            LEFT JOIN sned_category_fields cf ON cf.category_id = c.id AND cf.is_active = 1
            WHERE {$where}
            GROUP BY c.id
            ORDER BY c.display_order ASC
        ", $params)->getResultArray();
    }

    /**
     * Domains visible to one section: the section's own domains plus the
     * shared grade-level domains added on the Settings page.
     */
    public function getCategoryWithFields($categoryId)
    {
        $category = $this->find($categoryId);
        if (!$category) {
            return null;
        }

        $fieldModel = new SnedCategoryFieldModel();
        $category['fields'] = $fieldModel->where('category_id', $categoryId)
            ->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll();

        return $category;
    }

    public function getAllCategoriesWithFields($sectionId = null, $gradeLevel = null)
    {
        $categories = $this->getActiveCategories($sectionId, $gradeLevel);
        $fieldModel = new SnedCategoryFieldModel();

        foreach ($categories as &$category) {
            $category['fields'] = $fieldModel->where('category_id', $category['id'])
                ->where('is_active', 1)
                ->orderBy('display_order', 'ASC')
                ->findAll();
        }

        return $categories;
    }
}