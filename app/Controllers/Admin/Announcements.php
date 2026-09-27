<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class Announcements extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    /**
     * Display list of announcements with CRUD interface
     */
    public function index()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $db = \Config\Database::connect();

        // ---- read statistics (one aggregate pass over announcement_reads) ----
        $readRows = $db->table('announcement_reads')
            ->select('announcement_id, COUNT(DISTINCT user_id) AS reads_count, MAX(read_at) AS last_read_at')
            ->groupBy('announcement_id')
            ->get()
            ->getResultArray();
        $readMap = [];
        $totalReads = 0;
        foreach ($readRows as $r) {
            $readMap[(int) $r['announcement_id']] = [
                'count' => (int) $r['reads_count'],
                'last'  => $r['last_read_at'],
            ];
            $totalReads += (int) $r['reads_count'];
        }
        $uniqueReaders = (int) ($db->table('announcement_reads')
            ->select('COUNT(DISTINCT user_id) AS c')
            ->get()->getRowArray()['c'] ?? 0);

        // ---- server-side pagination for BOTH lists ----
        $reportLike = 'Class Analytics Report';

        // Regular announcements: ?page=1&per_page=10 (10/25/50)
        $perPageOptions = [10, 25, 50];
        $perPage = in_array((int) ($this->request->getGet('per_page') ?? 0), $perPageOptions, true)
            ? (int) $this->request->getGet('per_page')
            : 10;
        $regTotal = $announcementModel->notLike('title', $reportLike)->countAllResults(false);
        $regPages = max(1, (int) ceil($regTotal / $perPage));
        $regPage = min(max(1, (int) ($this->request->getGet('page') ?? 1)), $regPages);
        $regular = $announcementModel->notLike('title', $reportLike)
            ->orderBy('created_at', 'DESC')
            ->findAll($perPage, ($regPage - 1) * $perPage);

        // Class analytics reports: ?rep_page=1 (5 per page — narrow side column)
        $repPerPage = 5;
        $repTotal = $announcementModel->like('title', $reportLike)->countAllResults(false);
        $repPages = max(1, (int) ceil($repTotal / $repPerPage));
        $repPage = min(max(1, (int) ($this->request->getGet('rep_page') ?? 1)), $repPages);
        $reports = $announcementModel->like('title', $reportLike)
            ->orderBy('created_at', 'DESC')
            ->findAll($repPerPage, ($repPage - 1) * $repPerPage);

        // ---- per-row display data: target label, reach and read stats ----
        $decorate = function (array $rows) use ($readMap, $db) {
            foreach ($rows as &$a) {
                $id = (int) $a['id'];
                $a['reads_count']  = $readMap[$id]['count'] ?? 0;
                $a['last_read_at'] = $readMap[$id]['last'] ?? null;
                $a['reach']        = $this->audienceReach($a['target_roles'], $db);
                $a['target_display'] = $this->targetDisplayName($a['target_roles']);
            }
            return $rows;
        };
        $regular = $decorate($regular);
        $reports = $decorate($reports);

        $stats = [
            'total'          => $announcementModel->countAllResults(false),
            'published'      => $announcementModel->countAllResults(false), // all are published
            'total_reads'    => $totalReads,
            'unique_readers' => $uniqueReaders,
            'regular_total'  => $regTotal,
            'reports_total'  => $repTotal,
        ];

        $pagination = [
            'regular' => ['page' => $regPage, 'pages' => $regPages, 'per_page' => $perPage, 'total' => $regTotal],
            'reports' => ['page' => $repPage, 'pages' => $repPages, 'per_page' => $repPerPage, 'total' => $repTotal],
        ];

        return view('admin/announcements', [
            'title'         => 'Announcements - CSCS Tap n Track',
            'announcements' => $regular,      // page slice (backwards compat)
            'regular'       => $regular,
            'reports'       => $reports,
            'stats'         => $stats,
            'pagination'    => $pagination,
        ]);
    }

    /**
     * Human-readable target audience label (mirrors the old view helper).
     */
    private function targetDisplayName(string $targetRoles): string
    {
        if (strpos($targetRoles, 'section_') === 0) {
            $sectionId = str_replace('section_', '', $targetRoles);
            $section = \Config\Database::connect()->table('sections')
                ->select('section_name, grade_level')
                ->where('id', (int) $sectionId)
                ->get()
                ->getRow();
            return $section
                ? $section->section_name . ' (' . grade_level_label((int) $section->grade_level) . ')'
                : 'Section #' . $sectionId;
        }
        if (strpos($targetRoles, 'grade_') === 0) {
            if (strpos($targetRoles, ',') !== false) {
                $labels = [];
                foreach (explode(',', $targetRoles) as $g) {
                    $labels[] = grade_level_label((int) str_replace('grade_', '', $g));
                }
                return implode(', ', $labels);
            }
            return grade_level_label((int) str_replace('grade_', '', $targetRoles));
        }
        return ucfirst(str_replace('_', ' ', $targetRoles));
    }

    /**
     * Who saw an announcement (AJAX, admin only).
     */
    public function readers($id)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        $db = \Config\Database::connect();
        $announcement = $db->table('announcements')->where('id', (int) $id)->get()->getRowArray();
        if (!$announcement) {
            return $this->response->setJSON(['success' => false, 'error' => 'Announcement not found']);
        }

        $rows = $db->table('announcement_reads ar')
            ->select("ar.user_id, ar.read_at, u.email, u.username,
                      t.first_name AS t_first, t.last_name AS t_last,
                      s.first_name AS s_first, s.last_name AS s_last,
                      agu.group AS user_group")
            ->join('users u', 'u.id = ar.user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id', 'left')
            ->join('teachers t', 't.user_id = u.id', 'left')
            ->join('students s', 's.user_id = u.id', 'left')
            ->where('ar.announcement_id', (int) $id)
            ->groupBy('ar.user_id')
            ->orderBy('ar.read_at', 'DESC')
            ->get()
            ->getResultArray();

        $groupLabels = ['admin' => 'Admin', 'teacher' => 'Teacher', 'student' => 'Student', 'parent' => 'Parent'];
        $readers = [];
        foreach ($rows as $r) {
            $tName = trim((($r['t_first'] ?? '') . ' ' . ($r['t_last'] ?? '')));
            $sName = trim((($r['s_first'] ?? '') . ' ' . ($r['s_last'] ?? '')));
            $name = $tName !== '' ? $tName : ($sName !== '' ? $sName : trim((string) ($r['username'] ?? '')));
            if ($name === '') {
                $name = $r['email'] ?? ('User #' . $r['user_id']);
            }
            $readers[] = [
                'name'    => $name,
                'email'   => $r['email'] ?? '',
                'role'    => $groupLabels[$r['user_group']] ?? ucfirst((string) ($r['user_group'] ?? 'User')),
                'read_at' => $r['read_at'],
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'title'   => $announcement['title'],
            'count'   => count($readers),
            'readers' => $readers,
        ]);
    }

    /**
     * Estimated audience size for an announcement's target_roles value.
     */
    private function audienceReach(string $targetRoles, $db): int
    {
        static $cache = null;
        if ($cache === null) {
            $groupCounts = [];
            foreach ($db->table('auth_groups_users agu')
                ->select('agu.group, COUNT(*) AS c')
                ->join('users u', 'u.id = agu.user_id')
                ->where('u.active', 1)
                ->groupBy('agu.group')
                ->get()->getResultArray() as $g) {
                $groupCounts[$g['group']] = (int) $g['c'];
            }
            $gradeCounts = [];
            foreach ($db->table('students')
                ->select('grade_level, COUNT(*) AS c')
                ->groupBy('grade_level')
                ->get()->getResultArray() as $g) {
                $gradeCounts[(int) $g['grade_level']] = (int) $g['c'];
            }
            $sectionCounts = [];
            foreach ($db->table('students')
                ->select('section_id, COUNT(*) AS c')
                ->groupBy('section_id')
                ->get()->getResultArray() as $g) {
                $sectionCounts[(int) $g['section_id']] = (int) $g['c'];
            }
            $cache = ['groups' => $groupCounts, 'grades' => $gradeCounts, 'sections' => $sectionCounts];
        }

        if ($targetRoles === 'all') {
            return array_sum($cache['groups']);
        }
        if (isset($cache['groups'][$targetRoles])) {
            return $cache['groups'][$targetRoles];
        }
        if (strpos($targetRoles, 'grade_') === 0) {
            $sum = 0;
            foreach (explode(',', $targetRoles) as $g) {
                $grade = (int) str_replace('grade_', '', $g);
                $sum += $cache['grades'][$grade] ?? 0;
            }
            return $sum;
        }
        if (strpos($targetRoles, 'section_') === 0) {
            return $cache['sections'][(int) str_replace('section_', '', $targetRoles)] ?? 0;
        }
        return 0;
    }

    /**
     * Show create form
     */
    public function create()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        return view('admin/announcements_create', [
            'title' => 'Create Announcement - CSCS Tap n Track',
        ]);
    }

    /**
     * Store new announcement
     */
    public function store()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $rules = [
            'title' => 'required|max_length[255]',
            'body' => 'required',
            'target_roles' => 'required|in_list[all,admin,teacher,student,specific_grade,specific_section]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $announcementModel = new AnnouncementModel();

        $targetRoles = $this->request->getPost('target_roles');
        $gradeLevel = $this->request->getPost('grade_level');
        $gradeLevels = $this->request->getPost('grade_levels') ?? [];
        $sectionId = $this->request->getPost('section_id');
        
        // Build target roles string based on selection
        $finalTargetRoles = $targetRoles;
        if ($targetRoles === 'specific_grade' && !empty($gradeLevels)) {
            // Multiple grade levels selected via checkboxes
            $finalTargetRoles = implode(',', array_map(function($g) {
                return 'grade_' . $g;
            }, $gradeLevels));
        } elseif ($targetRoles === 'specific_grade' && $gradeLevel) {
            // Single grade level selected via dropdown (fallback)
            $finalTargetRoles = 'grade_' . $gradeLevel;
        } elseif ($targetRoles === 'specific_section' && $sectionId) {
            $finalTargetRoles = 'section_' . $sectionId;
        }

        $data = [
            'title' => $this->request->getPost('title'),
            'body' => $this->request->getPost('body'),
            'target_roles' => $finalTargetRoles,
            'created_by' => $this->auth->id(),
            'published_at' => date('Y-m-d H:i:s'), // Always publish immediately
        ];

        try {
            // The model builds a slug that is unique against the physical table
            // (soft-deleted rows included) and retries on a race-condition
            // collision, so this can no longer abort with
            // "Duplicate entry '...' for key 'slug'".
            if ($announcementModel->saveWithUniqueSlug($data)) {
                audit_event('announcement.created', [
                    'category'      => 'data',
                    'status'        => 'success',
                    'resource_type' => 'announcement',
                    'resource_id'   => (string) ($announcementModel->getInsertID() ?: ''),
                    'description'   => 'Announcement published',
                    'after'         => [
                        'title'        => (string) $this->request->getPost('title'),
                        'target_roles' => (string) $finalTargetRoles,
                    ],
                ]);

                return redirect()->to(base_url('admin/announcements'))->with('success', 'Announcement published successfully!');
            }

            return redirect()->back()->withInput()->with('errors', $announcementModel->errors());
        } catch (\Throwable $e) {
            log_message('error', 'Failed to publish announcement: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to publish announcement. Please try again.');
        }
    }

    /**
     * Show specific announcement
     */
    public function show($id)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Announcement not found.');
        }

        return view('admin/announcements_show', [
            'title' => 'View Announcement - CSCS Tap n Track',
            'announcement' => $announcement,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Announcement not found.');
        }

        return view('admin/announcements_edit', [
            'title' => 'Edit Announcement - CSCS Tap n Track',
            'announcement' => $announcement,
        ]);
    }

    /**
     * Update announcement content (AJAX)
     */
    public function updateContent($id)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return $this->response->setJSON(['success' => false, 'message' => 'Announcement not found']);
        }

        $body = $this->request->getPost('body');
        
        if (empty($body)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Content cannot be empty']);
        }

        if ($announcementModel->update($id, ['body' => $body])) {
            return $this->response->setJSON(['success' => true, 'message' => 'Content updated successfully']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to update']);
    }

    /**
     * Update announcement
     */
    public function update($id)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Announcement not found.');
        }

        $rules = [
            'title' => 'required|max_length[255]',
            'body' => 'required',
            'target_roles' => 'required|in_list[all,admin,teacher,student,specific_grade,specific_section]',
            'grade_levels' => 'permit_empty|is_array',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $targetRoles = $this->request->getPost('target_roles');
        $gradeLevel = $this->request->getPost('grade_level');
        $gradeLevels = $this->request->getPost('grade_levels') ?? [];
        $sectionId = $this->request->getPost('section_id');

        // Build target roles string based on selection
        $finalTargetRoles = $targetRoles;
        if ($targetRoles === 'specific_grade' && !empty($gradeLevels)) {
            // Multiple grade levels selected via checkboxes
            $finalTargetRoles = implode(',', array_map(function($g) {
                return 'grade_' . $g;
            }, $gradeLevels));
        } elseif ($targetRoles === 'specific_grade' && $gradeLevel) {
            // Single grade level selected via dropdown (fallback)
            $finalTargetRoles = 'grade_' . $gradeLevel;
        } elseif ($targetRoles === 'specific_section' && $sectionId) {
            $finalTargetRoles = 'section_' . $sectionId;
        }

        $data = [
            'title' => $this->request->getPost('title'),
            'body' => $this->request->getPost('body'),
            'target_roles' => $finalTargetRoles,
            'published_at' => $announcement['published_at'] ?: date('Y-m-d H:i:s'), // Ensure it's always published
        ];

        try {
            // Same soft-delete-aware slug generation as store(): the current row is
            // excluded from the uniqueness check so its own slug can be kept, while
            // trashed rows can never block the update.
            if ($announcementModel->saveWithUniqueSlug($data, (int) $id)) {
                audit_event('announcement.updated', [
                    'category'      => 'data',
                    'status'        => 'success',
                    'resource_type' => 'announcement',
                    'resource_id'   => (string) $id,
                    'description'   => 'Announcement updated',
                ] + audit_diff(
                    [
                        'title'        => (string) ($announcement['title'] ?? ''),
                        'target_roles' => (string) ($announcement['target_roles'] ?? ''),
                    ],
                    [
                        'title'        => (string) $data['title'],
                        'target_roles' => (string) $finalTargetRoles,
                    ],
                    ['title', 'target_roles']
                ));

                return redirect()->to(base_url('admin/announcements'))->with('success', 'Announcement updated successfully!');
            }

            return redirect()->back()->withInput()->with('errors', $announcementModel->errors());
        } catch (\Throwable $e) {
            log_message('error', 'Failed to update announcement #' . $id . ': ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update announcement. Please try again.');
        }
    }

    /**
     * Delete announcement
     */
    public function delete($id)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Announcement not found.');
        }

        if ($announcementModel->delete($id)) {
            audit_event('announcement.deleted', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'announcement',
                'resource_id'   => (string) $id,
                'description'   => 'Announcement deleted',
                'before'        => [
                    'title'        => (string) ($announcement['title'] ?? ''),
                    'target_roles' => (string) ($announcement['target_roles'] ?? ''),
                ],
            ]);

            return redirect()->to(base_url('admin/announcements'))->with('success', 'Announcement deleted successfully!');
        } else {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Failed to delete announcement.');
        }
    }



    /**
     * Export PDF for analytics reports
     */
    public function downloadPdf($id)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find($id);

        if (!$announcement) {
            return redirect()->to(base_url('admin/announcements'))->with('error', 'Announcement not found.');
        }

        // Check if this is an analytics report
        if (strpos($announcement['title'], 'Analytics Report') === false) {
            return redirect()->back()->with('error', 'PDF export is only available for analytics reports.');
        }

        $slug = (string) ($announcement['slug'] ?? '');
        if (preg_match('/class-analytics-t(\d+)-/', $slug, $m)) {
            return redirect()->to(base_url('teacher/analytics/export-pdf?teacher_id=' . (int) $m[1]));
        }

        $teacherName = '';
        if (preg_match('/Class Analytics Report - (.+)/', $announcement['title'], $matches)) {
            $teacherName = trim($matches[1]);
        }

        $url = base_url('teacher/analytics/export-pdf');
        if ($teacherName !== '') {
            $url .= '?teacher=' . urlencode($teacherName);
        }

        return redirect()->to($url);
    }

    /**
     * Get announcement statistics (AJAX)
     */
    public function getStats()
    {
        if (!$this->request->isAJAX() || ! is_any_admin()) {
            return $this->response->setStatusCode(403);
        }

        $announcementModel = new AnnouncementModel();
        
        $stats = [
            'total' => $announcementModel->countAllResults(false),
            'published' => $announcementModel->countAllResults(false), // All announcements are published
        ];

        return $this->response->setJSON($stats);
    }

    /**
     * Get sections by grade level (AJAX)
     */
    public function getSections()
    {
        if (!$this->auth->loggedIn() || ! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $gradeLevel = $this->request->getGet('grade_level');
        
        if (!$gradeLevel) {
            return $this->response->setJSON(['success' => false, 'message' => 'Grade level required']);
        }

        try {
            $db = \Config\Database::connect();
            $sections = $db->table('sections')
                ->select('id, section_name, grade_level')
                ->where('grade_level', $gradeLevel)
                ->orderBy('section_name', 'ASC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'success' => true,
                'sections' => $sections,
                'count' => count($sections)
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching sections: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading sections: ' . $e->getMessage()
            ]);
        }
    }

    }

