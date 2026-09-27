<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SnedCategoryModel;
use App\Models\SnedCategoryFieldModel;

class SnedManagement extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function getCategories()
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $categoryModel = new SnedCategoryModel();

        // shared=1 -> master list on Settings: every shared domain
        // (section_id IS NULL), regardless of grade level, because the domains
        // created there are used by every non-numerical section.
        if ($this->request->getGet('shared') === '1') {
            return $this->response->setJSON([
                'success'    => true,
                'categories' => $categoryModel->getCategoriesWithFieldCounts(null, true),
            ]);
        }

        // Optional grade filter (used by per-grade views) shows only shared
        // domains (section_id NULL) for that grade level, including legacy
        // domains that predate grade-level scoping.
        $grade = $this->request->getGet('grade');
        if ($grade !== null && $grade !== '') {
            try {
                SnedCategoryModel::ensureGradeLevelColumn();
                $db = \Config\Database::connect();
                $categories = $db->table('sned_categories')
                    ->select('sned_categories.*, (SELECT COUNT(*) FROM sned_category_fields cf WHERE cf.category_id = sned_categories.id AND cf.is_active = 1) AS field_count')
                    ->where('section_id', null)
                    ->groupStart()
                        ->where('grade_level', (int) $grade)
                        ->orWhere('grade_level', null)
                    ->groupEnd()
                    ->where('is_active', 1)
                    ->orderBy('display_order', 'ASC')
                    ->get()
                    ->getResultArray();
            } catch (\Throwable $e) {
                // Fallback: list all shared domains.
                $categories = array_values(array_filter(
                    $categoryModel->getCategoriesWithFieldCounts(),
                    static fn ($c) => array_key_exists('section_id', $c) && $c['section_id'] === null
                ));
            }
        } else {
            $categories = $categoryModel->getCategoriesWithFieldCounts();
        }

        return $this->response->setJSON([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function addCategory()
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $name = trim((string) $this->request->getPost('name'));
        $description = $this->request->getPost('description');
        // Optional: when provided, the domain is scoped to that section only;
        // when omitted (Settings page), the domain is shared.
        $sectionId = $this->request->getPost('section_id');
        // Optional: scope the shared domain to one grade level (e.g. Grade 6
        // switched to non-numerical on Settings). SNED block uses grade 7.
        $gradeLevel = $this->request->getPost('grade_level');

        if ($name === '') {
            return $this->response->setJSON(['success' => false, 'error' => 'Name is required']);
        }

        try {
            // Make sure the schema supports per-grade-level shared domains.
            SnedCategoryModel::ensureGradeLevelColumn();

            $categoryModel = new SnedCategoryModel();

            $data = [
                'name'          => $name,
                'description'   => $description !== '' && $description !== null ? $description : null,
                'display_order' => $categoryModel->countAll() + 1,
                'is_active'     => 1,
            ];

            if ($sectionId) {
                $data['section_id'] = (int) $sectionId;
            } else {
                // Shared domain: scope it to a grade level when provided.
                // (section_id is intentionally omitted when null so older
                // databases without that column still work.)
                if ($gradeLevel !== null && $gradeLevel !== '') {
                    $data['grade_level'] = (int) $gradeLevel;
                }
            }

            $inserted = $categoryModel->insert($data);

            if ($inserted === false) {
                $errors = $categoryModel->errors();
                $firstError = is_array($errors) && $errors !== [] ? reset($errors) : 'Failed to create the domain. Please try again.';
                return $this->response->setJSON(['success' => false, 'error' => $firstError]);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Category created']);
        } catch (\Throwable $e) {
            log_message('error', 'Add SNED category failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Rename a developmental domain and/or rewrite its description.
     *
     * Used by the Edit action on the domain cards (Settings master list and the
     * per-section domain page). Only the domain row itself changes — its
     * performance indicators, recorded grades and section assignments are left
     * untouched, so renaming is always safe.
     */
    public function editCategory()
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $categoryId  = (int) $this->request->getPost('category_id');
        $name        = trim((string) $this->request->getPost('name'));
        $description = $this->request->getPost('description');

        if ($categoryId <= 0) {
            return $this->response->setJSON(['success' => false, 'error' => 'Invalid domain']);
        }

        if ($name === '') {
            return $this->response->setJSON(['success' => false, 'error' => 'Domain name is required']);
        }

        try {
            $categoryModel = new SnedCategoryModel();
            $category = $categoryModel->find($categoryId);

            if (!$category) {
                return $this->response->setJSON(['success' => false, 'error' => 'Domain not found']);
            }

            // Clearing the description stores NULL so every reader keeps showing
            // the "No description" placeholder instead of an empty paragraph.
            $description = trim((string) $description);

            $updated = $categoryModel->update($categoryId, [
                'name'        => $name,
                'description' => $description === '' ? null : $description,
            ]);

            if ($updated === false) {
                $errors = $categoryModel->errors();
                $firstError = is_array($errors) && $errors !== [] ? reset($errors) : 'Failed to update the domain. Please try again.';

                return $this->response->setJSON(['success' => false, 'error' => $firstError]);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Domain updated']);
        } catch (\Throwable $e) {
            log_message('error', 'Edit SNED category failed: ' . $e->getMessage());

            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Admin page: developmental domains for one non-numerical section.
     *
     * This is where the "Subjects" button on Manage Sections must lead for
     * non-numerical sections — the teacher SNED portal (teacher/sned) rejects
     * admins with a redirect to the dashboard.
     */
    public function sectionCategories($sectionId)
    {
        if (! $this->auth->loggedIn() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        helper('grade_level');

        $db = \Config\Database::connect();

        // The SNED module depends on its own tables; if they are missing
        // (e.g. migrations were not run on this environment), show a clear
        // message instead of a fatal error page.
        foreach (['sned_categories', 'sned_category_fields'] as $snedTable) {
            if (! $db->tableExists($snedTable)) {
                log_message('error', "SNED table '{$snedTable}' is missing. Run database migrations on this environment.");

                return redirect()->to(base_url('admin/sections'))
                    ->with('error', 'The SNED module is not available because its database tables are missing. Run "php spark migrate" on the server, then try again.');
            }
        }
        $section = $db->table('sections')
            ->select('sections.*')
            ->where('sections.id', (int) $sectionId)
            ->get()->getRowArray();

        if (! $section) {
            return redirect()->to(base_url('admin/sections'))->with('error', 'Section not found.');
        }

        if (! in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'], true)) {
            return redirect()->to(base_url('admin/sections'))
                ->with('error', 'Section "' . $section['section_name'] . '" uses subjects, not developmental domains.');
        }

        $categoryModel = new SnedCategoryModel();

        try {
            // Domains scoped to this section…
            $sectionCategories = $categoryModel->getCategoriesWithFieldCounts((int) $sectionId);

            // …and the shared domains (section_id NULL) managed via Settings, which
            // every non-numerical section of the same grade level can use. Legacy
            // domains without a grade level are included as well.
            $allShared = $categoryModel->getCategoriesWithFieldCounts();
        } catch (\Throwable $e) {
            log_message('error', 'SNED section categories (section ' . $sectionId . ') failed: ' . $e->getMessage());
            return redirect()->to(base_url('admin/sections'))
                ->with('error', 'Could not load the developmental domains: ' . $e->getMessage());
        }

        $sectionGrade = (int) ($section['grade_level'] ?? -1);
        $globalCategories = array_values(array_filter(
            $allShared,
            static function ($c) use ($sectionGrade) {
                if (!array_key_exists('section_id', $c) || $c['section_id'] !== null) {
                    return false;
                }
                $catGrade = array_key_exists('grade_level', $c) && $c['grade_level'] !== null ? (int) $c['grade_level'] : null;
                return $catGrade === null || $catGrade === $sectionGrade;
            }
        ));

        // Section adviser, shown for context on the page.
        $adviser = null;
        if (! empty($section['adviser_id'])) {
            $adviser = $db->table('teachers')
                ->select('first_name, middle_name, last_name, email')
                ->where('id', (int) $section['adviser_id'])
                ->get()->getRowArray();
        }

        try {
            return view('admin/sned_section_categories', [
                'section' => $section,
                'sectionCategories' => $sectionCategories,
                'globalCategories' => $globalCategories,
                'adviser' => $adviser,
            ]);
        } catch (\Throwable $e) {
            // The most common cause on live servers: the view file was not
            // uploaded together with the controller. Say so instead of showing
            // the generic "Whoops" error page.
            log_message('error', 'SNED section view (section ' . $sectionId . ') failed: ' . $e->getMessage());
            return redirect()->to(base_url('admin/sections'))
                ->with('error', 'The developmental domains page could not be rendered: ' . $e->getMessage()
                    . ' — make sure app/Views/admin/sned_section_categories.php exists on the server.');
        }
    }

    /**
     * Shared developmental domains offered to one non-numerical section, each
     * flagged with whether the section currently uses it. Backs the checkbox
     * modal opened by the "Subjects" button on Manage Sections.
     */
    public function sectionDomainSelection($sectionId)
    {
        if (! $this->auth->loggedIn() || ! user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        helper('grade_level');

        $db = \Config\Database::connect();

        $section = $db->table('sections')->where('id', (int) $sectionId)->get()->getRowArray();
        if (! $section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section not found.']);
        }

        try {
            $categoryModel = new SnedCategoryModel();

            // Every shared domain (section_id NULL) — the single pool created
            // on the Settings page that all non-numerical sections pick from.
            $shared = $categoryModel->getCategoriesWithFieldCounts(null, true);

            $selected = section_selected_domain_ids((int) $sectionId);

            // A section that was never configured uses every shared domain, so
            // present all of them as ticked rather than as an empty list.
            $selectedLookup = array_flip(
                $selected ?? array_map('intval', array_column($shared, 'id'))
            );

            $domains = [];
            foreach ($shared as $domain) {
                $domains[] = [
                    'id'          => (int) $domain['id'],
                    'name'        => $domain['name'],
                    'description' => $domain['description'] ?? '',
                    'field_count' => (int) ($domain['field_count'] ?? 0),
                    'selected'    => isset($selectedLookup[(int) $domain['id']]),
                ];
            }

            return $this->response->setJSON([
                'success'    => true,
                'configured' => $selected !== null,
                'section'    => [
                    'id'           => (int) $section['id'],
                    'name'         => $section['section_name'],
                    'grade_label'  => grade_level_label((int) ($section['grade_level'] ?? 0)),
                    'grading_type' => $section['grading_type'] ?? 'numerical',
                ],
                'domains'    => $domains,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Load section domain selection failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Persist the shared domains a section uses. Accepts JSON or form input:
     * section_id plus domain_ids (array or comma separated list).
     */
    public function saveSectionDomainSelection()
    {
        if (! $this->auth->loggedIn() || ! user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        helper('grade_level');

        $json    = $this->request->getJSON(true);
        $payload = is_array($json) ? $json : $this->request->getPost();

        $sectionId = (int) ($payload['section_id'] ?? 0);
        if ($sectionId <= 0) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section id is required.']);
        }

        $raw = $payload['domain_ids'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : ($raw === '' ? [] : explode(',', $raw));
        }
        if (! is_array($raw)) {
            return $this->response->setJSON(['success' => false, 'error' => 'domain_ids must be an array.']);
        }

        $domainIds = array_values(array_unique(array_filter(
            array_map('intval', $raw),
            static fn ($id) => $id > 0
        )));

        $db      = \Config\Database::connect();
        $section = $db->table('sections')->where('id', $sectionId)->get()->getRowArray();
        if (! $section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section not found.']);
        }

        if (! in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Section "' . $section['section_name'] . '" is numerical; it uses subjects, not developmental domains.',
            ]);
        }

        // Only accept ids that really are active shared domains, so a stale page
        // cannot store a reference to a deleted domain.
        $validIds = array_map('intval', array_column(
            $db->table('sned_categories')
                ->select('id')
                ->where('section_id', null)
                ->where('is_active', 1)
                ->get()->getResultArray(),
            'id'
        ));
        $domainIds = array_values(array_intersect($domainIds, $validIds));

        try {
            $ok = save_section_selected_domain_ids($sectionId, $domainIds);
        } catch (\Throwable $e) {
            log_message('error', 'Save section domain selection failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }

        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'error' => 'Could not save the selection. Please try again.']);
        }

        $count = count($domainIds);

        return $this->response->setJSON([
            'success'    => true,
            'message'    => $count === 0
                ? 'Saved. Section "' . $section['section_name'] . '" no longer uses any shared developmental domain.'
                : 'Saved. Section "' . $section['section_name'] . '" now uses ' . $count
                    . ' developmental ' . ($count === 1 ? 'domain' : 'domains') . '.',
            'domain_ids' => $domainIds,
        ]);
    }

    public function deleteCategory($categoryId)
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $categoryModel = new SnedCategoryModel();
        $category = $categoryModel->find($categoryId);

        if (!$category) {
            return $this->response->setJSON(['success' => false, 'error' => 'Category not found']);
        }

        // Soft delete: deactivate instead of hard delete
        $categoryModel->update($categoryId, ['is_active' => 0]);

        // Also deactivate all fields
        $fieldModel = new SnedCategoryFieldModel();
        $fieldModel->where('category_id', $categoryId)->set(['is_active' => 0])->update();

        return $this->response->setJSON(['success' => true, 'message' => 'Category deactivated']);
    }

    public function getFields($categoryId)
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $fieldModel = new SnedCategoryFieldModel();
        $fields = $fieldModel->where('category_id', $categoryId)
            ->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'fields' => $fields,
        ]);
    }

    public function addField()
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $categoryId = $this->request->getPost('category_id');
        $fieldName = $this->request->getPost('field_name');

        if (!$categoryId || !$fieldName) {
            return $this->response->setJSON(['success' => false, 'error' => 'Category ID and field name are required']);
        }

        $fieldModel = new SnedCategoryFieldModel();
        $fieldModel->insert([
            'category_id' => $categoryId,
            'field_name' => $fieldName,
            'display_order' => $fieldModel->getNextDisplayOrder($categoryId),
            'is_active' => 1,
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Field added']);
    }

    public function editField()
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $fieldId = $this->request->getPost('field_id');
        $fieldName = trim((string) $this->request->getPost('field_name'));

        if (!$fieldId || $fieldName === '') {
            return $this->response->setJSON(['success' => false, 'error' => 'Field ID and field name are required']);
        }

        if (mb_strlen($fieldName) > 255) {
            return $this->response->setJSON(['success' => false, 'error' => 'Field name must be 255 characters or fewer']);
        }

        $fieldModel = new SnedCategoryFieldModel();
        $field = $fieldModel->find($fieldId);

        if (!$field) {
            return $this->response->setJSON(['success' => false, 'error' => 'Field not found']);
        }

        $fieldModel->update($fieldId, ['field_name' => $fieldName]);

        return $this->response->setJSON(['success' => true, 'message' => 'Field updated']);
    }

    public function deleteField($fieldId)
    {
        if (!$this->auth->loggedIn() || !user_is_any_admin($this->auth->user())) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $fieldModel = new SnedCategoryFieldModel();
        $field = $fieldModel->find($fieldId);

        if (!$field) {
            return $this->response->setJSON(['success' => false, 'error' => 'Field not found']);
        }

        $fieldModel->update($fieldId, ['is_active' => 0]);

        return $this->response->setJSON(['success' => true, 'message' => 'Field deactivated']);
    }
}