<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('home', 'Home::index');
$routes->get('about', 'Home::about');
$routes->get('school-materials/preview/(:num)', 'SchoolMaterials::preview/$1');
$routes->get('school-materials/download/(:num)', 'SchoolMaterials::download/$1');

// Authentication Routes
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
// Fresh CSRF token for the login form (e.g. after a lockout countdown or a
// back/forward-cache restore). GET only, so it never needs a token itself.
$routes->get('login/csrf-token', 'Auth::csrfToken');
// AJAX "Refresh CAPTCHA" on the login form. Returns a new arithmetic question
// (never its answer) and rotates this session's stored challenge.
$routes->get('login/captcha', 'Auth::captchaRefresh');
$routes->get('register', 'Auth::register');
$routes->post('register', 'Auth::store');
// Public teacher self-registration link (accounts are reviewed by an administrator)
$routes->get('teacher/register', 'Auth::teacherRegister');
$routes->post('teacher/register', 'Auth::teacherStore');
$routes->get('teachers/register', 'Auth::teacherRegister');
$routes->post('teachers/register', 'Auth::teacherStore');
$routes->get('logout', 'Auth::logout');
$routes->get('auth/forgot', 'Auth::forgot');
// Demo login routes - only available in non-production environments
if (ENVIRONMENT !== 'production') {
    $routes->get('login/demo/(:segment)', 'Auth::demo/$1');
    $routes->get('login/demo/(:segment)/(:segment)', 'Auth::demo/$1/$2');
}
// Generic dashboard redirector
$routes->get('dashboard', 'Auth::dashboard');

// CHILDPRO / GAD public pages
$routes->get('childpro', 'ChildProGad::childpro');
$routes->get('gad', 'ChildProGad::gad');

// Programs / Projects public pages
$routes->get('programs', 'ProgramsProjects::index');
$routes->get('projects', 'ProgramsProjects::index');

// File serving routes
// CI 4.5+ splits string-route back-references on "/", so `files/(:any)` with a
// subdirectory path (e.g. files/student_photos/x.png) would pass only the first
// directory segment to FileController::show(). Closures receive the full capture
// as a single argument, so wrap the controller call to keep nested paths working.
$routes->get('uploads/(:any)', static function ($path) {
    $controller = new \App\Controllers\FileController();
    $controller->initController(service('request'), service('response'), service('logger'));

    return $controller->show($path);
});
$routes->get('files/(:any)', static function ($path) {
    $controller = new \App\Controllers\FileController();
    $controller->initController(service('request'), service('response'), service('logger'));

    return $controller->show($path);
});

// Password reset routes
$routes->get('forgot-password', 'PasswordReset::index');
$routes->post('forgot-password/verify', 'PasswordReset::verify');

// CodeIgniter Shield routes (for additional auth features)
// Temporarily disabled to avoid conflicts with custom auth
// service('auth')->routes($routes);

