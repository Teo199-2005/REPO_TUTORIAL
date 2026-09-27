<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Files\UploadedFile;

class Settings extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();

        $yearSetting = $db->table('system_settings')
            ->where('setting_key', 'current_school_year')
            ->get()
            ->getRowArray();

        $termSetting = $db->table('system_settings')
            ->where('setting_key', 'current_term')
            ->get()
            ->getRowArray();

        $teacherPosterSetting = $db->table('system_settings')
            ->where('setting_key', 'featured_poster_teacher')
            ->get()
            ->getRowArray();

        $studentPosterSetting = $db->table('system_settings')
            ->where('setting_key', 'featured_poster_student')
            ->get()
            ->getRowArray();

        helper('asset');

        // The ID card branding helper owns the defaults and reads the stored
        // values, so the form and the printed card can never disagree.
        $branding      = id_card_branding();
        $principalName = $branding['principal_name'];
        $principalRank = $branding['principal_rank'];

        $principal = school_principal();

        $currentSchoolYear = $yearSetting['setting_value'] ?? get_current_school_year();
        $currentTerm       = $termSetting['setting_value'] ?? 1;

        return view('admin/settings', array_merge($this->welcomeModalViewData(), [
            'title'                 => 'System Settings - CSCS Tap n Track',
            'currentSchoolYear'     => $currentSchoolYear,
            'currentTerm'           => $currentTerm,
            'gradeGradingTypes'     => grade_grading_types(),
            'featuredPosterTeacher' => $teacherPosterSetting['setting_value'] ?? '',
            'featuredPosterStudent' => $studentPosterSetting['setting_value'] ?? '',
            'featuredPosterTeacherUrl' => featured_poster_url($teacherPosterSetting['setting_value'] ?? ''),
            'featuredPosterStudentUrl' => featured_poster_url($studentPosterSetting['setting_value'] ?? ''),
            'principalName'             => $principalName,
            'principalRank'             => $principalRank,
            'principalPhotoUrl'         => $principal['photo_url'],
            'principalPhotoPath'        => $principal['photo_path'],
            'hasCustomPrincipalPhoto'   => ($principal['photo_path'] !== 'principal2.png'),
        ]));
    }

    public function updateSchoolYear()
    {
        $schoolYear = $this->request->getPost('school_year');
        $term       = $this->request->getPost('term');

        if (! $schoolYear) {
            return redirect()->back()->with('error', 'School year is required');
        }

        // The settings form only offers two choices (current + previous school
        // year), so reject anything else: free text must never reach
        // system_settings or the sections backfill below.
        if (! in_array((string) $schoolYear, school_year_choices(), true)) {
            return redirect()->back()->with('error', 'Choose a valid school year: only the current or previous school year is allowed.');
        }

        $db = \Config\Database::connect();

        $exists = $db->table('system_settings')
            ->where('setting_key', 'current_school_year')
            ->get()
            ->getRowArray();

        if ($exists) {
            $db->table('system_settings')
                ->where('setting_key', 'current_school_year')
                ->update(['setting_value' => $schoolYear]);
        } else {
            $db->table('system_settings')->insert([
                'setting_key'   => 'current_school_year',
                'setting_value' => $schoolYear,
            ]);
        }

        if ($term) {
            $termValue = (int) $term;
            if ($termValue < 1 || $termValue > 3) {
                return redirect()->back()->with('error', 'Term must be between 1 and 3');
            }

            $termExists = $db->table('system_settings')
                ->where('setting_key', 'current_term')
                ->get()
                ->getRowArray();

            if ($termExists) {
                $db->table('system_settings')
                    ->where('setting_key', 'current_term')
                    ->update(['setting_value' => (string) $termValue]);
            } else {
                $db->table('system_settings')->insert([
                    'setting_key'   => 'current_term',
                    'setting_value' => (string) $termValue,
                ]);
            }

            // Keep any session copy in step with the database. Every reader
            // resolves the term from system_settings (get_current_term()), so
            // this is only a consistency courtesy for legacy readers.
            session()->set('current_term', $termValue);
        }

        $db->table('sections')
            ->where('deleted_at IS NULL')
            ->update(['school_year' => $schoolYear]);

        audit_event('settings.school_year_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'setting',
            'resource_id'   => 'current_school_year',
            'description'   => 'School year / term settings updated',
            'after'         => [
                'current_school_year' => $schoolYear,
                'current_term'        => $term !== null ? (int) $term : null,
            ],
        ]);

        return redirect()->back()->with('success', 'Settings updated successfully');
    }

    /**
     * The school principal is printed on student ID cards, report cards and
     * progress reports, shown on the public About page and the site footer, and
     * signed off in the automated emails. school_principal() falls back to the
     * hardcoded defaults when these rows do not exist, so saving here is all
     * that is needed to change what the whole system shows.
     */
    public function updatePrincipal()
    {
        $name = trim((string) $this->request->getPost('school_principal_name'));
        $rank = trim((string) $this->request->getPost('school_principal_rank'));

        if ($name === '') {
            return redirect()->back()->with('error', 'School principal name is required.');
        }

        if (mb_strlen($name) > 120 || mb_strlen($rank) > 60) {
            return redirect()->back()->with('error', 'Principal name or rank is too long.');
        }

        $model      = new \App\Models\SystemSettingModel();
        $oldName    = trim((string) $model->getSetting('school_principal_name', ''));
        $oldRank    = trim((string) $model->getSetting('school_principal_rank', ''));
        $oldPhoto   = trim((string) $model->getSetting('school_principal_photo', ''));
        $photoPath  = $oldPhoto;
        $photoSaved = false;

        // Optional photo replacement. Leaving the file input empty keeps the
        // current picture, so saving the name never wipes the photo.
        $file = $this->request->getFile('school_principal_photo');
        if ($file !== null && $file->getName() !== '') {
            $validation = $this->validatePosterUpload($file, 'Principal photo');
            if ($validation !== true) {
                return redirect()->back()->with('error', $validation);
            }

            try {
                $newName = $file->getRandomName();
                $file->move($this->principalPhotoUploadDir(), $newName);
            } catch (\Throwable $e) {
                log_message('error', 'Principal photo move failed: ' . $e->getMessage());

                return redirect()->back()->with('error', 'Could not save the principal photo. Check folder permissions on public/uploads/principal/.');
            }

            $photoPath  = 'uploads/principal/' . $newName;
            $photoSaved = true;
        } elseif ($this->request->getPost('remove_principal_photo') === '1') {
            // Back to the bundled picture.
            $photoPath = '';
            $this->deletePrincipalPhoto($oldPhoto);
        }

        $model->setSetting(
            'school_principal_name',
            $name,
            'Current school principal — ID cards, report cards, About page, footer and emails'
        );
        $model->setSetting(
            'school_principal_rank',
            $rank !== '' ? $rank : 'Principal IV',
            'Rank shown under the principal name everywhere the principal is printed'
        );
        $model->setSetting(
            'school_principal_photo',
            $photoPath,
            'Principal photo shown on the public About page'
        );

        if ($photoSaved && $oldPhoto !== '' && $oldPhoto !== $photoPath) {
            $this->deletePrincipalPhoto($oldPhoto);
        }

        audit_event('settings.principal_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'setting',
            'resource_id'   => 'school_principal',
            'description'   => 'School principal details updated',
        ] + audit_diff(
            ['name' => $oldName, 'rank' => $oldRank, 'photo' => $oldPhoto],
            ['name' => $name, 'rank' => $rank, 'photo' => $photoPath],
            ['name', 'rank', 'photo']
        ));

        return redirect()->back()->with('success', 'School principal updated everywhere.');
    }

    /**
     * Remove a previously uploaded principal photo. Only files inside
     * uploads/principal/ are ever deleted, so a malformed setting value can
     * never unlink a bundled asset such as principal2.png.
     */
    private function deletePrincipalPhoto(string $storedPath): void
    {
        $storedPath = ltrim(str_replace('\\', '/', $storedPath), '/');

        if ($storedPath === '' || ! str_starts_with($storedPath, 'uploads/principal/')) {
            return;
        }

        $file = asset_file_path($storedPath);

        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    private function principalPhotoUploadDir(): string
    {
        // Same docroot juggling as featuredPosterUploadDir(): CI4 keeps uploads
        // in public/, the flat Hostinger layout has index.php one level up.
        $candidates = [
            FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'principal' . DIRECTORY_SEPARATOR,
            FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'principal' . DIRECTORY_SEPARATOR,
        ];

        $dir = null;
        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                $dir = $cand;
                break;
            }
        }

        $dir ??= $candidates[0];

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload directory: ' . $dir);
        }

        return $dir;
    }

    public function updateFeaturedPosters()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $db        = \Config\Database::connect();
        $uploadDir = $this->featuredPosterUploadDir();

        $fields = [
            'featured_poster_teacher' => 'featured_poster_teacher',
            'featured_poster_student' => 'featured_poster_student',
        ];

        $updates = [];

        foreach ($fields as $settingKey => $fileField) {
            $file = $this->request->getFile($fileField);
            if ($file === null || $file->getName() === '') {
                continue;
            }

            $validation = $this->validatePosterUpload($file);
            if ($validation !== true) {
                return redirect()->back()->with('error', $validation);
            }

            $newName = $file->getRandomName();
            try {
                $file->move($uploadDir, $newName);
            } catch (\Throwable $e) {
                log_message('error', 'Featured poster move failed: ' . $e->getMessage());

                return redirect()->back()->with('error', 'Could not save the poster file. Check folder permissions on public/uploads/featured/.');
            }

            $updates[$settingKey] = 'uploads/featured/' . $newName;
        }

        if ($updates === []) {
            return redirect()->back()->with('error', 'No poster image selected. Choose a JPG, PNG, or WEBP file first.');
        }

        foreach ($updates as $key => $value) {
            $exists = $db->table('system_settings')->where('setting_key', $key)->get()->getRowArray();
            if ($exists) {
                $db->table('system_settings')->where('setting_key', $key)->update(['setting_value' => $value]);
            } else {
                $db->table('system_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
            }
        }

        audit_event('settings.posters_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'setting',
            'resource_id'   => 'featured_posters',
            'description'   => 'Featured posters updated (' . count($updates) . ')',
            'metadata'      => ['settings' => array_keys($updates)],
        ]);

        return redirect()->back()->with('success', 'Featured posters updated successfully.');
    }

    /**
     * Editable content for the first-login welcome modal.
     *
     * Mirrors updateFeaturedPosters() on purpose: same admin check, same upload
     * validation, same poster folder, same audit shape. The only difference is
     * that plain text fields are accepted alongside the file, so an admin can
     * write the greeting and the dialogue line as well as drop in a poster.
     */
    /**
     * Editable content for the first-login welcome modal.
     *
     * Every field is optional: an empty box falls back to the built-in default in
     * mascot_welcome_copy(), so an admin can override just the dialogue line and
     * leave the rest alone.
     */
    private function welcomeModalViewData(): array
    {
        $roles    = ['admin', 'teacher', 'student', 'parent'];
        $keys     = ['welcome_enabled'];
        foreach ($roles as $role) {
            $keys[] = 'welcome_title_' . $role;
            $keys[] = 'welcome_body_' . $role;
            $keys[] = 'welcome_dialogue_' . $role;
            $keys[] = 'welcome_poster_' . $role;
        }

        $stored = mascot_welcome_settings($keys);

        $data = [
            'welcomeEnabled' => trim($stored['welcome_enabled'] ?? '') !== '0',
        ];

        foreach ($roles as $role) {
            $data['welcomeTitle_' . $role]    = $stored['welcome_title_' . $role] ?? '';
            $data['welcomeBody_' . $role]     = $stored['welcome_body_' . $role] ?? '';
            $data['welcomeDialogue_' . $role] = $stored['welcome_dialogue_' . $role] ?? '';

            $path = trim($stored['welcome_poster_' . $role] ?? '');
            $data['welcomePoster_' . $role] = $path !== '' && function_exists('featured_poster_url')
                ? featured_poster_url($path)
                : '';
        }

        return $data;
    }

    public function updateWelcomeModal()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $db        = \Config\Database::connect();
        $uploadDir = $this->featuredPosterUploadDir();
        $roles     = ['admin', 'teacher', 'student', 'parent'];

        $updates = [];

        // "1" is the default: a modal nobody asked for is worse than one that
        // greets them, and the checkbox is opt-out rather than opt-in.
        $enabled = $this->request->getPost('welcome_enabled');
        $updates['welcome_enabled'] = ($enabled === '0') ? '0' : '1';

        foreach ($roles as $role) {
            foreach (['title', 'body', 'dialogue'] as $field) {
                $key = 'welcome_' . $field . '_' . $role;
                $value = trim((string) $this->request->getPost($key));

                // An empty box means "use the built-in default", so a blank field
                // must not overwrite a saved value with nothing.
                if ($value !== '') {
                    $updates[$key] = mb_substr($value, 0, 500);
                }
            }
        }

        foreach ($roles as $role) {
            $fileField = 'welcome_poster_' . $role;
            $file = $this->request->getFile($fileField);

            if ($file === null || $file->getName() === '') {
                continue;
            }

            $validation = $this->validatePosterUpload($file);
            if ($validation !== true) {
                return redirect()->back()->with('error', $validation);
            }

            $newName = $file->getRandomName();
            try {
                $file->move($uploadDir, $newName);
            } catch (\Throwable $e) {
                log_message('error', 'Welcome poster move failed: ' . $e->getMessage());

                return redirect()->back()->with('error', 'Could not save the poster file. Check folder permissions on public/uploads/featured/.');
            }

            $updates['welcome_poster_' . $role] = 'uploads/featured/' . $newName;
        }

        if ($updates === []) {
            return redirect()->back()->with('error', 'Nothing to save.');
        }

        foreach ($updates as $key => $value) {
            $exists = $db->table('system_settings')->where('setting_key', $key)->get()->getRowArray();
            if ($exists) {
                $db->table('system_settings')->where('setting_key', $key)->update(['setting_value' => $value]);
            } else {
                $db->table('system_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
            }
        }

        audit_event('settings.welcome_modal_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'setting',
            'resource_id'   => 'welcome_modal',
            'description'   => 'Welcome modal content updated (' . count($updates) . ' setting(s))',
            'metadata'      => ['settings' => array_keys($updates)],
        ]);

        return redirect()->back()->with('success', 'Welcome modal updated successfully.');
    }

    public function getGradeSubjects($gradeLevel)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $db = \Config\Database::connect();
        $subjects = $db->table('subjects')
            ->where('grade_level', $gradeLevel)
            ->orderBy('subject_name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success'  => true,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Developmental domains (shared, section_id NULL) for one grade level —
     * used when a grade level is switched to non-numerical on this page.
     */
    public function getGradeDomains($gradeLevel)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        $gradeLevel = (int) $gradeLevel;

        try {
            $hasGradeColumn = \App\Models\SnedCategoryModel::ensureGradeLevelColumn();

            $db = \Config\Database::connect();
            $builder = $db->table('sned_categories')
                ->select('sned_categories.*, (SELECT COUNT(*) FROM sned_category_fields cf WHERE cf.category_id = sned_categories.id AND cf.is_active = 1) AS field_count')
                ->where('section_id', null)
                ->where('is_active', 1);

            if ($hasGradeColumn) {
                // Domains created for this grade level, plus the legacy shared
                // domains (grade_level NULL) that predate per-grade scoping and
                // therefore still apply to every non-numerical section. Showing
                // them here keeps this list identical to what the teacher sees.
                $builder->groupStart()
                    ->where('grade_level', $gradeLevel)
                    ->orWhere('grade_level', null)
                    ->groupEnd();
            }

            $domains = $builder->orderBy('display_order', 'ASC')->get()->getResultArray();

            // Flag legacy (all-grade) domains so the UI can label them.
            foreach ($domains as &$domain) {
                $domain['is_legacy'] = $hasGradeColumn
                    && (! array_key_exists('grade_level', $domain) || $domain['grade_level'] === null);
            }
            unset($domain);

            return $this->response->setJSON([
                'success' => true,
                'domains' => $domains,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Load grade domains failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Switch a grade level between numerical (subjects, 0-100 grades) and
     * non-numerical (developmental domains). Persists the per-grade map in
     * system_settings and bulk-updates the grading_type of the grade's
     * existing sections so teachers/report cards follow immediately.
     */
    public function setGradeGradingType()
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        $gradeLevel  = (int) $this->request->getPost('grade_level');
        $gradingType = $this->request->getPost('grading_type') === 'non_numerical' ? 'non_numerical' : 'numerical';

        // Only Kindergarten through Grade 6 are switchable; grade 7 is always
        // SNED (non-numerical) and 99 is always custom.
        if ($gradeLevel < 0 || $gradeLevel > 6) {
            return $this->response->setJSON(['success' => false, 'error' => 'Only Kindergarten through Grade 6 can be switched.']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $types = grade_grading_types();
            $types[$gradeLevel] = $gradingType;
            save_grade_grading_types($types);

            // Bulk-update existing sections of this grade (leave 'custom' alone).
            $affectedSections = $db->table('sections')
                ->select('id, grading_type')
                ->where('grade_level', $gradeLevel)
                ->whereIn('grading_type', ['numerical', 'non_numerical'])
                ->get()
                ->getResultArray();

            $db->table('sections')
                ->where('grade_level', $gradeLevel)
                ->whereIn('grading_type', ['numerical', 'non_numerical'])
                ->update(['grading_type' => $gradingType]);

            // Switching a whole grade to non-numerical must also repair every
            // section of that grade: leftover subject links and stale numeric
            // grades (symbols typed into numeric fields, stored as 0.00) would
            // otherwise keep showing up on the grades pages and report cards.
            // This mirrors Admin\Dashboard::updateSection().
            if ($gradingType === 'non_numerical') {
                foreach ($affectedSections as $affected) {
                    $sectionId = (int) $affected['id'];

                    // 1. Remove the section's subject links.
                    $db->table('section_subjects')->where('section_id', $sectionId)->delete();

                    // 2. Remove stale numeric grades of the section's students.
                    $db->query(
                        "DELETE g FROM grades g
                         JOIN students st ON st.id = g.student_id
                         WHERE st.section_id = ?",
                        [$sectionId]
                    );

                    // 3. Seed the default symbols once, on the switch.
                    if (($affected['grading_type'] ?? 'numerical') !== 'non_numerical') {
                        $hasSymbols = $db->table('section_grading_symbols')
                            ->where('section_id', $sectionId)
                            ->countAllResults() > 0;

                        if (! $hasSymbols) {
                            $defaultSymbols = [
                                ['symbol' => 'P',     'label' => 'Proficient',                   'description' => 'Meets expectations',                          'display_order' => 1],
                                ['symbol' => 'AP',    'label' => 'Approaching Proficiency',      'description' => 'Nearly meets expectations',                   'display_order' => 2],
                                ['symbol' => 'D',     'label' => 'Developing',                   'description' => 'Still developing skills',                     'display_order' => 3],
                                ['symbol' => 'B',     'label' => 'Beginning',                    'description' => 'Beginning to learn',                          'display_order' => 4],
                                ['symbol' => 'NO/NA', 'label' => 'Not Observed / Not Applicable', 'description' => 'Skill not yet observed or not applicable',    'display_order' => 5],
                            ];
                            foreach ($defaultSymbols as $sym) {
                                $db->table('section_grading_symbols')->insert([
                                    'section_id'    => $sectionId,
                                    'symbol'        => $sym['symbol'],
                                    'label'         => $sym['label'],
                                    'description'   => $sym['description'],
                                    'display_order' => $sym['display_order'],
                                    'is_active'     => 1,
                                ]);
                            }
                        }
                    }
                }
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Set grade grading type failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to save the grading type. Please try again.']);
        }

        $label = $gradingType === 'non_numerical'
            ? 'now uses developmental domains (non-numerical)'
            : 'now uses numerical subjects';

        audit_event('settings.grading_type_changed', [
            'category'      => 'settings',
            'status'        => 'success',
            'resource_type' => 'section',
            'resource_id'   => (string) $gradeLevel,
            'description'   => 'Grading type changed for ' . grade_level_label($gradeLevel) . ' to ' . $gradingType,
            'after'         => ['grading_type' => $gradingType],
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => grade_level_label($gradeLevel) . ' ' . $label . '. Sections of this grade were updated.',
        ]);
    }

    private function featuredPosterUploadDir(): string
    {
        // The web docroot differs between deployments:
        //  - Standard CI4 docroot (public/): FCPATH itself is public/ -> uploads live at FCPATH.'uploads/featured/'
        //  - Flat Hostinger layout (index.php next to public/): uploads live at FCPATH.'public/uploads/featured/'
        // Prefer an existing directory so we never write somewhere the web cannot serve.
        $candidates = [
            FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'featured' . DIRECTORY_SEPARATOR,
            FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'featured' . DIRECTORY_SEPARATOR,
        ];

        $dir = null;
        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                $dir = $cand;
                break;
            }
        }

        $dir ??= $candidates[0];

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload directory: ' . $dir);
        }

        return $dir;
    }

    /**
     * Shared image validation for the upload boxes on this page (featured
     * posters and the principal photo).
     *
     * @return true|string Error message
     */
    private function validatePosterUpload(UploadedFile $file, string $label = 'Poster')
    {
        if (! $file->isValid()) {
            $error = $file->getErrorString();

            return $error !== '' ? $error : 'Upload failed. The file may be too large (max 50MB).';
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $label . ' must be 5MB or less.';
        }

        $ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: '');
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array($ext, $allowedExt, true)) {
            return $label . ' must be JPG, PNG, or WEBP.';
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/pjpeg', 'image/x-png'];
        if ($mime !== '' && ! in_array($mime, $allowedMime, true) && ! str_starts_with($mime, 'image/')) {
            return $label . ' must be an image (JPG, PNG, or WEBP).';
        }

        return true;
    }
}
