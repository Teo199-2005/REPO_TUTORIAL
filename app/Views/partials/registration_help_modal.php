<?php

/**
 * "Need help registering?" - guidance for parents and teachers.
 *
 * Exists because the registration wizard asks for 24 fields, and for a
 * Kindergarten, Grade 1 or Grade 2 enrolment most of that is unfamiliar: an LRN
 * looks like a random number, the previous school may be "none", and the birth
 * certificate has to be read closely to get a name right. A parent who cannot
 * read a birth certificate should not lose an enrolment to it.
 *
 * Opens once per browser session on arrival, and stays reachable from the
 * "Need help?" button for the whole visit - the people who need it most are
 * least likely to find a one-time popup.
 *
 * Minimalist by design: the school banner and the logo are gone, and the
 * ENROLL NOW poster sits BESIDE the opening line rather than above the whole
 * body, where a full-width illustration pushed the guidance below the fold and
 * defeated the dialog. The guidance itself is kept in full, because a parent
 * who cannot read a birth certificate is exactly who it is for, and trimming
 * that to look tidier would cost the people it was written for.
 *
 * Dark with white type, matching the registration card it is opened from -- a
 * white dialog flashed a bright sheet into the middle of a dark page.
 *
 * The teacher-facing twin is partials/teacher_registration_help_modal.php. Same
 * CSS, different id, different copy.
 */

$helpPoster = mascot_poster_banner('enroll', ['alt' => '', 'loading' => 'eager']);
?>
<div class="modal fade reg-help-modal" id="registrationHelpModal" tabindex="-1"
     aria-labelledby="registrationHelpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content reg-help-modal__content">

      <div class="reg-help-modal__header">
        <h2 class="reg-help-modal__title" id="registrationHelpModalLabel">Need help with this form?</h2>
        <button type="button" class="btn-close reg-help-modal__close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body reg-help-modal__body">
        <?php /* Poster beside the opening line, not stacked above the body. Stacked
                 it pushed the guidance below the fold, which is the one thing this
                 dialog must not do -- the guidance is the content and the poster is
                 only a reason to look. Beside it, the illustration costs no height. */ ?>
        <div class="reg-help-modal__intro">
          <p class="reg-help-modal__lead">
            You do not have to do this alone, and you do not have to know everything
            before you start. Here is what to gather, and who can help you.
          </p>
          <?php if ($helpPoster !== ''): ?>
            <div class="reg-help-modal__poster"><?= $helpPoster ?></div>
          <?php endif; ?>
        </div>

        <h3 class="reg-help-modal__heading">Before you start, have these ready</h3>
        <ul class="reg-help-modal__list">
          <li>
            <strong>The birth certificate.</strong> The full name must match it exactly,
            including the middle name. If the certificate is still with the local
            civil registrar, the school office can help you check it.
          </li>
          <li>
            <strong>The LRN (Learner Reference Number).</strong> Twelve digits, issued by
            DepEd. For a first-time enrollee there is none yet &mdash; leave it blank and
            the school assigns one during enrolment.
          </li>
          <li>
            <strong>The last school attended and the school year.</strong> If this is the
            child's first enrolment, enter <em>None</em> rather than leaving it blank.
          </li>
          <li>
            <strong>A parent or guardian's contact number</strong>, plus one emergency
            contact who is not the parent, if possible.
          </li>
          <li>
            <strong>Nationality and religion.</strong> Choose the closest match from the
            list; if it is not there, pick <em>Other</em> and type it in. Both are
            required.
          </li>
        </ul>

        <h3 class="reg-help-modal__heading">For Kindergarten, Grade 1 and Grade 2</h3>
        <p class="reg-help-modal__text">
          These are our youngest learners, and the form asks for more than most
          families expect. You have three options, and all of them are fine:
        </p>
        <ul class="reg-help-modal__list reg-help-modal__list--options">
          <li>
            <strong>Ask the school office to help you fill it in.</strong> Bring the birth
            certificate and your own ID. The office can complete the form with you at the
            counter.
          </li>
          <li>
            <strong>Ask your child's teacher or the grade-level adviser.</strong> They know
            which pupils still need an LRN and can confirm the details with you.
          </li>
          <li>
            <strong>Have another parent or guardian help.</strong> Anyone helping must have
            the birth certificate with them. Do not share a photo of it online.
          </li>
        </ul>
        <p class="reg-help-modal__note">
          <i class="bi bi-info-circle me-1"></i>
          Registration does not close when this form does. If you get stuck, save nothing
          &mdash; start again later, or go to the office. An incomplete enrolment is far
          worse for a child than a late one.
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