// Admin Dashboard Routes
$routes->group('admin', ['filter' => 'adminaccess'], static function ($routes) {
    $routes->get('dashboard/staff-permissions/(:num)', 'Admin\Users::editStaffPermissions/$1');
    $routes->post('dashboard/staff-permissions/(:num)', 'Admin\Users::updateStaffPermissions/$1');
    $routes->post('dashboard/staff-delete/(:num)', 'Admin\Users::deleteStaff/$1');
    $routes->post('dashboard/staff-delete-batch', 'Admin\Users::deleteStaffBatch');
    $routes->get('dashboard', 'Admin\Dashboard::index');
    $routes->get('students', 'Admin\Students::index');
    // PDF export of the master list - the active filters travel in the query string
    $routes->get('students/export-pdf', 'Admin\Students::exportPdf');
    $routes->get('students/create', 'Admin\Students::create');
    $routes->get('students/enroll', 'Admin\Students::enroll');
    $routes->post('students/store', 'Admin\Students::store');
    $routes->get('students/edit/(:num)', 'Admin\Students::edit/$1');
    $routes->post('students/update/(:num)', 'Admin\Students::update/$1');
    $routes->post('students/archive/(:num)', 'Admin\Students::archive/$1');
    $routes->post('students/bulkArchive', 'Admin\Students::bulkArchive');
    $routes->get('students/archived', 'Admin\Students::archived');
    $routes->get('students/view-archived/(:num)', 'Admin\Students::viewArchived/$1');
    $routes->post('students/restore/(:num)', 'Admin\Students::restore/$1');
    $routes->post('students/bulkRestore', 'Admin\Students::bulkRestore');
    $routes->delete('students/bulkDeletePermanently', 'Admin\Students::bulkDeletePermanently');
    $routes->post('students/(:num)/delete-permanently', 'Admin\\Students::deletePermanently/$1');
    $routes->post('students/delete-permanently/(:num)', 'Admin\Students::deletePermanently/$1');
    $routes->get('students/details/(:num)', 'Admin\Students::getStudentDetails/$1');
    $routes->get('students/view/(:num)', 'Admin\Students::viewStudent/$1');
    $routes->get('students/document/(:segment)', 'Admin\Students::viewDocument/$1');
    $routes->post('students/update-password/(:num)', 'Admin\Students::updatePassword/$1');
    $routes->get('students/pending', 'Admin\Students::pending');
    $routes->get('students/pending/history', 'Admin\Students::pendingHistory');
    $routes->get('students/pending-count', 'Admin\Students::getPendingCount');
    $routes->post('students/approve/(:num)', 'Admin\Students::approve/$1');
    $routes->post('students/reject/(:num)', 'Admin\Students::reject/$1');
    $routes->post('students/bulkApprove', 'Admin\Students::bulkApprove');
    $routes->post('students/bulkReject', 'Admin\Students::bulkReject');
    $routes->post('students/promote/(:num)', 'Admin\Students::promote/$1');
    $routes->get('students/next-year-applications', 'Admin\Students::nextYearApplications');
    $routes->post('students/next-year-applications/reject/(:num)', 'Admin\Students::rejectApplication/$1');
    $routes->post('students/next-year-applications/bulk-approve', 'Admin\Students::bulkPromoteApplications');
    $routes->post('students/next-year-applications/bulk-reject', 'Admin\Students::bulkRejectApplications');
    $routes->post('students/unassign-subjects/(:num)', 'Admin\Students::unassignSubjects/$1');

    $routes->get('students/grades/(:num)', 'Admin\Students::viewGrades/$1');
    $routes->get('teachers', 'Admin\Teachers::index');
    // PDF export of the faculty list - the active filters travel in the query string
    $routes->get('teachers/export-pdf', 'Admin\Teachers::exportPdf');
    $routes->get('teachers/create', 'Admin\Teachers::create');
    $routes->post('teachers/store', 'Admin\Teachers::store');
    $routes->get('teachers/pending', 'Admin\Teachers::pending');
    $routes->get('teachers/pending-count', 'Admin\Teachers::getPendingCount');
    $routes->post('teachers/toggle-personnel-edit-global', 'Admin\Teachers::toggleGlobalPersonnelEdit');
    $routes->post('teachers/approve/(:num)', 'Admin\Teachers::approve/$1');
    $routes->post('teachers/reject/(:num)', 'Admin\Teachers::reject/$1');
    $routes->get('teachers/edit/(:num)', 'Admin\Teachers::edit/$1');
    $routes->post('teachers/update/(:num)', 'Admin\Teachers::update/$1');
    $routes->put('teachers/update/(:num)', 'Admin\Teachers::update/$1');
    $routes->patch('teachers/update/(:num)', 'Admin\Teachers::update/$1');
    $routes->delete('teachers/delete/(:num)', 'Admin\Teachers::delete/$1');
    $routes->get('teachers/details/(:num)', 'Admin\Teachers::getTeacherDetails/$1');
    $routes->get('teachers/view/(:num)', 'Admin\Teachers::viewTeacher/$1');
    $routes->get('teachers/edit-form/(:num)', 'Admin\Teachers::editForm/$1');
    $routes->get('teachers/schedule/(:num)', 'Admin\Teachers::schedule/$1');
    $routes->post('teachers/schedule/save/(:num)', 'Admin\Teachers::saveSchedule/$1');
    $routes->post('teachers/schedule/suggestions/(:num)', 'Admin\Teachers::getScheduleSuggestions/$1');
    $routes->get('sections', 'Admin\Dashboard::sections');
    $routes->post('sections/create', 'Admin\Dashboard::createSection');
    $routes->post('sections/delete/(:num)', 'Admin\Dashboard::deleteSection/$1');
    $routes->post('sections/assign-adviser/(:num)', 'Admin\Dashboard::assignAdviser/$1');
    $routes->post('sections/remove-adviser/(:num)', 'Admin\Dashboard::removeAdviser/$1');
    $routes->get('sections/students/(:num)', 'Admin\Dashboard::getSectionStudents/$1');
    $routes->get('sections/subjects/(:num)', 'Admin\Dashboard::getSectionSubjects/$1');
    $routes->post('sections/assign-subjects', 'Admin\Dashboard::assignSubjectsToSection');
    $routes->get('sections/teachers/(:num)', 'Admin\Dashboard::getSectionTeachers/$1');
    $routes->get('sections/all-teachers', 'Admin\Dashboard::getAllTeachers');
    $routes->get('sections/subject-assignments/(:num)', 'Admin\Dashboard::getSubjectAssignments/$1');
    $routes->post('sections/assign-subject-teacher', 'Admin\Dashboard::assignSubjectTeacher');
    $routes->post('sections/assign-subject-teacher-only', 'Admin\Dashboard::assignSubjectTeacherOnly');
    $routes->get('sections/grade-sections/(:num)', 'Admin\Dashboard::getGradeSections/$1');
    $routes->get('sections/capacity-info/(:num)', 'Admin\Dashboard::getSectionCapacityInfo/$1');
    $routes->get('settings/get-grade-subjects/(:num)', 'Admin\Settings::getGradeSubjects/$1');
    $routes->post('sections/remove-subject-teacher/(:num)', 'Admin\Dashboard::removeSubjectTeacher/$1');
    $routes->post('subjects/add', 'Admin\Dashboard::addSubject');
    $routes->post('subjects/edit/(:num)', 'Admin\Dashboard::editSubject/$1');
    $routes->post('subjects/delete/(:num)', 'Admin\Dashboard::deleteSubject/$1');
    $routes->get('settings/get-grade-domains/(:num)', 'Admin\Settings::getGradeDomains/$1');
    $routes->post('settings/set-grade-grading-type', 'Admin\Settings::setGradeGradingType');
    $routes->get('sections/unassigned-students/(:num)', 'Admin\Dashboard::getUnassignedStudents/$1');
    $routes->post('sections/assign-students/(:num)', 'Admin\Dashboard::assignStudentsToSection/$1');
    $routes->post('sections/remove-student/(:num)', 'Admin\Dashboard::removeStudentFromSection/$1');
    $routes->post('sections/remove-students-bulk', 'Admin\Dashboard::removeStudentsBulk');
    $routes->post('sections/update/(:num)', 'Admin\Dashboard::updateSection/$1');
    $routes->get('sections/auto-assign-preview/(:num)', 'Admin\Dashboard::autoAssignPreview/$1');
    $routes->post('sections/auto-assign-execute/(:num)', 'Admin\Dashboard::autoAssignExecute/$1');
    $routes->get('sections/rebalance/(:num)', 'Admin\Dashboard::rebalanceGrade/$1');
    // SNED (non-numerical sections) — developmental domain management.
    // 'section' renders the admin page for one section's domains (linked from
    // the Subjects button on Manage Sections); the rest are the AJAX endpoints
    // used by the SNED block on the Settings page.
    $routes->get('sned/section/(:num)', 'Admin\SnedManagement::sectionCategories/$1');
    // Checkbox picker for the shared developmental domains one section uses.
    $routes->get('sned/section-domains/(:num)', 'Admin\SnedManagement::sectionDomainSelection/$1');
    $routes->post('sned/section-domains/save', 'Admin\SnedManagement::saveSectionDomainSelection');
    $routes->get('sned/categories', 'Admin\SnedManagement::getCategories');
    $routes->post('sned/categories/add', 'Admin\SnedManagement::addCategory');
    // Rename a domain / rewrite its description (Edit action on domain cards).
    $routes->post('sned/categories/edit', 'Admin\SnedManagement::editCategory');
    $routes->post('sned/categories/delete/(:num)', 'Admin\SnedManagement::deleteCategory/$1');
    $routes->get('sned/fields/(:num)', 'Admin\SnedManagement::getFields/$1');
    $routes->post('sned/fields/add', 'Admin\SnedManagement::addField');
    $routes->post('sned/fields/edit', 'Admin\SnedManagement::editField');
    $routes->post('sned/fields/delete/(:num)', 'Admin\SnedManagement::deleteField/$1');

    $routes->get('analytics', 'Admin\Dashboard::analytics');
    $routes->get('analytics/export-pdf', 'Admin\Dashboard::exportPdf');
    $routes->get('student-nutrition', 'Admin\StudentNutrition::index');
    $routes->get('student-nutrition/export-pdf', 'Admin\StudentNutrition::exportPdf');
    $routes->get('fix-enrollment-counts', 'Admin\Dashboard::fixEnrollmentCounts');
    $routes->post('dashboard/updateTerm', 'Admin\Dashboard::updateTerm');
    $routes->post('dashboard/createAdmin', 'Admin\Dashboard::createAdmin');
    $routes->post('dashboard/toggleEnrollment', 'Admin\Dashboard::toggleEnrollment');
    $routes->post('dashboard/toggleGrading', 'Admin\Dashboard::toggleGrading');
    // Additional Admin Features

    // Announcements CRUD
    $routes->get('announcements', 'Admin\Announcements::index');
    $routes->get('announcements/create', 'Admin\Announcements::create');
    $routes->post('announcements/store', 'Admin\Announcements::store');
    $routes->get('announcements/show/(:num)', 'Admin\Announcements::show/$1');
    $routes->get('announcements/edit/(:num)', 'Admin\Announcements::edit/$1');
    $routes->post('announcements/update/(:num)', 'Admin\Announcements::update/$1');
    $routes->post('announcements/updateContent/(:num)', 'Admin\Announcements::updateContent/$1');
    $routes->post('announcements/delete/(:num)', 'Admin\Announcements::delete/$1');
    $routes->get('announcements/download-pdf/(:num)', 'Admin\Announcements::downloadPdf/$1');
    $routes->post('announcements/getStats', 'Admin\Announcements::getStats');
    $routes->get('announcements/readers/(:num)', 'Admin\Announcements::readers/$1');
    $routes->get('announcements/get-sections', 'Admin\Announcements::getSections');



    // Announcements AJAX management
    $routes->get('announcements/list', 'Announcements::listAjax');
    $routes->post('announcements/store', 'Announcements::storeAjax');
    $routes->get('announcements/get/(:num)', 'Announcements::getAjax/$1');
    $routes->post('announcements/update/(:num)', 'Announcements::updateAjax/$1');
    $routes->delete('announcements/delete/(:num)', 'Announcements::deleteAjax/$1');

    // Password Reset Management
    $routes->get('password-resets', 'Admin\PasswordResets::index');
    $routes->post('password-resets/approve/(:num)', 'Admin\PasswordResets::approve/$1');
    $routes->post('password-resets/reject/(:num)', 'Admin\PasswordResets::reject/$1');
    $routes->get('password-resets/change/(:num)', 'Admin\PasswordResets::change/$1');
    $routes->post('password-resets/change-password', 'Admin\PasswordResets::changePassword');
    $routes->get('password-resets/details/(:num)', 'Admin\PasswordResets::getRequestDetails/$1');
    $routes->get('password-resets/count', 'Admin\PasswordResets::getCount');
    $routes->get('password-resets/generate-reset-link/(:num)', 'Admin\PasswordResets::generateResetLink/$1');
    $routes->post('password-resets/approve-all', 'Admin\PasswordResets::approveAll');
    $routes->post('password-resets/reject-all', 'Admin\PasswordResets::rejectAll');
    $routes->post('password-resets/delete/(:num)', 'Admin\PasswordResets::delete/$1');
    
    // Settings
    $routes->get('settings', 'Admin\Settings::index');
    $routes->post('settings/update-school-year', 'Admin\Settings::updateSchoolYear');
    $routes->post('settings/update-principal', 'Admin\Settings::updatePrincipal');
    $routes->post('settings/update-featured-posters', 'Admin\Settings::updateFeaturedPosters');
    $routes->post('settings/update-welcome-modal', 'Admin\Settings::updateWelcomeModal');
    $routes->get('landing-page', 'Admin\LandingPage::index');
    $routes->post('landing-page/update', 'Admin\LandingPage::update');
    $routes->get('childpro-gad', 'Admin\ChildProGad::index');
    $routes->post('childpro-gad/update', 'Admin\ChildProGad::update');
        $routes->get('programs-projects', 'Admin\ProgramsProjects::index');
        $routes->post('programs-projects/update', 'Admin\ProgramsProjects::update');
    $routes->get('materials', 'Admin\Materials::index');
    $routes->post('materials/upload', 'Admin\Materials::upload');
    $routes->post('materials/update/(:num)', 'Admin\Materials::update/$1');
    $routes->post('materials/delete/(:num)', 'Admin\Materials::delete/$1');
    $routes->get('materials/download/(:num)', 'Admin\Materials::download/$1');
    $routes->get('settings/get-grade-subjects/(:num)', 'Admin\Settings::getGradeSubjects/$1');
    
    // Profile
    $routes->get('profile', 'Admin\Profile::index');
    $routes->post('profile/update', 'Admin\Profile::update');
    $routes->post('profile/change-password', 'Admin\Profile::changePassword');
    
    // Platform satisfaction (student + teacher ratings)
    $routes->get('platform-ratings', 'Admin\PlatformRatings::index');

    // Activity / audit log (read-only; see App\Controllers\Admin\AuditLog)
    $routes->get('audit-log', 'Admin\AuditLog::index');
    $routes->get('audit-log/export', 'Admin\AuditLog::export');
    $routes->get('audit-log/show/(:num)', 'Admin\AuditLog::show/$1');
    $routes->post('audit-log/verify', 'Admin\AuditLog::verify');

    // Database backup & restore (see App\Controllers\Admin\Backups).
    // The page is grantable to admin_staff; restore, delete and the automatic
    // schedule are restricted to master admins inside the controller.
    $routes->get('backups', 'Admin\Backups::index');
    $routes->get('backups/status', 'Admin\Backups::status');
    $routes->post('backups/create', 'Admin\Backups::create');
    $routes->post('backups/verify/(:num)', 'Admin\Backups::verify/$1');
    $routes->get('backups/download/(:num)', 'Admin\Backups::download/$1');
    $routes->post('backups/restore', 'Admin\Backups::restore');
    $routes->post('backups/delete/(:num)', 'Admin\Backups::delete/$1');
    $routes->post('backups/schedule', 'Admin\Backups::schedule');

    // Student ID cards (optional administrative utility)
    $routes->get('id-cards', 'Admin\IdCards::index');
    $routes->get('id-cards/view/(:num)', 'Admin\IdCards::viewCard/$1');
    $routes->get('id-cards/print/(:num)', 'Admin\IdCards::printCard/$1');
    $routes->post('id-cards/generate-lrn/(:num)', 'Admin\IdCards::generateLrn/$1');

    // Records Management
    $routes->get('records', 'Admin\RecordsManagement::index');
    $routes->get('records/section/(:num)/(:segment)', 'Admin\RecordsManagement::viewSection/$1/$2');
    $routes->post('records/archive', 'Admin\RecordsManagement::archiveCompleted');
    $routes->get('records/view/(:num)', 'Admin\RecordsManagement::viewRecord/$1');
    // Developmental (non-numerical) report card — admin
    $routes->get('records/sned-report-card/(:num)', 'Admin\RecordsManagement::viewSnedRecord/$1');
    $routes->get('records/sned-report-card-pdf/(:num)', 'Admin\RecordsManagement::viewSnedRecordPdf/$1');
    // Learner Development Report (values / conduct / character, three terms) — admin
    $routes->get('records/learner-development-report-pdf/(:num)', 'Admin\RecordsManagement::learnerDevelopmentReportPdf/$1');
    
    // Schedules
    $routes->get('schedules', 'Admin\Schedules::index');
    $routes->get('schedules/section/(:num)', 'Admin\Schedules::section/$1');
    $routes->get('schedules/section-schedule/(:num)', 'Admin\Schedules::sectionSchedule/$1');
    $routes->get('schedules/get/(:num)/(:alpha)', 'Admin\Schedules::getSchedules/$1/$2');
    $routes->get('schedules/subjects/(:num)', 'Admin\Schedules::subjects/$1');
    $routes->get('schedules/subject-teachers/(:num)', 'Admin\Schedules::subjectTeachers/$1');
    $routes->get('schedules/form/(:num)/(:alpha)', 'Admin\Schedules::form/$1/$2');
    $routes->post('schedules/save', 'Admin\Schedules::save');
    $routes->post('schedules/update-room', 'Admin\Schedules::updateRoom');
    // Moving a time slot rewrites the stored start/end of every block in it, so
    // it has to be validated against the timetable like any other write.
    $routes->post('schedules/update-time', 'Admin\Schedules::updateTime');
    // NOTE: no 'schedules/update-field' route — no such method exists in
    // Admin\Schedules and nothing in the app calls it. The working partial
    // editors are 'update-room' and 'update-both-fields'.
    $routes->post('schedules/update-both-fields', 'Admin\Schedules::updateBothFields');
    $routes->post('schedules/delete/(:num)', 'Admin\Schedules::delete/$1');
    // One "Save Schedules" click posts every new block in one request, so the
    // blocks are validated against each other and not just one at a time.
    $routes->post('schedules/save-batch', 'Admin\Schedules::saveBatch');

});

