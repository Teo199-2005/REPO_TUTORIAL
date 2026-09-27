<div class="section-divider"></div>

<section id="portal-highlights" class="section-dark portal-highlights-section" aria-label="Portal highlights">
  <div class="landing-section-inner">
    <div class="portal-highlights-header">
      <h2 class="section-title">Portal Highlights</h2>
      <p class="section-subtitle">Tools that support grading, scheduling, attendance, and school communication in one web-based system.</p>
    </div>
    <div class="portal-highlights-body">
      <figure class="portal-poster">
        <img
          class="portal-poster__img"
          src="<?= about_poster_url() ?>"
          width="1536"
          height="1024"
          alt="CSCS Tap n Track — modern school management overview"
          loading="lazy"
          decoding="async"
        >
      </figure>
      <div class="portal-features-grid">
        <div class="feature-item scroll-animate">
          <i class="bi bi-people-fill"></i>
          <h4>Role-Based Access</h4>
          <p>Secure areas for admins, teachers, students, and parents—each role sees only what they need.</p>
          <div class="feature-badges">
            <span>Secure login</span><span>Role dashboards</span>
          </div>
        </div>
        <div class="feature-item scroll-animate">
          <i class="bi bi-database-fill-lock"></i>
          <h4>Student &amp; Class Records</h4>
          <p>Learner profiles, sections, and subjects for class organization and tracking.</p>
          <div class="feature-badges">
            <span>Sections</span><span>Subjects</span>
          </div>
        </div>
        <div class="feature-item scroll-animate">
          <i class="bi bi-graph-up-arrow"></i>
          <h4>Grades &amp; Reports</h4>
          <p>Grade entry and viewing for teachers; analytics for administrators.</p>
          <div class="feature-badges">
            <span>Grades</span><span>Analytics</span>
          </div>
        </div>
        <div class="feature-item scroll-animate">
          <i class="bi bi-megaphone-fill"></i>
          <h4>Announcements &amp; Materials</h4>
          <p>School-wide or targeted news and learning resources.</p>
          <div class="feature-badges">
            <span>Announcements</span><span>Materials</span>
          </div>
        </div>
        <div class="feature-item scroll-animate">
          <i class="bi bi-bell-fill"></i>
          <h4>In-App Notifications</h4>
          <p>Portal alerts and optional email—no SMS mass broadcasts.</p>
          <div class="feature-badges">
            <span>In-app</span><span>Email-ready</span>
          </div>
        </div>
        <div class="feature-item scroll-animate">
          <i class="bi bi-calendar-check-fill"></i>
          <h4>Schedules &amp; Attendance</h4>
          <p>Class timetables and daily attendance for assigned sections.</p>
          <div class="feature-badges">
            <span>Schedules</span><span>Attendance</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="section-divider"></div>

<?php
/**
 * The four poster slots for "Using the Portal".
 *
 * Resolved through mascot_poster_for(), which is an allowlist: a slot with no
 * artwork installed falls back to a placeholder naming the exact file to drop in,
 * so this section is safe to ship before the images exist and never renders a
 * broken image.
 *
 * To add the artwork, generate the four PNGs and drop them in
 * public/assets/mascot/ under these names. The key is also registered in
 * mascot_helper.php and tools/mascot_assets.php; a file that is not registered
 * there is reported as UNDOCUMENTED by the validator.
 */
$portalPosterSlots = [
    [
        'key'   => 'portal-login',
        'file'  => 'poster-portal-login.png',
        'title' => 'Login',
        'text'  => 'Use the credentials issued by the school. Students, teachers, and parents receive access through the registrar, class adviser, or authorized school personnel.',
    ],
    [
        'key'   => 'portal-grades',
        'file'  => 'poster-portal-grades.png',
        'title' => 'Grades &amp; attendance',
        'text'  => 'Teachers record attendance and grades based on school policy. Students and parents can view only the information permitted by the school.',
    ],
    [
        'key'   => 'portal-schedules',
        'file'  => 'poster-portal-schedules.png',
        'title' => 'Schedules &amp; class information',
        'text'  => 'View schedules and class-related updates published by administrators and teachers, aligned with the approved school-year schedule.',
    ],
    [
        'key'   => 'portal-updates',
        'file'  => 'poster-portal-updates.png',
        'title' => 'Stay informed',
        'text'  => 'Read school announcements and in-portal notifications for official updates from school administrators and teachers.',
    ],
];
?>
<section id="portal-overview" class="enrollment-process-section section-light py-5" aria-label="Using the portal">
  <div class="landing-section-inner">
    <div class="text-center mb-5">
      <h2 class="section-title">Using the Portal</h2>
      <p class="section-subtitle">CSCS Tap n Track is a school management portal for daily academic operations of Cauayan South Central School. It supports authorized school users and is not a public enrollment or AI chatbot platform.</p>
    </div>

    <?php /* Artwork first, caption beneath. Never text over the poster: these are
             full-frame illustrations with no clear area to set type in, so a
             headline laid on top would be unreadable. */ ?>
    <div class="portal-poster-grid">
      <?php foreach ($portalPosterSlots as $index => $slot): ?>
        <?php
        // Empty alt on purpose: the speech bubble below carries every word of
        // meaning, so announcing the illustration as well would just repeat it.
        $art = mascot_poster_for($slot['key'], ['alt' => '', 'loading' => 'lazy']);
        ?>
        <figure class="portal-poster-card">
          <?php /* Decorative. The grid order already conveys the sequence, so a
                   bare "1" announced on its own would be noise for no gain. */ ?>
          <span class="portal-poster-card__number" aria-hidden="true"><?= $index + 1 ?></span>
          <?php if ($art !== ''): ?>
            <?= $art ?>
          <?php else: ?>
            <?php /* Placeholder, shown only while the artwork is missing. It names
                     the file to drop in, and disappears on its own the moment that
                     file exists, so nothing has to be switched off later. */ ?>
            <div class="portal-poster-slot" aria-hidden="true">
              <i class="bi bi-image"></i>
              <code><?= esc($slot['file']) ?></code>
            </div>
          <?php endif; ?>
          <?php /* The caption is a speech bubble rather than plain text: the
                   character is talking to the reader, and the tail points back up
                   at it. All of it is live HTML, so the words stay translatable,
                   resizable and readable by a screen reader. */ ?>
          <figcaption class="portal-poster-card__bubble">
            <h4 class="portal-poster-card__title"><?= $slot['title'] ?></h4>
            <p class="portal-poster-card__text"><?= $slot['text'] ?></p>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>

  </div>
</section>
