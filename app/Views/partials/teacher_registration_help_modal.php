<?php

/**
 * "Need help registering?" - guidance for teacher applicants.
 *
 * The teacher-facing twin of partials/registration_help_modal.php. It exists
 * because the teacher form asks for details a first-time applicant often cannot
 * source alone: a PRC licence number they may not have been issued yet, a
 * PhilSys number and TIN that are easy to transpose, and a "Position" list that
 * will not contain the exact title their appointment letter uses. Someone who
 * cannot read an appointment letter should not lose an application to it.
 *
 * Deliberately the same markup and the same CSS as the student dialog
 * (.reg-help-modal__*). Only the id and the copy differ, so the two can never be
 * mistaken for one another on the same page and the styling cannot drift.
 *
 * The auto-open key lives in the teacher view, not here, and is deliberately
 * different from the student form's: a parent who has already read the
 * enrolment guidance should not be shown it again when they open the teacher
 * form in the same session.
 *
 * Body kept short on purpose. A guidance dialog that must be scrolled past its
 * own advice is not read.
 */

$helpPoster = mascot_poster_banner('support', ['alt' => '', 'loading' => 'eager']);
?>
<div class="modal fade reg-help-modal" id="teacherRegistrationHelpModal" tabindex="-1"
     aria-labelledby="teacherRegistrationHelpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content reg-help-modal__content">

      <div class="reg-help-modal__header">
        <h2 class="reg-help-modal__title" id="teacherRegistrationHelpModalLabel">Need help with this form?</h2>
        <button type="button" class="btn-close reg-help-modal__close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body reg-help-modal__body">
        <div class="reg-help-modal__intro">
          <p class="reg-help-modal__lead">
            You do not have to know everything before you start, and you do not
            have to have every document in hand. Here is what to gather, and what
            you can leave for the school office to check.
          </p>
          <?php if ($helpPoster !== ''): ?>
            <div class="reg-help-modal__poster"><?= $helpPoster ?></div>
          <?php endif; ?>
        </div>

        <h3 class="reg-help-modal__heading">Before you start, have these ready</h3>
        <ul class="reg-help-modal__list">
          <li>
            <strong>Your name, as it appears on your appointment papers.</strong>
            If you have no middle name, tick <em>No middle name</em> rather than
            leaving a single letter in the box.
          </li>
          <li>
            <strong>Your date of birth.</strong> It must be a real past date, and you
            must be at least <?= esc(teacher_minimum_age()) ?> years old. Anyone
            younger cannot be appointed to a teaching post, so the form will stop
            you at the field rather than after you have filled everything in.
          </li>
          <li>
            <strong>An email address you can actually open.</strong> It becomes your
            username. Your account stays inactive until an administrator approves
            it, so use an address you will still be checking in a week.
          </li>
          <li>
            <strong>A contact number starting with 09</strong>, eleven digits in total.
          </li>
          <li>
            <strong>Your PhilSys number and TIN, if you have them.</strong> Both are
            optional. A wrong digit is worse than a blank one, because a mismatched
            national ID slows the approval down.
          </li>
        </ul>

        <h3 class="reg-help-modal__heading">If a list does not have your answer</h3>
        <p class="reg-help-modal__text">
          Position, Teaching Area, Designation, Civil Status and Religion all end
          with <em>Other</em>. Choose it and a box appears where you can type the
          exact wording from your appointment letter. The literal word
          &ldquo;Other&rdquo; is never what gets saved, and a blank box is rejected
          rather than quietly stored.
        </p>

        <h3 class="reg-help-modal__heading">If you are not ready yet</h3>
        <p class="reg-help-modal__text">
          A PRC licence number, a licence release and a government employee number
          are all optional. Leave them blank and the school office will ask for
          them when your appointment is processed.
        </p>

        <p class="reg-help-modal__note">
          <i class="bi bi-info-circle me-1"></i>
          Nothing is lost by starting again. If you get stuck, go to the school
          office and apply there in person &mdash; an application filed late is far
          better than one that was never filed.
        </p>
      </div>

      <div class="modal-footer reg-help-modal__foot">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
          <i class="bi bi-pencil me-1"></i>Start registration
        </button>
      </div>

    </div>
  </div>
</div>