// Student Dashboard Routes
$routes->group('student', [], static function ($routes) {
    $routes->get('dashboard', 'Student\Dashboard::index');
    $routes->get('profile', 'Student\Dashboard::profile');
    $routes->post('profile', 'Student\Dashboard::updateProfile');
    $routes->get('grades', 'Student\Dashboard::grades');
    $routes->post('apply-next-year', 'Student\Dashboard::applyNextYear');
    $routes->get('schedule', 'Student\Dashboard::schedule');
    $routes->get('schedule-pdf', 'Student\Dashboard::schedulePdf');
    $routes->get('announcements', 'Student\Dashboard::announcements');
    $routes->get('announcements/view/(:num)', 'Student\Dashboard::viewAnnouncement/$1');
    $routes->get('profile', 'Student\Profile::index');
    $routes->post('profile/update', 'Student\Profile::update');
    $routes->post('profile/change-password', 'Student\Profile::changePassword');
    // Additional Student Features
    $routes->get('materials', 'Student\Materials::index');
    $routes->get('materials/download/(:num)', 'Student\Materials::download/$1');
    $routes->get('notifications', 'Student\Notifications::index');
    $routes->get('report-card', 'Student\Dashboard::viewReportCard');
    $routes->get('learner-development-report', 'Student\Dashboard::learnerDevelopmentReport');
    $routes->get('analytics', 'Student\Analytics::index');
    $routes->get('platform-rating', 'Student\PlatformRating::index');
    $routes->post('platform-rating/save', 'Student\PlatformRating::save');
    // Digital ID card - students can only ever see their OWN card
    $routes->get('id-cards', 'Student\IdCards::index');

});

