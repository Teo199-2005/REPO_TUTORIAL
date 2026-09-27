<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
/* Same compact form system as the Edit Teacher modal (teacher_personnel_fields). */
?>
<style>
  .tpf-form .tpf-section-title {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e3a8a;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: .5rem;
    margin: 1.25rem 0 .75rem;
  }
  .tpf-form .tpf-section-title:first-child { margin-top: 0; }
  .tpf-form .tpf-section-title .bi {
    font-size: 1.15rem;
    color: #2563eb;
  }
  .tpf-form .form-label {
    font-size: .95rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: .3rem;
    display: flex;
    align-items: center;
    gap: .35rem;
  }
  .tpf-form .form-label .bi {
    font-size: .9rem;
    color: #6b7280;
  }
  .tpf-form .mb-3 { margin-bottom: .5rem !important; }
  .tpf-form .row {
    row-gap: .5rem;
    --bs-gutter-x: 1rem;
    /* Neutralize dashboard.css ".main-content .row" spacing (1.5rem margins +
       dashed separators) so this form stays compact like the teacher modal. */
    margin-bottom: .5rem;
    padding-bottom: 0;
    border-bottom: 0;
  }
  /* Same specificity as ".main-content .row:not(:last-child)" but declared
     later, so the dashed separators + extra padding are actually overridden. */
  .tpf-form .row:not(:last-child) {
    margin-bottom: .5rem;
    padding-bottom: 0;
    border-bottom: 0;
  }
  .tpf-form .form-control,
  .tpf-form .form-select { border-radius: .4rem; }
  .tpf-form .form-text {
    font-size: .8rem;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: .25rem;
  }
  .tpf-form .tpf-sub {
    display: flex;
    align-items: center;
    gap: .4rem;
    color: #4b5563;
    font-size: .95rem;
    font-weight: 700;
    margin: .35rem 0 .15rem;
  }
  .tpf-form .tpf-sub .bi { color: #2563eb; font-size: .9rem; }

  /* Religion selector + its "Other" companion box (same behaviour as the
     registration form): the box only appears for "Other". */
  .religion-other { display: none; margin-top: .35rem; }
  .religion-other.is-visible { display: block; }
  .religion-other .form-label { margin-bottom: .15rem; }

  /* Previous-school fields, revealed only while the student type is
     "Transferee" (same behaviour as the registration form). */
  .transferee-fields { display: none; }
  .transferee-fields.is-visible { display: block; }
  .transferee-fields .form-label { margin-bottom: .15rem; }

  /* "No middle name" checkbox. */
  .no-middle-name-check {
    display: flex;
    align-items: center;
    gap: .3rem;
    margin-top: .35rem;
    font-size: .75rem;
    color: #6c757d;
    cursor: pointer;
    user-select: none;
  }
  .no-middle-name-check input { width: auto; margin: 0; flex-shrink: 0; cursor: pointer; }
  .no-middle-name-check:hover { color: #0d6efd; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Student</h1>
    <a href="<?= base_url('admin/students') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Students
    </a>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger py-2"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success py-2"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= base_url('admin/students/update/' . $student['id']) ?>">
            <?= csrf_field() ?>
            <div class="tpf-form">

            <h6 class="tpf-section-title"><i class="bi bi-person-vcard"></i> Student Information</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="lrn" class="form-label"><i class="bi bi-hash"></i>LRN</label>
                        <input type="text" class="form-control" name="lrn" id="lrn" 
                               value="<?= esc($student['lrn']) ?>" required maxlength="12" pattern="[0-9]{12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="student_type" class="form-label"><i class="bi bi-person-badge"></i>Student Type</label>
                        <select class="form-select" name="student_type" id="student_type" onchange="toggleTransfereeFields()">
                            <option value="New Student" <?= in_array($student['student_type'] ?? '', ['New Student', 'new'], true) ? 'selected' : '' ?>>New Student</option>
                            <option value="Transferee" <?= in_array($student['student_type'] ?? '', ['Transferee', 'transferee'], true) ? 'selected' : '' ?>>Transferee</option>
                            <option value="Old Student" <?= in_array($student['student_type'] ?? '', ['Old Student', 'old student', 'old'], true) ? 'selected' : '' ?>>Old Student</option>
                            <option value="Returnee" <?= in_array($student['student_type'] ?? '', ['Returnee', 'returnee'], true) ? 'selected' : '' ?>>Returnee</option>
                        </select>

                        <?php
                        // Transferees additionally declare the school they came
                        // from and the school year they last attended it. A
                        // redisplayed form (validation error) reopens the box.
                        $isTransferee   = in_array($student['student_type'] ?? '', ['Transferee', 'transferee'], true);
                        $prevSchool     = old('previous_school', $student['previous_school'] ?? '');
                        $prevSchoolYear = old('previous_school_year', $student['previous_school_year'] ?? '');
                        $prevYears      = previous_school_year_choices();
                        // Keep a year already on record selectable even if it is
                        // older than the generated range.
                        if ($prevSchoolYear !== '' && ! in_array($prevSchoolYear, $prevYears, true)) {
                            $prevYears[] = $prevSchoolYear;
                            sort($prevYears, SORT_NATURAL);
                            $prevYears = array_reverse($prevYears);
                        }
                        ?>

                        <div class="transferee-fields<?= $isTransferee ? ' is-visible' : '' ?>" id="transfereeFields">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="previous_school" class="form-label"><i class="bi bi-building"></i>Previous School (Last school attended)</label>
                                    <input type="text" class="form-control" name="previous_school" id="previous_school"
                                           value="<?= esc($prevSchool) ?>"
                                           data-field-label="Previous School"
                                           maxlength="255" placeholder="e.g. Cauayan Central Elementary School"
                                           <?= $isTransferee ? 'required' : 'disabled' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label for="previous_school_year" class="form-label"><i class="bi bi-calendar3"></i>School Year Last Attended</label>
                                    <select class="form-select" name="previous_school_year" id="previous_school_year"
                                            data-field-label="School Year Last Attended"
                                            <?= $isTransferee ? 'required' : 'disabled' ?>>
                                        <option value="">Select school year</option>
                                        <?php foreach ($prevYears as $prevYear): ?>
                                            <option value="<?= esc($prevYear) ?>" <?= $prevSchoolYear === $prevYear ? 'selected' : '' ?>><?= esc($prevYear) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="email" class="form-label"><i class="bi bi-envelope"></i>Email</label>
                        <input type="email" class="form-control" name="email" id="email" 
                               value="<?= esc($student['email']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="first_name" class="form-label"><i class="bi bi-person"></i>First Name</label>
                        <input type="text" class="form-control" name="first_name" id="first_name" maxlength="50" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                               value="<?= esc($student['first_name']) ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <?php
                        // Students without a middle name tick the box; the field
                        // is then cleared and left unrequired. A supplied middle
                        // name must be at least two characters.
                        $storedMiddleName = trim((string) ($student['middle_name'] ?? ''));
                        $noMiddleName = old(NO_MIDDLE_NAME_POST_KEY) === no_middle_name_rule();
                        $middleNameValue = $noMiddleName ? '' : old('middle_name', $storedMiddleName);
                        // A record with no middle name stored opens with the box
                        // ticked; one that has a middle name opens unticked.
                        if (old(NO_MIDDLE_NAME_POST_KEY) === null && $storedMiddleName === '') {
                            $noMiddleName = true;
                            $middleNameValue = '';
                        }
                        ?>
                        <label for="middle_name" class="form-label"><i class="bi bi-person"></i>Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" id="middle_name" maxlength="100" minlength="2"
                               oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                               value="<?= esc($middleNameValue) ?>"
                               <?= $noMiddleName ? 'disabled' : 'required' ?>>
                        <label class="no-middle-name-check" for="no_middle_name">
                            <input type="checkbox" name="<?= NO_MIDDLE_NAME_POST_KEY ?>" id="no_middle_name"
                                   value="<?= no_middle_name_rule() ?>" onchange="toggleNoMiddleName()"
                                   <?= $noMiddleName ? 'checked' : '' ?>>
                            <span>No middle name</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="last_name" class="form-label"><i class="bi bi-person"></i>Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="last_name" maxlength="50" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                               value="<?= esc($student['last_name']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="grade_level" class="form-label"><i class="bi bi-mortarboard"></i>Grade Level</label>
                        <select class="form-select" name="grade_level" id="grade_level" required>
                            <?php foreach (grade_level_options() as $g): ?>
                                <option value="<?= $g ?>" <?= $student['grade_level'] == $g ? 'selected' : '' ?>><?= esc(grade_level_label($g)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="section_id" class="form-label"><i class="bi bi-diagram-3"></i>Section</label>
                        <select class="form-select" name="section_id" id="section_id">
                            <option value="">No Section</option>
                            <?php 
                            $db = \Config\Database::connect();
                            foreach ($sections as $section): 
                                $enrollmentCount = $db->table('students')
                                    ->where('section_id', $section['id'])
                                    ->where('enrollment_status', 'enrolled')
                                    ->where('deleted_at IS NULL')
                                    ->countAllResults();
                                
                                $capacity = $section['capacity'] ?? 40;
                                $isFull = $enrollmentCount >= $capacity;
                                $isCurrentSection = $student['section_id'] == $section['id'];
                            ?>
                                <option value="<?= $section['id'] ?>" 
                                        data-grade="<?= $section['grade_level'] ?>"
                                        <?= $isCurrentSection ? 'selected' : '' ?>
                                        <?= $isFull && !$isCurrentSection ? 'disabled' : '' ?>>
                                    <?= esc($section['section_name']) ?>
                                    <?= $isFull ? ' - FULL' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="gender" class="form-label"><i class="bi bi-gender-ambiguous"></i>Gender</label>
                        <select class="form-select" name="gender" id="gender" required>
                            <option value="Male" <?= $student['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $student['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date_of_birth" class="form-label"><i class="bi bi-calendar3"></i>Date of Birth</label>
                        <input type="date" class="form-control" name="date_of_birth" id="date_of_birth" 
                               value="<?= esc($student['date_of_birth']) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="contact_number" class="form-label"><i class="bi bi-telephone"></i>Contact Number</label>
                        <input type="text" class="form-control" name="contact_number" id="contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX"
                               value="<?= esc($student['contact_number'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="place_of_birth" class="form-label"><i class="bi bi-geo-alt"></i>Place of Birth</label>
                        <input type="hidden" name="place_of_birth" id="place_of_birth"
                               value="<?= esc($student['place_of_birth'] ?? '') ?>">
                        <div data-loc-group="place_of_birth" data-loc-field="place_of_birth" data-loc-label="Place of Birth"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="nationality" class="form-label"><i class="bi bi-flag"></i>Nationality</label>
                        <input type="text" class="form-control" name="nationality" id="nationality" 
                               value="<?= esc($student['nationality'] ?? 'Filipino') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <?php
                        // Religion selector (ten most populated Philippine
                        // religions + Other), mirroring registration: a saved
                        // value outside the list re-opens "Other" with the text
                        // pre-filled, so nothing existing is ever lost.
                        $religionOptions = religion_options();
                        $religionOld      = old('religion', $student['religion'] ?? '');
                        $religionValue    = is_string($religionOld) ? trim($religionOld) : '';
                        $religionIsOther  = $religionValue !== '' && ! in_array($religionValue, $religionOptions, true);
                        ?>
                        <label for="religion" class="form-label"><i class="bi bi-brightness-high"></i>Religion</label>
                        <select class="form-select" name="religion" id="religion" onchange="toggleReligionOther()">
                            <option value="">Select religion</option>
                            <?php foreach ($religionOptions as $religionOption): ?>
                            <option value="<?= esc($religionOption) ?>" <?= $religionValue === $religionOption ? 'selected' : '' ?>><?= esc($religionOption) ?></option>
                            <?php endforeach; ?>
                            <option value="<?= esc(religion_other_option()) ?>" <?= $religionIsOther ? 'selected' : '' ?>>Other (please specify)</option>
                        </select>
                        <div class="religion-other<?= $religionIsOther ? ' is-visible' : '' ?>" id="religionOtherWrap">
                            <label class="form-label" for="religion_other">Specify Religion</label>
                            <input type="text" class="form-control" name="religion_other" id="religion_other"
                                   value="<?= $religionIsOther ? esc($religionValue) : '' ?>"
                                   data-field-label="Specify Religion"
                                   maxlength="50"
                                   <?= $religionIsOther ? 'required' : 'disabled' ?>>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="suffix" class="form-label"><i class="bi bi-tag"></i>Suffix</label>
                        <select class="form-select" name="suffix" id="suffix">
                            <option value="">None</option>
                            <option value="Jr." <?= ($student['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                            <option value="Sr." <?= ($student['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                            <option value="II" <?= ($student['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                            <option value="III" <?= ($student['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                            <option value="IV" <?= ($student['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                            <option value="V" <?= ($student['suffix'] ?? '') === 'V' ? 'selected' : '' ?>>V</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label"><i class="bi bi-house-door"></i>Address</label>
                <input type="hidden" name="address" id="address"
                       value="<?= esc($student['address'] ?? '') ?>">
                <div data-loc-group="address" data-loc-field="address" data-loc-label="Address"></div>
            </div>

            <?php helper('nutrition'); ?>
            <h6 class="tpf-section-title"><i class="bi bi-clipboard2-pulse"></i> Health / Nutrition</h6>
            <div class="form-text mb-2"><i class="bi bi-info-circle"></i>Optional: enter on behalf of the student. BMI and screening category update when all three are filled.</div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="height_cm" class="form-label"><i class="bi bi-arrows-vertical"></i>Height (cm)</label>
                    <input type="number" step="0.1" min="80" max="250" class="form-control" name="height_cm" id="height_cm"
                           value="<?= esc(old('height_cm', $student['height_cm'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="weight_kg" class="form-label"><i class="bi bi-speedometer2"></i>Weight (kg)</label>
                    <input type="number" step="0.1" min="15" max="200" class="form-control" name="weight_kg" id="weight_kg"
                           value="<?= esc(old('weight_kg', $student['weight_kg'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="ethnicity" class="form-label"><i class="bi bi-people"></i>Ethnicity</label>
                    <select class="form-select" name="ethnicity" id="ethnicity">
                        <option value="">—</option>
                        <?php $ev = old('ethnicity', $student['ethnicity'] ?? '');
                        foreach (student_ethnicity_options() as $val => $lab): ?>
                            <option value="<?= esc($val) ?>" <?= (string) $ev === (string) $val ? 'selected' : '' ?>><?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if (! empty($student['bmi']) || ! empty($student['nutrition_status'])): ?>
                <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Current: BMI <?= esc((string) ($student['bmi'] ?? '—')) ?> —
                    <?= esc(\App\Libraries\StudentNutritionClassifier::statusLabel($student['nutrition_status'] ?? \App\Libraries\StudentNutritionClassifier::STATUS_UNKNOWN)) ?></p>
            <?php endif; ?>

            <h6 class="tpf-section-title"><i class="bi bi-person-lines-fill"></i> Emergency Contact</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="emergency_contact_name" class="form-label"><i class="bi bi-person-heart"></i>Name</label>
                        <input type="text" class="form-control" name="emergency_contact_name" id="emergency_contact_name" maxlength="100" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                               value="<?= esc($student['emergency_contact_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="emergency_contact_number" class="form-label"><i class="bi bi-telephone"></i>Number</label>
                        <input type="text" class="form-control" name="emergency_contact_number" id="emergency_contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX"
                               value="<?= esc($student['emergency_contact_number'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <?php
                        // Relationship selector (the listed choices + Other),
                        // mirroring registration: a saved value outside the list
                        // re-opens "Other" with the text pre-filled, so nothing
                        // existing is ever lost.
                        $relOptions = emergency_contact_relationship_options();
                        $relOld     = old('emergency_contact_relationship', $student['emergency_contact_relationship'] ?? '', false);
                        $relValue   = is_string($relOld) ? trim($relOld) : '';
                        $relIsOther = emergency_contact_relationship_is_other($relValue);
                        ?>
                        <label for="emergency_contact_relationship" class="form-label"><i class="bi bi-people"></i>Relationship</label>
                        <select class="form-select" name="emergency_contact_relationship" id="emergency_contact_relationship" onchange="toggleRelationshipOther()">
                            <option value="">Select Relationship</option>
                            <?php foreach ($relOptions as $relOption): ?>
                            <option value="<?= esc($relOption) ?>" <?= $relValue === $relOption ? 'selected' : '' ?>><?= esc($relOption) ?></option>
                            <?php endforeach; ?>
                            <option value="<?= esc(emergency_contact_relationship_other_option()) ?>" <?= $relIsOther ? 'selected' : '' ?>>Other (please specify)</option>
                        </select>
                        <div class="religion-other<?= $relIsOther ? ' is-visible' : '' ?>" id="relationshipOtherWrap">
                            <label class="form-label" for="emergency_contact_relationship_other">Specify Relationship</label>
                            <input type="text" class="form-control" name="emergency_contact_relationship_other" id="emergency_contact_relationship_other"
                                   value="<?= $relIsOther ? esc($relValue) : '' ?>"
                                   data-field-label="Specify Relationship"
                                   maxlength="50"
                                   <?= $relIsOther ? 'required' : 'disabled' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="tpf-section-title"><i class="bi bi-journal-check"></i> Enrollment</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="enrollment_status" class="form-label"><i class="bi bi-activity"></i>Status</label>
                        <select class="form-select" name="enrollment_status" id="enrollment_status" required>
                            <option value="enrolled" selected>Enrolled</option>
                            <option value="graduated" <?= $student['enrollment_status'] == 'graduated' ? 'selected' : '' ?>>Graduated</option>
                            <option value="transferred" <?= $student['enrollment_status'] == 'transferred' ? 'selected' : '' ?>>Transferred</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="school_year" class="form-label"><i class="bi bi-calendar3"></i>School Year</label>
                        <input type="text" class="form-control" name="school_year" id="school_year" 
                               value="<?= esc($student['school_year'] ?? get_current_school_year()) ?>">
                    </div>
                </div>

            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= base_url('admin/students') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Student</button>
            </div>
            </div><!-- /.tpf-form -->
        </form>

<?php if (!empty($student['section_id'])): ?>
<div class="card mt-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-book me-2"></i>Enrolled Subjects</h5>
        <button type="button" class="btn btn-sm btn-warning" onclick="unassignSelectedSubjects(event)" id="unassignBtn" disabled>
            <i class="bi bi-x-circle"></i> Unassign Selected (<span id="selectedSubjectCount">0</span>)
        </button>
    </div>
    <div class="card-body">
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
        ?>
        <?php if (!empty($subjects)): ?>
            <div class="row g-3">
                <?php foreach ($subjects as $subject): ?>
                    <?php if (!in_array($subject['id'], $excludedSubjects)): ?>
                    <div class="col-md-3">
                        <div class="subject-card-edit">
                            <input type="checkbox" class="subject-checkbox" value="<?= $subject['id'] ?>" onchange="updateSubjectCount()">
                            <div class="subject-icon-edit">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="subject-info-edit">
                                <div class="subject-name-edit"><?= esc($subject['subject_name']) ?></div>
                                <div class="subject-code-edit"><?= esc($subject['subject_code']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> No subjects assigned to this section yet.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    'use strict';
    window.pendingSubjectIds = [];
    
    window.updateSubjectCount = function() {
        const selected = document.querySelectorAll('.subject-checkbox:checked');
        document.getElementById('selectedSubjectCount').textContent = selected.length;
        document.getElementById('unassignBtn').disabled = selected.length === 0;
    };
    
    window.unassignSelectedSubjects = function(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const selected = Array.from(document.querySelectorAll('.subject-checkbox:checked'));
        window.pendingSubjectIds = selected.map(cb => cb.value);
        
        if (window.pendingSubjectIds.length === 0) {
            alert('Please select at least one subject to unassign.');
            return false;
        }
        
        const existingModal = document.getElementById('unassignModal');
        if (existingModal) existingModal.remove();
        
        const modalHtml = `
            <div class="modal fade" id="unassignModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Unassign</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Unassign ${window.pendingSubjectIds.length} subject(s) from this student?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-warning" onclick="confirmUnassign()">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const portal = document.getElementById('dashboard-modal-portal') || document.body;
        portal.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('unassignModal'), { backdrop: 'static', keyboard: true, focus: true });
        modal.show();
        
        return false;
    };
    
    window.confirmUnassign = function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('unassignModal'));
        if (modal) modal.hide();
        
        fetch('<?= base_url('admin/students/unassign-subjects/' . $student['id']) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ subject_ids: window.pendingSubjectIds })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert(d.message);
                location.reload();
            } else {
                alert('Error: ' + (d.error || 'Failed'));
            }
        })
        .catch(e => alert('Error: ' + e.message));
    };
})();
</script>

<style>
.subject-card-edit {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
    height: 100%;
}

.subject-card-edit:hover {
    background: #eff6ff;
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.1);
}

.subject-icon-edit {
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

.subject-info-edit {
    flex: 1;
    min-width: 0;
}

.subject-name-edit {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.subject-code-edit {
    font-size: 0.8125rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.subject-card-edit input[type="checkbox"] {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 18px;
    height: 18px;
    cursor: pointer;
    z-index: 1;
}

.subject-card-edit {
    position: relative;
}

.subject-unassigned {
    opacity: 0.5;
    background: #f1f5f9 !important;
}
</style>

<script>

// The religion selector only reveals its free-text box for "Other". While
// hidden that box is disabled (so no stale text is ever submitted) and drops
// `required`, matching the registration form's behaviour.
function toggleReligionOther() {
    const select = document.getElementById('religion');
    const wrapper = document.getElementById('religionOtherWrap');
    const input = document.getElementById('religion_other');

    if (!select || !wrapper || !input) {
        return;
    }

    const isOther = select.value === <?= json_encode(religion_other_option()) ?>;

    wrapper.classList.toggle('is-visible', isOther);
    input.required = isOther;
    input.disabled = !isOther;
}

// Relationship selector: the same "Other reveals a text box" behaviour as the
// religion selector above, and the registration form.
function toggleRelationshipOther() {
    const select = document.getElementById('emergency_contact_relationship');
    const wrapper = document.getElementById('relationshipOtherWrap');
    const input = document.getElementById('emergency_contact_relationship_other');

    if (!select || !wrapper || !input) {
        return;
    }

    const isOther = select.value === <?= json_encode(emergency_contact_relationship_other_option()) ?>;

    wrapper.classList.toggle('is-visible', isOther);
    input.required = isOther;
    input.disabled = !isOther;
}

// Transferees declare the school they came from and the school year they last
// attended it. While hidden those controls are disabled (so no stale value is
// ever submitted) and drop `required`, matching the registration form.
function toggleTransfereeFields() {
    const select = document.getElementById('student_type');
    const wrapper = document.getElementById('transfereeFields');

    if (!select || !wrapper) {
        return;
    }

    const isTransferee = select.value === 'Transferee';

    wrapper.classList.toggle('is-visible', isTransferee);
    wrapper.querySelectorAll('input, select, textarea').forEach(function (field) {
        field.required = isTransferee;
        field.disabled = !isTransferee;
    });
}

// "No middle name": when ticked the field is cleared, disabled (so no stale
// value is ever submitted) and unrequired. When unticked it is required and
// must be at least two characters.
function toggleNoMiddleName() {
    const checkbox = document.getElementById('no_middle_name');
    const field = document.getElementById('middle_name');

    if (!checkbox || !field) {
        return;
    }

    const noMiddleName = checkbox.checked;

    if (noMiddleName) {
        field.value = '';
    }

    field.disabled = noMiddleName;
    field.required = !noMiddleName;
    field.style.opacity = noMiddleName ? '0.5' : '';
}

document.addEventListener('DOMContentLoaded', function () {
    // Restore the box state for a saved/redisplayed value.
    toggleReligionOther();
    toggleRelationshipOther();
    toggleTransfereeFields();
    toggleNoMiddleName();
});

// Sanitize contact and name fields on load (cleans legacy/garbage DB values)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#contact_number, #emergency_contact_number').forEach(function (el) {
        // Delegate to the shared normaliser so +63/63/dashed legacy values
        // become 09XXXXXXXXX instead of being truncated digit-by-digit.
        if (window.PhoneInput) {
            el.value = window.PhoneInput.normalise(el.value);
        } else {
            el.value = el.value.replace(/[^0-9]/g, '').slice(0, 11);
        }
    });
    document.querySelectorAll('#first_name, #middle_name, #last_name, #emergency_contact_name').forEach(function (el) {
        el.value = el.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const gradeSelect = document.getElementById('grade_level');
    const sectionSelect = document.getElementById('section_id');

    function filterSections() {
        const selectedGrade = gradeSelect.value;
        const options = sectionSelect.querySelectorAll('option');

        options.forEach(option => {
            if (option.value === '') {
                option.style.display = '';
                return;
            }

            const optionGrade = option.getAttribute('data-grade');
            if (optionGrade === selectedGrade) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
                if (option.selected) {
                    sectionSelect.value = '';
                }
            }
        });
    }

    gradeSelect.addEventListener('change', filterSections);
    filterSections();
});
</script>
    </div>
</div>

<?= $this->endSection() ?>
