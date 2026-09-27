<div class="row">
  <div class="col-md-6">
    <div class="student-info-section">
      <div class="student-info-title">
        <i class="bi bi-person-circle"></i>
        Personal Information
      </div>
      <table class="student-details-table">
        <tr><td>LRN:</td><td><?= esc($student['lrn'] ?? 'N/A') ?></td></tr>
        <tr><td>Full Name:</td><td><?= esc($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'] . ' ' . ($student['suffix'] ?? '')) ?></td></tr>
        <tr><td>Student Type:</td><td><?= esc($student['student_type'] ?? 'N/A') ?></td></tr>
        <?php if (in_array($student['student_type'] ?? '', ['Transferee', 'transferee'], true)): ?>
        <tr><td>Previous School:</td><td><?= esc($student['previous_school'] ?? 'N/A') ?></td></tr>
        <tr><td>School Year Last Attended:</td><td><?= esc($student['previous_school_year'] ?? 'N/A') ?></td></tr>
        <?php endif; ?>
        <tr><td>Gender:</td><td><?= esc($student['gender'] ?? 'N/A') ?></td></tr>
        <tr><td>Date of Birth:</td><td><?= $student['date_of_birth'] ? date('M j, Y', strtotime($student['date_of_birth'])) : 'N/A' ?></td></tr>
        <tr><td>Place of Birth:</td><td><?= esc($student['place_of_birth'] ?? 'N/A') ?></td></tr>
        <tr><td>Nationality:</td><td><?= esc($student['nationality'] ?? 'N/A') ?></td></tr>
        <tr><td>Religion:</td><td><?= esc($student['religion'] ?? 'N/A') ?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="student-info-section">
      <div class="student-info-title">
        <i class="bi bi-telephone"></i>
        Contact Information
      </div>
      <table class="student-details-table">
        <tr><td>Email:</td><td><?= esc($student['email'] ?? 'N/A') ?></td></tr>
        <tr><td>Contact Number:</td><td><?= esc($student['contact_number'] ?? 'N/A') ?></td></tr>
        <tr><td>Address:</td><td><?= esc($student['address'] ?? 'N/A') ?></td></tr>
      </table>
    </div>

    <div class="student-info-section">
      <div class="student-info-title">
        <i class="bi bi-shield-exclamation"></i>
        Emergency Contact
      </div>
      <table class="student-details-table">
        <tr><td>Name:</td><td><?= esc($student['emergency_contact_name'] ?? 'N/A') ?></td></tr>
        <tr><td>Number:</td><td><?= esc($student['emergency_contact_number'] ?? 'N/A') ?></td></tr>
        <tr><td>Relationship:</td><td><?= esc($student['emergency_contact_relationship'] ?? 'N/A') ?></td></tr>
      </table>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="student-info-section">
      <div class="student-info-title">
        <i class="bi bi-mortarboard"></i>
        Application Information
      </div>
      <table class="student-details-table">
        <tr><td>Grade Level:</td><td><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td></tr>
        <tr><td>Section:</td><td><?= esc($student['section_name'] ?? 'Not assigned') ?></td></tr>
        <tr><td>School Year:</td><td><?= esc($student['school_year'] ?? 'N/A') ?></td></tr>
        <tr><td>Status:</td><td>
          <?php 
          $statusColors = [
            'pending' => 'warning',
            'approved' => 'info', 
            'enrolled' => 'success',
            'graduated' => 'primary',
            'transferred' => 'dark',
            'rejected' => 'danger',
            'dropped' => 'secondary'
          ];
          $statusColor = $statusColors[$student['enrollment_status']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $statusColor ?>">
            <?= ucfirst(esc($student['enrollment_status'] ?? 'Unknown')) ?>
          </span>
        </td></tr>
        <tr><td>Submitted Date:</td><td><?= $student['created_at'] ? date('M j, Y', strtotime($student['created_at'])) : 'N/A' ?></td></tr>
      </table>
    </div>
  </div>
</div>

<?php if (!empty($student['section_id'])): ?>
<div class="row">
  <div class="col-12">
    <div class="student-info-section">
      <div class="student-info-title">
        <i class="bi bi-book"></i>
        Enrolled Subjects
      </div>
      <?php 
      $db = \Config\Database::connect();
      $subjects = $db->query(
        "SELECT s.id, s.subject_name, s.subject_code 
         FROM subjects s
         JOIN section_subjects ss ON ss.subject_id = s.id
         WHERE ss.section_id = ? AND s.grade_level = ?
         ORDER BY s.subject_name",
        [$student['section_id'], $student['grade_level']]
      )->getResultArray();
      $excludedSubjects = !empty($student['excluded_subjects']) ? explode(',', $student['excluded_subjects']) : [];
      $subjects = array_filter($subjects, fn($s) => !in_array($s['id'], $excludedSubjects));
      ?>
      <?php if (!empty($subjects)): ?>
        <div class="subjects-grid">
          <?php foreach ($subjects as $subject): ?>
            <div class="subject-card">
              <div class="subject-icon">
                <i class="bi bi-journal-text"></i>
              </div>
              <div class="subject-info">
                <div class="subject-name"><?= esc($subject['subject_name']) ?></div>
                <div class="subject-code"><?= esc($subject['subject_code']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info mb-0">
          <i class="bi bi-info-circle"></i> No subjects assigned to this section yet.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
/* Student Details Table */
.student-details-table {
  width: 100%;
  border-collapse: collapse;
}

.student-details-table td {
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.student-details-table td:first-child {
  font-weight: 700;
  color: #374151;
  width: 35%;
}

.student-details-table td:last-child {
  color: #6b7280;
}

.student-details-table tr:last-child td {
  border-bottom: none;
}

/* Subjects Grid Styles */
.subjects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.subject-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.subject-card:hover {
  background: #eff6ff;
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(59, 130, 246, 0.1);
}

.subject-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #3b82f6;
  color: white;
  border-radius: 8px;
  font-size: 1.2rem;
}

.subject-info {
  flex: 1;
  min-width: 0;
}

.subject-name {
  font-weight: 700;
  color: #1e293b;
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.subject-code {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.subject-info .badge {
  font-size: 0.7rem;
  padding: 0.2rem 0.5rem;
}

/* Documents Grid Styles */
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.document-item {
  border: var(--hairline);
  border-radius: 12px;
  padding: 1rem;
  background: #ffffff;
  transition: all 0.3s ease;
}

.document-item:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.document-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-weight: 700;
  color: #374151;
}

.document-header i {
  font-size: 1.2rem;
  color: #3b82f6;
}

.document-preview {
  position: relative;
}

.image-preview {
  position: relative;
  cursor: pointer;
  border-radius: 8px;
  overflow: hidden;
  background: #f8fafc;
}

.document-thumbnail {
  width: 100%;
  height: 120px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.image-preview:hover .document-thumbnail {
  transform: scale(1.05);
}

.document-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: opacity 0.3s ease;
  text-decoration: none;
}

.image-preview:hover .document-overlay {
  opacity: 1;
}

.document-overlay span {
  color: white;
  text-decoration: none;
}

.document-overlay i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.file-preview {
  text-align: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.file-preview i {
  font-size: 2rem;
  color: #dc2626;
  margin-bottom: 0.5rem;
}

.file-name {
  display: block;
  font-size: 0.9rem;
  color: #6b7280;
  margin-bottom: 0.75rem;
  word-break: break-word;
}

.document-missing {
  text-align: center;
  padding: 1.5rem;
  background: #f9fafb;
  border-radius: 8px;
  color: #6b7280;
}

.document-missing i {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.document-info {
  margin-top: 0.5rem;
  text-align: center;
}

/* Document Viewer Modal */
.document-viewer-modal {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  background: rgba(0, 0, 0, 0.9) !important;
  z-index: 99999 !important;
}

.document-viewer-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.document-viewer-content {
  position: absolute !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  background: white;
  border-radius: 12px;
  max-width: 90vw;
  max-height: 90vh;
  overflow: hidden;
}

.document-viewer-header {
  background: #1e40af;
  color: white;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.document-viewer-header h5 {
  margin: 0;
  color: white;
}

.document-viewer-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.document-viewer-body {
  padding: 1rem;
  text-align: center;
  height: 70vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.document-viewer-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
</style>

<!-- JavaScript functions are now defined in the main page for global access -->
