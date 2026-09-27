<!-- Registration Closed Modal -->
<div class="modal fade" id="registrationClosedModal" tabindex="-1" aria-labelledby="registrationClosedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content reg-closed-modal__content">
      <div class="reg-closed-modal__header">
        <img src="<?= asset_url('LPHS2.png') ?>" alt="CSCS Logo" class="reg-closed-modal__logo" />
        <div class="reg-closed-modal__brand">
          <span class="reg-closed-modal__brand-title">Cauayan South Central School</span>
          <span class="reg-closed-modal__brand-subtitle">CSCS Tap n Track &middot; Student Registration</span>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body reg-closed-modal__body">
        <div class="reg-closed-modal__icon-wrap" aria-hidden="true">
          <i class="bi bi-lock-fill"></i>
        </div>
        <h2 class="reg-closed-modal__title" id="registrationClosedModalLabel">Registration Is Currently Closed</h2>
        <p class="reg-closed-modal__text">
          Thank you for your interest in enrolling at Cauayan South Central School. Our online
          registration portal is closed at the moment because the enrollment period for the current
          school year has ended, while our registrar finalizes class sections and student records.
        </p>
        <p class="reg-closed-modal__text">
          Please check back soon &mdash; registration will reopen once the school announces the next
          enrollment window. For updates, visit the
          <a href="<?= base_url('announcements') ?>" class="reg-closed-modal__link">Announcements</a>
          page or follow our official Facebook page at
          <a href="https://www.facebook.com/cauayan.south.central" target="_blank" rel="noopener" class="reg-closed-modal__link">facebook.com/cauayan.south.central</a>.
          For urgent concerns, you may visit the school office at Mabini Street, District I, Cauayan
          City, Isabela.
        </p>
        <div class="reg-closed-modal__note">
          <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
          Already have an account? You can still log in and access your dashboard as usual.
        </div>
      </div>
      <div class="modal-footer reg-closed-modal__footer">
        <button type="button" class="btn reg-closed-modal__btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