// Teacher Dashboard Routes
$routes->group('teacher', ['filter' => 'teacheraccess'], static function ($routes) {
    $routes->get('dashboard', 'Teacher\Dashboard::index');
    $routes->get('grades', 'Teacher\Dashboard::grades');
    $routes->post('grades', 'Teacher\Dashboard::saveGrades');
    $routes->post('grades/bulk', 'Teacher\Dashboard::saveBulkGrades');
    $routes->get('students', 'Teacher\Dashboard::students');
    $routes->get('schedule', 'Teacher\Dashboard::schedule');
    $routes->get('schedule-pdf', 'Teacher\Dashboard::schedulePdf');
    $routes->get('schedule/manage', 'Teacher\Dashboard::manageSchedule');
    $routes->post('schedule/save', 'Teacher\Dashboard::saveSchedule');

    // Learning materials (teacher upload for assigned classes / public files)
    $routes->get('materials', 'Teacher\Materials::index');
    $routes->post('materials/upload', 'Teacher\Materials::upload');
    $routes->get('materials/download/(:num)', 'Teacher\Materials::download/$1');
    $routes->post('materials/delete/(:num)', 'Teacher\Materials::delete/$1');

    // Additional Teacher Features
    $routes->get('announcements', 'Teacher\Announcements::index');
    $routes->post('announcements', 'Teacher\Announcements::post');
    $routes->get('announcements/view/(:num)', 'Teacher\Announcements::view/$1');

    $routes->get('messages', 'Teacher\Messages::index');
    $routes->get('analytics', 'Teacher\Analytics::index');
    $routes->get('analytics/export-pdf', 'Teacher\Analytics::exportPdf');
    $routes->post('analytics/send-to-admin', 'Teacher\Analytics::sendToAdmin');
    $routes->get('attendance', 'Teacher\Dashboard::attendance');
    $routes->post('attendance', 'Teacher\Dashboard::saveAttendance');
    $routes->get('attendance/history', 'Teacher\Dashboard::attendanceHistoryPage');
    $routes->get('attendance/history/data', 'Teacher\Dashboard::attendanceHistory');
    $routes->get('platform-rating', 'Teacher\PlatformRating::index');
    $routes->post('platform-rating/save', 'Teacher\PlatformRating::save');

    $routes->get('profile', 'Teacher\Profile::index');
    $routes->post('profile/update', 'Teacher\Profile::update');
    $routes->post('profile/update-personnel', 'Teacher\Profile::updatePersonnel');
    $routes->post('profile/change-password', 'Teacher\Profile::changePassword');
    $routes->get('sections', 'Teacher\Dashboard::sections');
    $routes->get('sections/students/(:num)', 'Teacher\Dashboard::getSectionStudents/$1');
    $routes->get('sections/unassigned-students/(:num)', 'Teacher\Dashboard::getUnassignedStudents/$1');
    $routes->post('sections/assign-students/(:num)', 'Teacher\Dashboard::assignStudentsToSection/$1');
    $routes->get('report-card/(:num)', 'Teacher\Dashboard::generateReportCard/$1');
    $routes->post('send-report-card', 'Teacher\Dashboard::sendReportCard');
    $routes->post('send-all-report-cards', 'Teacher\Dashboard::sendAllReportCards');
    $routes->post('remove-student', 'Teacher\Dashboard::removeStudent');
    $routes->post('toggle-report-card-access', 'Teacher\Dashboard::toggleReportCardAccess');
    
    // Notification routes
    $routes->get('notifications', 'Teacher\Notifications::index');
    $routes->post('notifications/mark-as-read', 'Teacher\Notifications::markAsRead');
    $routes->get('notifications/unread-count', 'Teacher\Notifications::getUnreadCount');
    $routes->post('notifications/send-to-students', 'Teacher\Notifications::sendToStudents');
    $routes->get('announcements/unread-count', 'Teacher\Announcements::getUnreadCount');
    $routes->post('announcements/mark-grade-rec-read', 'Teacher\Announcements::markGradeRecAsRead');
    $routes->post('grades/submit-recommendation', 'Teacher\Dashboard::submitGradeRecommendation');
    
    // SNED (Special Needs Education) Routes
    $routes->get('sned', 'Teacher\SnedGrades::index');
    $routes->get('sned/categories/(:num)', 'Teacher\SnedGrades::categories/$1');
    $routes->get('sned/grades/(:num)/(:num)', 'Teacher\SnedGrades::gradeEntry/$1/$2');
    $routes->post('sned/grades/save', 'Teacher\SnedGrades::saveGrades');
    $routes->get('sned/categories/(:num)/fields', 'Teacher\SnedGrades::manageFields/$1');
    $routes->post('sned/fields/add', 'Teacher\SnedGrades::addField');
    $routes->post('sned/fields/edit', 'Teacher\SnedGrades::editField');
    $routes->post('sned/fields/delete/(:num)', 'Teacher\SnedGrades::deleteField/$1');
    $routes->get('sned/report-card/(:num)', 'Teacher\SnedGrades::reportCard/$1');
    $routes->get('sned/report-card-pdf/(:num)', 'Teacher\SnedGrades::reportCardPdf/$1');
    $routes->get('sned/learner-development-report-pdf/(:num)', 'Teacher\SnedGrades::learnerDevelopmentReportPdf/$1');
});

