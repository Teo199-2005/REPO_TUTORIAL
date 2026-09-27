<?php
namespace App\Controllers;

use App\Models\AnnouncementModel;
use App\Models\StudentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    public function index(): ResponseInterface|string
    {
        // If logged in, send user to their role dashboard instead of landing page
        try {
            $auth = auth();
            if ($auth->loggedIn()) {
                $user = $auth->user();
                helper('admin_access');
                if (is_any_admin()) {
                    if (is_admin_staff()) {
                        $dest = admin_staff_post_login_redirect_url((int) $user->id);

                        return redirect()->to($dest ?? base_url('/'));
                    }

                    return redirect()->to(base_url('admin/dashboard'));
                }
                if ($user->inGroup('teacher')) {
                    return redirect()->to(base_url('teacher/dashboard'));
                }
                if ($user->inGroup('student')) {
                    // A locked portal only exposes My Profile, so a student with
                    // an incomplete profile (BMI + both photos) goes straight there.
                    try {
                        $studentRow = (new StudentModel())->where('user_id', (int) $user->id)->first();
                    } catch (\Throwable $e) {
                        $studentRow = null;
                    }

                    if ($studentRow !== null && ! student_profile_complete($studentRow)) {
                        return redirect()->to(base_url('student/profile'));
                    }

                    return redirect()->to(base_url('student/dashboard'));
                }
                if ($user->inGroup('parent')) {
                    return redirect()->to(base_url('parent/dashboard'));
                }
            }
        } catch (\Throwable $e) {
            // ignore and show public landing page
        }

        $announcements = [];
        $enrollmentData = [];
        
        try {
            $model = new AnnouncementModel();
            $announcements = $model->orderBy('published_at', 'DESC')->findAll(5);
        } catch (\Throwable $e) {
            // Table may not exist yet during first run.
        }
        
        try {
            $enrollmentData = $this->getEnrollmentDataFromDB();
            $selectedYear = $this->request->getGet('year') ?? date('Y');
            $monthlyEnrollmentData = $this->getMonthlyEnrollmentData($selectedYear);
            $availableYears = $this->getAvailableYears();
        } catch (\Throwable $e) {
            log_message('error', 'Home controller error: ' . $e->getMessage());
            $selectedYear = date('Y');
            $monthlyEnrollmentData = array_fill(0, 12, 0);
            $availableYears = [(int)date('Y')];
        }
        
        // Get registration status
        try {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
            if ($registrationSetting === null) {
                $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1); // backward compatibility
            }
            $registrationEnabled = (bool) $registrationSetting;
        } catch (\Throwable $e) {
            $registrationEnabled = true;
        }
        
        helper('landing');

        return view('landing', [
            'title' => 'CSCS Tap n Track',
            'announcements' => $announcements,
            'enrollmentData' => json_encode($enrollmentData),
            'monthlyEnrollmentData' => json_encode($monthlyEnrollmentData),
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
            'registrationEnabled' => $registrationEnabled,
            'heroSlides' => landing_hero_slides_for_view(),
            'stripText' => landing_announcement_strip_text(),
        ]);
    }
    
    private function getEnrollmentDataFromDB(): array
    {
        $db = \Config\Database::connect();
        
        // Get enrollment data by school year instead of calendar year
        $enrollmentData = [];
        $currentYear = get_current_school_year();
        $years = explode('-', $currentYear);
        $schoolYears = [];
        for ($i = 4; $i >= 0; $i--) {
            $y = $years[0] - $i;
            $schoolYears[] = $y . '-' . ($y + 1);
        }
        
        foreach ($schoolYears as $schoolYear) {
            $yearlyTotal = $db->query("
                SELECT COUNT(*) as count
                FROM students 
                WHERE school_year = ?
                AND enrollment_status = 'enrolled'
                AND deleted_at IS NULL
            ", [$schoolYear])->getRow()->count ?? 0;
            
            // Get monthly distribution for this school year
            $monthlyResults = $db->query("
                SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as count
                FROM students 
                WHERE school_year = ?
                AND enrollment_status = 'enrolled'
                AND deleted_at IS NULL
                GROUP BY MONTH(created_at)
                ORDER BY MONTH(created_at)
            ", [$schoolYear])->getResultArray();
            
            // Initialize monthly array with zeros
            $monthly = array_fill(0, 12, 0);
            
            // Fill in actual counts from database
            foreach ($monthlyResults as $result) {
                if ($result['month'] >= 1 && $result['month'] <= 12) {
                    $monthly[$result['month'] - 1] = (int)$result['count'];
                }
            }
            
            // Use the year part of school year for chart display
            $displayYear = (int)substr($schoolYear, 0, 4) + 1; // 2021-2022 becomes 2022
            
            $enrollmentData[$displayYear] = [
                'monthly' => $monthly, 
                'yearly' => [(int)$yearlyTotal]
            ];
        }
        
        return $enrollmentData;
    }
    
    private function getEnrollmentData(): array
    {
        return $this->getEnrollmentDataFromDB();
    }
    
    public function getEnrollmentApi(): ResponseInterface
    {
        try {
            $enrollmentData = $this->getEnrollmentDataFromDB();
            
            return $this->response->setJSON([
                'success' => true,
                'enrollment' => $enrollmentData
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to fetch enrollment data'
            ])->setStatusCode(500);
        }
    }

    /**
     * Get monthly enrollment data for enrolled students chart (same as admin dashboard)
     */
    private function getMonthlyEnrollmentData($year = null): array
    {
        $db = \Config\Database::connect();
        
        // Use current year if not specified
        if ($year === null) {
            $year = date('Y');
        }
        
        try {
            // Get all enrolled students by month for specified year
            $currentYearStudents = $db->query("
                SELECT MONTH(created_at) as month, COUNT(*) as count 
                FROM students 
                WHERE YEAR(created_at) = ?
                AND enrollment_status = 'enrolled'
                AND deleted_at IS NULL
                GROUP BY MONTH(created_at)
            ", [$year])->getResultArray();
            
            // Initialize monthly data array with zeros
            $monthlyData = array_fill(0, 12, 0);
            
            // Fill in actual enrollment counts from database
            foreach ($currentYearStudents as $student) {
                if ($student['month'] >= 1 && $student['month'] <= 12) {
                    $monthlyData[$student['month'] - 1] = (int)$student['count'];
                }
            }
            
            return $monthlyData;
        } catch (\Throwable $e) {
            log_message('error', "Error getting enrollment data for year {$year}: " . $e->getMessage());
            return array_fill(0, 12, 0);
        }
    }

    /**
     * Get available years for enrollment data filtering
     */
    private function getAvailableYears(): array
    {
        $db = \Config\Database::connect();
        
        try {
            $years = $db->query("
                SELECT DISTINCT YEAR(created_at) as year 
                FROM students 
                WHERE deleted_at IS NULL 
                AND created_at IS NOT NULL
                ORDER BY year DESC
            ")->getResultArray();
            
            $availableYears = [];
            foreach ($years as $yearData) {
                if ($yearData['year']) {
                    $availableYears[] = (int)$yearData['year'];
                }
            }
            
            // Ensure current year is included
            $currentYear = (int)date('Y');
            if (!in_array($currentYear, $availableYears)) {
                $availableYears[] = $currentYear;
                sort($availableYears);
                $availableYears = array_reverse($availableYears);
            }
            
            return $availableYears;
        } catch (\Throwable $e) {
            // Fallback to current year
            return [(int)date('Y')];
        }
    }

    public function about(): ResponseInterface|string
    {
        return view('about', [
            'title' => 'About — Cauayan South Central School',
        ]);
    }
}
