<?php
/**
 * Principal section of the public About page.
 *
 * Photo, name and rank come from school_principal(), which reads the values an
 * admin saved under Admin > Settings. Until a photo is uploaded it falls back
 * to the bundled principal2.png, so this block never renders a broken image.
 */
$principal = school_principal();
?>
<!-- Principal Section -->
<section class="principal-section section-light py-5">
  <div class="landing-section-inner">
    <div class="principal-card">
      <div class="principal-accent-bar" aria-hidden="true"></div>
      <div class="principal-card-inner">
        <div class="principal-photo-wrap scroll-animate-left">
          <div class="principal-photo-frame">
            <img src="<?= esc($principal['photo_url']) ?>" alt="<?= esc($principal['name']) ?>" class="principal-photo">
            <span class="principal-badge"><?= esc($principal['rank']) ?></span>
          </div>
        </div>
        <div class="principal-body scroll-animate-right">
          <header class="principal-header">
            <p class="principal-label">A message from our</p>
            <h2 class="principal-name"><?= esc($principal['name']) ?></h2>
            <p class="principal-rank">
              <span class="principal-rank-badge"><?= esc($principal['rank']) ?></span>
              <span class="principal-school">Cauayan South Central School</span>
            </p>
          </header>
          <blockquote class="principal-message-box">
            <i class="bi bi-quote principal-quote principal-quote--open" aria-hidden="true"></i>
            <div class="principal-message">
              <p>Cauayan South Central School is a complete, large school located at Mabini Street, District I, Cauayan City, Isabela. Which is surrounded by the following barangays: Barangay District II in the north; District III in the west, and District I in the south. These barangays are classified as catchment areas.</p>
              <p>It offers Kindergarten, Grade 1 to 6 classes implementing K-12 Curriculum for grades 1,2,3,4, and 5 and SNED, SSES, SPJ and SPED curriculum, ALS and MADRASAH. The total enrollment for the current school year is 2,678 with 110 teaching and non-teaching personnel.</p>
              <p>The school is more than seven decades old now. It has been delivering quality education since 1952.</p>
              <p>CSCS embodies the spirit of education in the city, offering a dynamic and enriching environment where students can learn, grow, and thrive amidst the vibrant urban landscape.</p>
            </div>
            <i class="bi bi-quote principal-quote principal-quote--close" aria-hidden="true"></i>
          </blockquote>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="section-divider"></div>

<!-- Vision & Mission Section -->
<section id="vision-mission-section" class="vision-mission-section section-light py-5">
  <div class="landing-section-inner">
    <div class="text-center mb-5">
      <h2 class="section-title">Our Vision & Mission</h2>
      <p class="section-subtitle">Guiding principles that shape our commitment to educational excellence</p>
    </div>

    <!-- Vision and Mission as two branded, bordered panels.

             Each carries a brand lockup, a tinted ground drawn from the site's own
             navy and amber, and the official statement set as a quotation. The two
             grounds are near neighbours rather than opposites - a cool blue and a
             warm sand - so the pair reads as one family instead of two competing
             blocks.

             "vision-mission-panel" is kept on the article alongside the new class
         because about.php drives its GSAP reveal off that exact selector. -->
    <div class="vision-mission-row">

      <article class="vision-mission-panel vm-panel vm-panel--vision">
        <header class="vm-panel__head">
          <img class="vm-panel__logo" src="<?= asset_url('LPHS2.png') ?>" alt="" aria-hidden="true">
          <div class="vm-panel__headings">
            <h3 class="vm-title">Vision</h3>
            <p class="vm-panel__kicker">Cauayan South Central School</p>
          </div>
        </header>

        <div class="vm-panel__body">
          <blockquote class="vm-quote">
            <p>We dream of Filipinos who passionately love their country and whose values and competencies enable them to realize their full potential and contribute meaningfully to building the nation.</p>
            <p>As a learner-centered public institution, the Department of Education continuously improves itself to better serve its stakeholders.</p>
          </blockquote>

          <p class="vm-panel__lead">At Cauayan South Central School, we envision graduates who are:</p>
          <ul class="vm-list">
            <li>Globally competitive yet rooted in Filipino values, equipped with 21st-century skills and digital literacy to thrive in an ever-changing world.</li>
            <li>Empowered to become lifelong learners, critical thinkers, and responsible citizens who contribute to sustainable development and social progress.</li>
          </ul>
        </div>
      </article>

      <article class="vision-mission-panel vm-panel vm-panel--mission">
        <header class="vm-panel__head">
          <img class="vm-panel__logo" src="<?= asset_url('LPHS2.png') ?>" alt="" aria-hidden="true">
          <div class="vm-panel__headings">
            <h3 class="vm-title">Mission</h3>
            <p class="vm-panel__kicker">Cauayan South Central School</p>
          </div>
        </header>

        <div class="vm-panel__body">
          <blockquote class="vm-quote">
            <p>To protect and promote the right of every Filipino to quality, equitable, culture-based and complete basic education where:</p>
          </blockquote>

          <ul class="vm-list">
            <li>Students learn in a child-friendly, gender-sensitive, safe and motivating environment.</li>
            <li>Teachers facilitate learning and constantly nurture every learner.</li>
            <li>Administrators and staff, as stewards of the institution, ensure an enabling and supportive environment for effective learning to happen.</li>
            <li>Family, community and other stakeholders are actively engaged and share responsibility for developing life-long learners.</li>
          </ul>
        </div>
      </article>

    </div>
  </div>
</section>