// Parent Dashboard Routes
$routes->group('parent', [], static function ($routes) {
    $routes->get('dashboard', 'Parent\Dashboard::index');
    $routes->get('children', 'Parent\Dashboard::children');
    $routes->get('grades/(:num)', 'Parent\Dashboard::childGrades/$1');
    $routes->get('announcements', 'Parent\Dashboard::announcements');
});

// Public Announcements
// $routes->get('announcements', 'Announcements::index'); // disabled public announcements page

// Admin Announcements Management
$routes->group('announcements', [], static function ($routes) {
    $routes->get('admin', 'Announcements::admin');
    $routes->get('create', 'Announcements::create');
    $routes->post('/', 'Announcements::store');
});


// API Routes
$routes->get('api/enrollment', 'Home::getEnrollmentApi');
$routes->get('api/notification-counts', 'Api\NotificationCounts::index');
$routes->get('api/platform-rating/status', 'Api\PlatformRating::status');
$routes->post('api/platform-rating/submit', 'Api\PlatformRating::submit');
// Whether this account has hidden Tappy. Server side on purpose: a localStorage
// key is scoped to the browser, so it would not survive signing out and back in
// on another machine, and it would leak onto the next account that uses the same
// computer. The controller rejects the request when nobody is signed in.
$routes->post('api/mascot/visibility', 'Api\MascotVisibility::update');


