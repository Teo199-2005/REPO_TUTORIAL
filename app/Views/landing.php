<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<?php
// Shared branded loading overlay (see partials/page_loader.php). The same
// component is reused by the login screen, so every loader looks identical.
?>
<?= view('partials/page_loader', ['loaderId' => 'landingPageLoader']) ?>

<!-- Landing-specific CSS -->
<link href="<?= asset_url('css/landing.css') ?>" rel="stylesheet" />
<style>
/* Professional Typography & Layout */
#landing {
  font-family: 'Times New Roman', Times, serif;
  line-height: 1.6;
  margin: 0;
  padding: 0;
}
#landing .container { max-width: 1200px !important; margin: 0 auto !important; padding: 0 2rem !important; }

/* Wide content band â€” matches Vision & Mission horizontal span */
#landing .landing-section-inner {
  width: 100% !important;
  max-width: none !important;
  margin: 0 auto !important;
  padding-left: clamp(1.25rem, 6vw, 10rem) !important;
  padding-right: clamp(1.25rem, 6vw, 10rem) !important;
  box-sizing: border-box !important;
}
#landing section { padding: 5rem 0 !important; }
#landing #about-lnhs { padding: 3rem 0 !important; }

/* Typography Hierarchy */
#landing .sr-only {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: -1px !important;
  overflow: hidden !important;
  clip: rect(0, 0, 0, 0) !important;
  white-space: nowrap !important;
  border: 0 !important;
}
#landing .hero-description { font-size: 1.5rem !important; font-weight: 400 !important; line-height: 1.55 !important; max-width: 900px !important; margin: 0 auto 2rem !important; color: rgba(255, 255, 255, 0.98) !important; letter-spacing: 0 !important; text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35) !important; }

/* Hero slideshow */
#landing .hero.hero-slideshow {
  background: #0b1530 !important;
  position: relative !important;
  overflow: hidden !important;
  width: 100% !important;
  max-width: 100% !important;
  min-height: unset !important;
  height: auto !important;
  padding: 0 !important;
  margin: 0 !important;
  display: block !important;
}
#landing .hero-slideshow__stage {
  position: relative !important;
  width: 100% !important;
  aspect-ratio: 1983 / 793 !important;
  background: #0b1530 !important;
  margin-top: 1rem !important;
}
#landing .hero-slide {
  position: absolute !important;
  inset: 0 !important;
  opacity: 0 !important;
  transition: opacity 0.85s ease-in-out !important;
  pointer-events: none !important;
}
#landing .hero-slide.is-active {
  opacity: 1 !important;
  pointer-events: auto !important;
  z-index: 1 !important;
}
#landing .hero-banner-img {
  display: block !important;
  width: 100% !important;
  height: 100% !important;
  max-width: none !important;
  margin: 0 !important;
  object-fit: cover !important;
  object-position: var(--hero-object-position, center center) !important;
  vertical-align: top !important;
}
#landing .hero-slideshow__dots {
  position: absolute !important;
  bottom: 5.5rem !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  z-index: 4 !important;
  display: flex !important;
  gap: 0.5rem !important;
  padding: 0.35rem 0.65rem !important;
  background: rgba(15, 23, 42, 0.45) !important;
  border-radius: 999px !important;
  backdrop-filter: blur(6px) !important;
}
#landing .hero-slideshow__dot {
  width: 10px !important;
  height: 10px !important;
  border-radius: 50% !important;
  border: 2px solid rgba(255, 255, 255, 0.85) !important;
  background: transparent !important;
  padding: 0 !important;
  cursor: pointer !important;
  transition: background 0.2s, transform 0.2s !important;
}
#landing .hero-slideshow__dot.is-active {
  background: #fbbf24 !important;
  border-color: #fbbf24 !important;
  transform: scale(1.15) !important;
}
#landing .landing-announcement-strip {
  position: absolute !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  z-index: 6 !important;
  background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 45%, #1d4ed8 100%) !important;
  color: #fff !important;
  /* Separation from the hero is a soft shadow, not a coloured strip. */
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.14) inset, 0 6px 18px rgba(0, 0, 0, 0.18) !important;
}
#landing .landing-announcement-strip__viewport {
  overflow: hidden !important;
  width: 100% !important;
  mask-image: linear-gradient(90deg, transparent 0%, #000 3%, #000 97%, transparent 100%) !important;
  -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 3%, #000 97%, transparent 100%) !important;
}
#landing .landing-announcement-strip__track {
  display: flex !important;
  width: max-content !important;
  /* Ensure the track is at least twice the viewport so duplicated groups fully cover the strip
     even when announcement text is short. This keeps the marquee seamless. */
  min-width: 200% !important;
  animation: landingStripScroll var(--landing-strip-duration, 32s) linear infinite !important;
  will-change: transform !important;
}
#landing .landing-announcement-strip__group {
  /* Each duplicated group should cover half the viewport so two groups span full width.
     This guarantees the marquee fills left-to-right even for very short text. */
  min-width: 50vw !important;
  justify-content: center !important;
}
#landing .landing-announcement-strip__group {
  display: flex !important;
  align-items: center !important;
  flex-shrink: 0 !important;
}
#landing .landing-announcement-strip__item {
  display: inline-flex !important;
  align-items: center !important;
  gap: 0.65rem !important;
  padding: 0.5rem 1.75rem !important;
  white-space: nowrap !important;
  font-size: clamp(0.8rem, 1.6vw, 0.95rem) !important;
  font-weight: 700 !important;
  letter-spacing: 0.02em !important;
  line-height: 1.2 !important;
}
#landing .landing-announcement-strip__icon {
  color: #fbbf24 !important;
  font-size: 1.05rem !important;
  flex-shrink: 0 !important;
}
#landing .landing-announcement-strip__text {
  white-space: nowrap !important;
}
#landing .landing-announcement-strip__text .strip-link {
  color: #fbbf24 !important;
  font-weight: 700 !important;
  text-decoration: underline !important;
  text-underline-offset: 2px !important;
  /* The strip scrolls underneath a pointer that is already over the bar, so a
     plain click often lands on empty space where the text used to be. Padding
     gives the target somewhere to actually hit. */
  padding: 0.1rem 0.15rem !important;
  border-radius: 4px !important;
}
#landing .landing-announcement-strip__text .strip-link:hover,
#landing .landing-announcement-strip__text .strip-link:focus-visible {
  color: #fff !important;
  background: rgba(251, 191, 36, 0.18) !important;
  outline: 2px solid #fbbf24 !important;
  outline-offset: 2px !important;
}
/* A link travelling at marquee speed is effectively uncatchable: the pointer
   arrives where the text was rather than where it is. Hold the track still
   while the pointer is anywhere over the strip, and while a link inside it
   holds keyboard focus, so the target stays put long enough to click or to tab
   onto. Hovering the bar is a large, easy target, so this does not require
   hitting the link itself to stop the motion. */
#landing .landing-announcement-strip:hover .landing-announcement-strip__track,
#landing .landing-announcement-strip:focus-within .landing-announcement-strip__track {
  animation-play-state: paused !important;
}
#landing .landing-announcement-strip__sep {
  color: rgba(251, 191, 36, 0.65) !important;
  font-size: 0.55rem !important;
  padding: 0 0.25rem !important;
  flex-shrink: 0 !important;
  line-height: 1 !important;
}
@keyframes landingStripScroll {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
@media (prefers-reduced-motion: reduce) {
  #landing .landing-announcement-strip__track {
    animation: none !important;
    justify-content: center !important;
    width: 100% !important;
    flex-wrap: wrap !important;
  }
  #landing .landing-announcement-strip__group[aria-hidden="true"] {
    display: none !important;
  }
  #landing .hero-slide {
    transition: none !important;
  }
}

/* Minimal hero nav arrows */
.hero-nav {
  position: absolute !important;
  top: 50% !important;
  transform: translateY(-50%) !important;
  background: rgba(0,0,0,0.28) !important; /* reduced opacity */
  border: none !important;
  color: #fff !important;
  width: 56px !important;
  height: 56px !important;
  border-radius: 10px !important;
  display: grid !important;
  place-items: center !important;
  cursor: pointer !important;
  z-index: 10 !important;
  backdrop-filter: blur(4px) !important;
  box-shadow: 0 4px 18px rgba(0,0,0,0.35) !important;
}
.hero-nav i { font-size: 1.2rem !important; }
.hero-nav.hero-prev { left: 18px !important; }
.hero-nav.hero-next { right: 18px !important; }

#landing .hero-nav {
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    background: rgba(0, 0, 0, 0.36) !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.24) !important;
  }

  #landing .hero-nav i {
    font-size: 0.95rem !important;
  }

  #landing .hero-nav.hero-prev {
    left: 10px !important;
  }

  #landing .hero-nav.hero-next {
    right: 10px !important;
  }

/* Hide the dot radio indicators visually (we keep them for accessibility) */
.hero-slideshow__dots { display: none !important; }
#hero-lifelines-styles {
  display: none;
}
/* Lifelines styles (below hero) */
.hero { position: relative !important; }
.hero-lifelines {
  position: absolute !important;
  top: 50px !important; /* moved further down */
  right: 14px !important;
  display: flex !important;
  gap: 0.4rem !important;
  justify-content: flex-start !important;
  align-items: center !important;
  padding: 0.28rem 0.45rem !important; /* slightly smaller */
  background: rgba(0,0,0,0.36) !important;
  border-radius: 8px !important;
  z-index: 25 !important;
  box-shadow: 0 6px 18px rgba(0,0,0,0.35) !important;
}
.hero-lifelines .lifeline-card {
  background: rgba(0,0,0,0.45) !important;
  border-radius: 8px !important;
  display: flex !important;
  gap: 0.45rem !important;
  align-items: center !important;
  padding: 0.32rem 0.5rem !important; /* reduced */
  min-width: 0 !important;
  width: auto !important;
}
.hero-lifelines .lifeline-icon i {
  font-size: 0.95rem !important; /* a little smaller */
  color: #22c55e !important;
}
.hero-lifelines .lifeline-body .lifeline-title {
  font-weight: 700 !important;
  color: #fff !important;
  font-size: 0.8125rem !important; /* slightly smaller */
}
.hero-lifelines .lifeline-body .lifeline-status {
  font-weight: 700 !important;
  color: #22c55e !important;
  font-size: 0.82rem !important; /* slightly smaller */
}
.hero-lifelines .lifeline-card.is-down { background: rgba(255,0,0,0.08) !important; }
.hero-lifelines .lifeline-card.is-down .lifeline-icon i,
.hero-lifelines .lifeline-card.is-down .lifeline-body .lifeline-status { color: #ff4d4f !important; }
@media (max-width: 767.98px) {
  /* On small screens place lifelines as a centered thin strip above the hero content */
  .hero-lifelines {
    position: static !important;
    display: flex !important;
    flex-wrap: nowrap !important;
    gap: 0.35rem !important;
    width: 100% !important;
    margin: 0.5rem 0 0 0 !important;
    padding: 0.25rem 0.35rem !important;
    justify-content: center !important;
    box-shadow: none !important;
    background: rgba(0,0,0,0.45) !important;
  }
  .hero-lifelines .lifeline-card {
    width: auto !important;
    padding: 0.25rem 0.35rem !important;
  }
  .hero-lifelines .lifeline-icon i {
    font-size: 0.82rem !important;
  }
  .hero-lifelines .lifeline-body .lifeline-title {
    font-size: 0.8125rem !important;
  }
  .hero-lifelines .lifeline-body .lifeline-status {
    font-size: 0.76rem !important;
  }
}
#landing .hero-caption {
  position: absolute !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  z-index: 3 !important;
  padding: 1.25rem 1.5rem 1.5rem !important;
  background: linear-gradient(to top, rgba(15, 23, 42, 0.88) 0%, rgba(15, 23, 42, 0.45) 70%, transparent 100%) !important;
}
#landing .hero-caption .hero-description {
  margin: 0 auto !important;
  max-width: 900px !important;
  font-size: clamp(0.95rem, 2vw, 1.25rem) !important;
}
@media (max-width: 768px) {
  #landing .hero-slideshow__stage {
    aspect-ratio: 16 / 9 !important;
    min-height: 260px !important;
    max-height: 300px !important;
    background: #0b1530 !important;
  }
  #landing .hero-slide,
  #landing .hero-banner-img {
    height: 100% !important;
  }
  #landing .hero-banner-img {
    object-fit: cover !important;
    object-position: var(--hero-object-position, center center) !important;
    background: #0b1530 !important;
  }
  /* On small screens keep the announcement strip visible. We center and allow wrapping
     to keep content legible on narrow viewports. */
  #landing .landing-announcement-strip {
    display: block !important;
  }
  #landing .hero-slideshow__dots {
    bottom: 4.25rem !important;
    gap: 0.4rem !important;
    padding: 0.3rem 0.55rem !important;
  }
  #landing .hero-slideshow__dot {
    width: 8px !important;
    height: 8px !important;
  }
  #landing .hero-caption {
    position: absolute !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.96) 0%, rgba(15, 23, 42, 0.62) 64%, transparent 100%) !important;
    padding: 0.9rem 0.9rem 0.95rem !important;
  }
  #landing .hero-caption .hero-description {
    font-size: clamp(0.78rem, 3.35vw, 0.92rem) !important;
    line-height: 1.45 !important;
    text-wrap: balance !important;
    margin: 0 auto !important;
  }
}
@media (max-width: 480px) {
  #landing .hero-slideshow__stage {
    aspect-ratio: 3 / 4 !important;
    min-height: 320px !important;
  }
  #landing .hero-slideshow__dots {
    bottom: 3.85rem !important;
  }
  #landing .hero-caption {
    padding: 0.75rem 0.75rem 0.85rem !important;
  }
  #landing .hero-caption .hero-description {
    font-size: 0.78rem !important;
    line-height: 1.4 !important;
    max-width: 95% !important;
  }
}
#landing .section-title { font-size: 2.75rem !important; font-weight: 700 !important; line-height: 1.1 !important; margin-bottom: 1.5rem !important; color: #0f172a !important; letter-spacing: 0 !important; }
#landing .section-subtitle { font-size: 1.375rem !important; font-weight: 400 !important; color: #475569 !important; margin-bottom: 3rem !important; line-height: 1.5 !important; letter-spacing: 0 !important; }

/* Card Typography */
#landing .stats-number { font-size: 3rem !important; font-weight: 700 !important; line-height: 1 !important; margin-bottom: 0.5rem !important; }
#landing .stats-label { font-size: 1.125rem !important; font-weight: 700 !important; margin-bottom: 0.75rem !important; }
#landing .stats-trend { font-size: 0.875rem !important; font-weight: 400 !important; }
#landing .feature-title { font-size: 1.5rem !important; font-weight: 700 !important; line-height: 1.3 !important; margin-bottom: 1rem !important; }
#landing .feature-description { font-size: 1.125rem !important; font-weight: 400 !important; line-height: 1.6 !important; margin-bottom: 1.5rem !important; }
#landing .analytics-value { font-size: 2.5rem !important; font-weight: 700 !important; line-height: 1 !important; margin-bottom: 0.5rem !important; }
#landing .analytics-label { font-size: 1rem !important; font-weight: 700 !important; margin-bottom: 0.75rem !important; }

/* Professional Cards */
#landing .stats-card, #landing .feature-card, #landing .analytics-item {
  background: white !important; padding: 2.5rem 2rem !important; border-radius: 16px !important;
  box-shadow: 0 12px 40px rgba(0,0,0,0.15), 0 4px 16px rgba(0,0,0,0.08) !important; border: 1px solid rgba(0,0,0,0.05) !important;
  transition: all 0.3s ease !important; height: 100% !important;
}
#landing .stats-card:hover, #landing .feature-card:hover, #landing .analytics-item:hover {
  transform: translateY(-8px) !important; box-shadow: 0 24px 60px rgba(0,0,0,0.2), 0 8px 24px rgba(0,0,0,0.12) !important;
}

/* Icon Consistency */
#landing .stats-icon { width: 80px !important; height: 80px !important; margin: 0 auto 1.5rem !important; }
#landing .stats-icon i { font-size: 2.5rem !important; }
#landing .feature-icon-wrapper { width: 88px !important; height: 88px !important; margin: 0 auto 1.5rem !important; }
#landing .feature-icon-wrapper i { font-size: 2.75rem !important; }
#landing .analytics-icon { width: 64px !important; height: 64px !important; margin: 0 auto 1rem !important; }
#landing .analytics-icon i { font-size: 2rem !important; }

/* Grid & Spacing */
#landing .features-grid { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)) !important; gap: 2rem !important; margin-top: 3rem !important; }
#landing .analytics-grid { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 2rem !important; margin-top: 3rem !important; }

@media (max-width: 992px) {
  #landing .analytics-grid { grid-template-columns: repeat(2, 1fr) !important; }
}

@media (max-width: 576px) {
  #landing .analytics-grid { grid-template-columns: 1fr !important; }
}

#landing .info-title {
  font-size: 1rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin-bottom: 1rem !important;
}

#landing .info-text {
  font-size: 0.875rem !important; color: #64748b !important; line-height: 1.5 !important;
  margin-bottom: 1.5rem !important; flex-grow: 1 !important;
}

#landing .info-stats {
  display: flex !important; flex-direction: column !important; gap: 0.75rem !important;
}

#landing .stat-item {
  display: flex !important; align-items: center !important; gap: 0.5rem !important;
  font-size: 0.8rem !important; color: #475569 !important; font-weight: 400 !important;
}

#landing .stat-item i {
  font-size: 1rem !important;
}

#landing .process-steps { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important; gap: 2rem !important; margin-top: 3rem !important; }

/* About LNHS Section Styles */
#landing #about-lnhs {
  background: linear-gradient(135deg, #e2e8f0 0%, #d1d5db 100%) !important;
  border-top: 1px solid #cbd5e1 !important;
  border-bottom: 1px solid #cbd5e1 !important;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

#landing .highlight-card {
  background: white !important; padding: 2rem !important; border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.06) !important;
  transition: all 0.3s ease !important; text-align: center !important; height: 100% !important;
}

#landing .highlight-card:hover {
  transform: translateY(-6px) !important; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18), 0 6px 16px rgba(0, 0, 0, 0.1) !important;
  border-color: #3b82f6 !important;
}

#landing .highlight-icon {
  width: 60px !important; height: 60px !important;
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(59, 130, 246, 0.15) 100%) !important;
  border: 2px solid rgba(30, 64, 175, 0.2) !important; border-radius: 50% !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  margin: 0 auto 1rem !important; transition: all 0.3s ease !important;
}

#landing .highlight-icon i { font-size: 1.5rem !important; color: #1e40af !important; }

#landing .highlight-card h4 {
  font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin-bottom: 0.75rem !important;
}

#landing .highlight-card p {
  font-size: 1rem !important; color: #64748b !important; margin-bottom: 0 !important;
  line-height: 1.5 !important;
}

#landing .about-image .image-wrapper {
  position: relative !important; border-radius: 16px !important; overflow: hidden !important;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important; transition: all 0.3s ease !important;
}

#landing .campus-image {
  width: 100% !important; height: 300px !important;
  background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
  background-image: url('https://images.pexels.com/photos/5905709/pexels-photo-5905709.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1') !important;
  background-size: cover !important; background-position: center !important;
}

#landing .image-overlay {
  position: absolute !important; bottom: 0 !important; left: 0 !important; right: 0 !important;
  background: linear-gradient(transparent, rgba(0, 0, 0, 0.7)) !important;
  padding: 2rem !important; color: white !important;
}

#landing .overlay-content { text-align: center !important; }
#landing .overlay-content i { font-size: 2rem !important; margin-bottom: 0.5rem !important; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; background-clip: text !important; }
#landing .overlay-content p { margin: 0 !important; font-size: 0.875rem !important; font-weight: 400 !important; }

#landing .tourism-excellence, #landing .partnerships-section {
  background: white !important; padding: 3rem !important; border-radius: 16px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15), 0 4px 16px rgba(0, 0, 0, 0.08) !important;
}

#landing .excellence-title, #landing .partnerships-title {
  font-size: 2rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin-bottom: 0.5rem !important;
}

#landing .excellence-subtitle, #landing .partnerships-subtitle {
  font-size: 1.125rem !important; color: #64748b !important; font-weight: 400 !important;
}

#landing .facility-card {
  background: #f8fafc !important; padding: 2.5rem !important; border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important; transition: all 0.3s ease !important; height: 100% !important;
}

#landing .facility-card:hover {
  background: white !important; transform: translateY(-6px) !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18), 0 6px 16px rgba(0, 0, 0, 0.1) !important; border-color: #3b82f6 !important;
}

#landing .facility-header {
  display: flex !important; align-items: center !important; gap: 1rem !important;
  margin-bottom: 1.5rem !important;
}

#landing .facility-icon {
  width: 50px !important; height: 50px !important;
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(59, 130, 246, 0.15) 100%) !important;
  border: 2px solid rgba(30, 64, 175, 0.2) !important; border-radius: 50% !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  flex-shrink: 0 !important;
}

#landing .facility-icon i { font-size: 1.25rem !important; color: #1e40af !important; }

#landing .facility-header h4 {
  font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin: 0 !important;
}

#landing .facility-list {
  list-style: none !important; padding: 0 !important; margin: 0 !important;
}

#landing .facility-list li {
  padding: 0.5rem 0 !important; border-bottom: 1px solid #e2e8f0 !important;
  color: #64748b !important; font-size: 0.95rem !important; position: relative !important;
  padding-left: 1.5rem !important;
}

#landing .facility-list li::before {
  content: 'â€¢' !important; color: #1e40af !important; font-weight: bold !important;
  position: absolute !important; left: 0 !important;
}

#landing .partnerships-grid {
  display: grid !important; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
  gap: 2rem !important; margin-top: 2rem !important;
}

#landing .partnership-item {
  text-align: center !important; padding: 2rem !important; background: #f8fafc !important;
  border-radius: 12px !important; border: 1px solid #e2e8f0 !important;
  transition: all 0.3s ease !important;
}

#landing .partnership-item:hover {
  background: white !important; transform: translateY(-6px) !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18), 0 6px 16px rgba(0, 0, 0, 0.1) !important; border-color: #3b82f6 !important;
}

#landing .partnership-icon {
  width: 60px !important; height: 60px !important;
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(59, 130, 246, 0.15) 100%) !important;
  border: 2px solid rgba(30, 64, 175, 0.2) !important; border-radius: 50% !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  margin: 0 auto 1rem !important; transition: all 0.3s ease !important;
}

#landing .partnership-icon i { font-size: 1.5rem !important; color: #1e40af !important; }

#landing .partnership-item h5 {
  font-size: 1.125rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin-bottom: 0.5rem !important;
}

#landing .partnership-item p {
  font-size: 0.875rem !important; color: #64748b !important; margin: 0 !important;
  line-height: 1.5 !important;
}

#landing .excellence-badge {
  display: inline-flex !important; align-items: center !important; gap: 1rem !important;
  background: white !important; padding: 2rem 3rem !important; border-radius: 16px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15), 0 4px 16px rgba(0, 0, 0, 0.08) !important;
  transition: all 0.3s ease !important;
}

#landing .badge-icon {
  width: 60px !important; height: 60px !important;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(34, 197, 94, 0.15) 100%) !important;
  border: 2px solid rgba(16, 185, 129, 0.2) !important; border-radius: 50% !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  flex-shrink: 0 !important;
}

#landing .badge-icon i { font-size: 1.5rem !important; color: #10b981 !important; }

#landing .badge-content h4 {
  font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin: 0 0 0.25rem 0 !important;
}

#landing .badge-content p {
  font-size: 0.875rem !important; color: #64748b !important; margin: 0 !important;
  line-height: 1.5 !important;
}

/* Portal Highlights â€” poster + 3Ã—2 feature grid */
#landing .portal-highlights-section {
  padding-top: clamp(2rem, 4vw, 3rem) !important;
  padding-bottom: clamp(2rem, 4vw, 3rem) !important;
}

#landing .portal-highlights-section .portal-highlights-header {
  text-align: center !important;
  margin-bottom: clamp(1.5rem, 3vw, 2.25rem) !important;
  max-width: 52rem !important;
  margin-left: auto !important;
  margin-right: auto !important;
}

#landing .portal-highlights-section .portal-highlights-header .section-subtitle {
  margin-bottom: 0 !important;
}

#landing .portal-highlights-body {
  display: grid !important;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
  gap: clamp(1rem, 2.2vw, 2rem) !important;
  align-items: stretch !important;
}

#landing .portal-poster {
  margin: 0 !important;
  padding: 0 !important;
  width: 100% !important;
  height: 100% !important;
  min-height: 0 !important;
  display: flex !important;
  align-items: center !important;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  border-radius: 0 !important;
  overflow: visible !important;
  line-height: 0 !important;
}

#landing .portal-poster__img {
  display: block !important;
  width: 100% !important;
  height: auto !important;
  max-width: 100% !important;
  border-radius: 12px !important;
  border: 1px solid rgba(30, 64, 175, 0.12) !important;
  box-shadow: 0 10px 28px rgba(30, 64, 175, 0.16) !important;
}

#landing .portal-features-grid {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  grid-template-rows: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.6rem !important;
  height: 100% !important;
  min-height: 0 !important;
  align-content: stretch !important;
  max-width: none !important;
  margin: 0 !important;
}

/* Minimal compact feature tiles (portal section only) */
#landing .portal-features-grid .feature-item {
  background: #ffffff !important;
  background-image: none !important;
  padding: 0.85rem 0.9rem !important;
  border-radius: 9px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: none !important;
  text-align: left !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: flex-start !important;
  gap: 0.25rem !important;
  height: 100% !important;
  min-height: 0 !important;
}

#landing .portal-features-grid .feature-item:hover {
  transform: none !important;
  background: #f8fafc !important;
  border-color: #bfdbfe !important;
  box-shadow: 0 1px 4px rgba(30, 64, 175, 0.06) !important;
}

#landing .portal-features-grid .feature-item i {
  font-size: clamp(1.15rem, 1.6vw, 1.35rem) !important;
  color: #1e40af !important;
  margin: 0 0 0.35rem !important;
  line-height: 1 !important;
}

#landing .portal-features-grid .feature-item h4 {
  font-size: clamp(0.92rem, 1.25vw, 1.05rem) !important;
  font-weight: 700 !important;
  color: #0f172a !important;
  margin: 0 0 0.35rem !important;
  line-height: 1.35 !important;
}

#landing .portal-features-grid .feature-item p {
  font-size: clamp(0.82rem, 1.05vw, 0.92rem) !important;
  color: #64748b !important;
  line-height: 1.45 !important;
  margin: 0 0 0.4rem !important;
  flex: 1 1 auto !important;
  min-height: 0 !important;
  display: -webkit-box !important;
  -webkit-line-clamp: 4 !important;
  -webkit-box-orient: vertical !important;
  overflow: hidden !important;
}

#landing .portal-features-grid .feature-badges {
  justify-content: flex-start !important;
  margin-bottom: 0 !important;
  margin-top: auto !important;
  gap: 0.3rem !important;
}

#landing .portal-features-grid .feature-badges span {
  background: #f1f5f9 !important;
  color: #475569 !important;
  font-size: clamp(0.72rem, 0.9vw, 0.8rem) !important;
  font-weight: 400 !important;
  padding: 0.15rem 0.45rem !important;
  border-radius: 4px !important;
  border: none !important;
  backdrop-filter: none !important;
}

#landing .portal-features-grid .feature-item a {
  display: none !important;
}

@media (max-width: 1199.98px) {
  #landing .portal-highlights-body {
    grid-template-columns: 1fr !important;
    align-items: start !important;
  }

  #landing .portal-poster {
    height: auto !important;
    display: block !important;
    max-width: min(1100px, 100%) !important;
    margin-left: auto !important;
    margin-right: auto !important;
  }

  #landing .portal-features-grid {
    height: auto !important;
    grid-template-rows: none !important;
  }

  #landing .portal-features-grid .feature-item {
    height: auto !important;
  }
}

@media (max-width: 768px) {
  #landing .portal-features-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    grid-template-rows: none !important;
  }
}

@media (max-width: 480px) {
  #landing .portal-features-grid {
    grid-template-columns: 1fr !important;
  }

}

#landing .info-grid {
  display: grid !important; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
  gap: 1rem !important;
}

#landing .info-item {
  background: white !important; padding: 1.5rem !important; border-radius: 8px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04) !important;
  display: flex !important; align-items: center !important; gap: 0.75rem !important;
  transition: all 0.3s ease !important;
}

#landing .info-item:hover {
  transform: translateY(-4px) !important; box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.08) !important;
  border-color: #3b82f6 !important;
}

#landing .info-item i {
  font-size: 1.25rem !important; color: #1e40af !important; flex-shrink: 0 !important;
}

#landing .info-item strong {
  color: #0f172a !important; margin-right: 0.5rem !important;
}

#landing .features-row {
  display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 2rem !important;
}

#landing .feature-box {
  background: white !important; padding: 2rem !important; border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.06) !important;
  transition: all 0.3s ease !important;
}

#landing .feature-box:hover {
  transform: translateY(-6px) !important; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18), 0 6px 16px rgba(0, 0, 0, 0.1) !important;
  border-color: #3b82f6 !important;
}

#landing .feature-box h4 {
  font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important;
  margin-bottom: 0.5rem !important; display: flex !important; align-items: center !important;
  gap: 0.5rem !important;
}

#landing .feature-box h4 i {
  color: #1e40af !important;
}

#landing .feature-subtitle {
  font-size: 0.875rem !important; color: #64748b !important; margin-bottom: 1rem !important;
  font-style: italic !important;
}

#landing .feature-details {
  display: flex !important; flex-direction: column !important; gap: 0.75rem !important;
}

#landing .feature-details span {
  font-size: 0.875rem !important; color: #475569 !important; line-height: 1.5 !important;
  padding: 0.75rem !important; background: #f8fafc !important; border-radius: 6px !important;
}

#landing .partnership-list {
  display: flex !important; flex-direction: column !important; gap: 0.5rem !important;
}

#landing .partnership-list span {
  font-size: 0.875rem !important; color: #475569 !important; display: flex !important;
  align-items: center !important; gap: 0.5rem !important; padding: 0.5rem !important;
  background: #f8fafc !important; border-radius: 6px !important;
}

#landing .partnership-list span i {
  color: #1e40af !important; flex-shrink: 0 !important;
}

#landing .compliance-row {
  display: grid !important; grid-template-columns: 2fr 1fr !important; gap: 2rem !important;
  align-items: center !important;
}

#landing .compliance-badge {
  background: white !important; padding: 1.5rem !important; border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1), 0 2px 6px rgba(0, 0, 0, 0.05) !important;
  display: flex !important; align-items: center !important; gap: 1rem !important;
  transition: all 0.3s ease !important;
}

#landing .compliance-badge:hover {
  transform: translateY(-4px) !important; box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08) !important;
  border-color: #10b981 !important;
}

#landing .compliance-badge i {
  font-size: 2rem !important; color: #10b981 !important; flex-shrink: 0 !important;
}

#landing .compliance-badge div {
  font-size: 0.875rem !important; color: #475569 !important; line-height: 1.4 !important;
}

#landing .compliance-badge strong {
  color: #0f172a !important;
}

#landing .campus-badge {
  background: white !important; padding: 1.5rem !important; border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1), 0 2px 6px rgba(0, 0, 0, 0.05) !important;
  display: flex !important; align-items: center !important; gap: 0.75rem !important;
  justify-content: center !important; text-align: center !important;
  transition: all 0.3s ease !important;
}

#landing .campus-badge:hover {
  transform: translateY(-4px) !important; box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08) !important;
  border-image: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%) 1 !important;
}

#landing .campus-badge i {
  font-size: 1.5rem !important; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; background-clip: text !important;
}

#landing .campus-badge span {
  font-size: 0.875rem !important; color: #475569 !important; font-weight: 400 !important;
}

/* Responsive Design */
@media (max-width: 768px) {
  #landing .features-row {
    grid-template-columns: 1fr !important;
  }

  #landing .compliance-row {
    grid-template-columns: 1fr !important;
  }

  #landing .info-grid {
    grid-template-columns: 1fr !important;
  }

  .site-footer {
    padding: 2rem 0 0.75rem !important;
  }

  #landing .footer-main {
    grid-template-columns: 1fr !important;
    gap: 1.2rem !important;
    margin-bottom: 1.5rem !important;
    padding-bottom: 1rem !important;
  }

  #landing .footer-brand {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.75rem !important;
    justify-content: space-between !important;
    align-items: center !important;
  }

  #landing .footer-brand > * {
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }

  #landing .footer-bottom {
    flex-direction: row !important;
    flex-wrap: wrap !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 0.75rem !important;
    text-align: left !important;
  }

  #landing .footer-brand .brand-logo {
    justify-content: flex-start !important;
    gap: 0.75rem !important;
  }

  #landing .footer-brand .brand-logo h3 {
    font-size: 1.1rem !important;
  }

  #landing .footer-links h4,
  #landing .footer-info h4 {
    font-size: 1rem !important;
    margin-bottom: 1rem !important;
  }

  #landing .footer-links,
  #landing .footer-info,
  #landing .contact-info {
    font-size: 0.95rem !important;
  }

  #landing a[href*="facebook.com"] {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1rem !important;
    padding: 0 !important;
  }
}

/* Horizontal Features Layout */
#landing .features-horizontal {
  display: grid !important;
  grid-template-columns: repeat(6, 1fr) !important;
  gap: 1.5rem !important;
  max-width: 1400px !important;
  margin: 0 auto !important;
}

#landing .feature-item {
  background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
  padding: 1.5rem !important;
  border-radius: 12px !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2) !important;
  text-align: center !important;
  transition: all 0.3s ease !important;
  display: flex !important;
  flex-direction: column !important;
  height: 100% !important;
  position: relative !important;
  overflow: hidden !important;
  background-image:

    linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
  background-size: 20px 20px, 20px 20px, 100% 100% !important;
}

#landing .feature-item:hover {
  transform: translateY(-4px) !important;
  box-shadow: 0 12px 24px rgba(30, 64, 175, 0.3) !important;
  border-color: rgba(255, 255, 255, 0.2) !important;
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
}

#landing .feature-item i {
  font-size: 2.5rem !important;
  color: white !important;
  margin-bottom: 1rem !important;
}

#landing .feature-item h4 {
  font-size: 1rem !important;
  font-weight: 700 !important;
  color: white !important;
  margin-bottom: 0.75rem !important;
  line-height: 1.3 !important;
}

#landing .feature-item p {
  font-size: 0.8rem !important;
  color: rgba(255, 255, 255, 0.9) !important;
  line-height: 1.4 !important;
  margin-bottom: 1rem !important;
  flex-grow: 1 !important;
}

#landing .feature-badges {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 0.25rem !important;
  justify-content: center !important;
  margin-bottom: 1rem !important;
}

#landing .feature-badges span {
  background: rgba(255, 255, 255, 0.15) !important;
  color: white !important;
  font-size: 0.7rem !important;
  padding: 0.25rem 0.5rem !important;
  border-radius: 4px !important;
  font-weight: 400 !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  backdrop-filter: blur(10px) !important;
}

#landing .feature-item a {
  color: white !important;
  text-decoration: none !important;
  font-size: 0.8rem !important;
  font-weight: 700 !important;
  transition: all 0.3s ease !important;
  background: rgba(255, 255, 255, 0.1) !important;
  padding: 0.5rem 1rem !important;
  border-radius: 6px !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  backdrop-filter: blur(10px) !important;
}

#landing .feature-item a:hover {
  background: rgba(255, 255, 255, 0.2) !important;
  border-color: rgba(255, 255, 255, 0.3) !important;
  text-decoration: none !important;
}

/* Responsive Design for Features */
@media (max-width: 1200px) {
  #landing .features-horizontal {
    grid-template-columns: repeat(3, 1fr) !important;
  }
}

@media (max-width: 768px) {
  #landing .features-horizontal {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}

@media (max-width: 480px) {
  #landing .features-horizontal {
    grid-template-columns: 1fr !important;
  }
}

/* Portal Highlights grid â€” override generic .features-horizontal breakpoints */
#landing #portal-highlights .portal-features-grid {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  grid-template-rows: repeat(2, minmax(0, 1fr)) !important;
}

@media (max-width: 1199.98px) {
  #landing #portal-highlights .portal-features-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    grid-template-rows: none !important;
  }
}

@media (max-width: 768px) {
  #landing #portal-highlights .portal-features-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}

@media (max-width: 480px) {
  #landing #portal-highlights .portal-features-grid {
    grid-template-columns: 1fr !important;
  }
}

/* Portal overview / process steps (legacy: enrollment-process-section) */
#landing .process-timeline {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  max-width: 1200px !important;
  margin: 0 auto !important;
  position: relative !important;
}

#landing .process-item {
  background: white !important;
  padding: 2rem 1.5rem !important;
  border-radius: 16px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15), 0 4px 16px rgba(0, 0, 0, 0.08) !important;
  text-align: center !important;
  flex: 1 !important;
  max-width: 250px !important;
  position: relative !important;
  transition: all 0.3s ease !important;
  background-image:

  background-size: 20px 20px !important;
}

#landing .process-item:hover {
  transform: translateY(-10px) !important;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2), 0 8px 24px rgba(0, 0, 0, 0.12) !important;
  border-color: #3b82f6 !important;
}

#landing .process-number {
  position: absolute !important;
  top: -25px !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  width: 30px !important;
  height: 30px !important;
  background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
  color: white !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-weight: 700 !important;
  font-size: 0.875rem !important;
  border: 3px solid white !important;
  box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3) !important;
}

#landing .process-icon {
  width: 60px !important;
  height: 60px !important;
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(59, 130, 246, 0.15) 100%) !important;
  border: 2px solid rgba(30, 64, 175, 0.2) !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  margin: 0 auto 1.5rem !important;
  transition: all 0.3s ease !important;
}

#landing .process-icon i {
  font-size: 1.5rem !important;
  color: #1e40af !important;
}

#landing .process-item:hover .process-icon {
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.2) 0%, rgba(59, 130, 246, 0.25) 100%) !important;
  border-color: rgba(30, 64, 175, 0.4) !important;
  transform: scale(1.1) !important;
}

#landing .process-item h4 {
  font-size: 1.25rem !important;
  font-weight: 700 !important;
  color: #0f172a !important;
  margin-bottom: 1rem !important;
}

#landing .process-item p {
  font-size: 0.875rem !important;
  color: #64748b !important;
  line-height: 1.5 !important;
  margin: 0 !important;
}

#landing .process-connector {
  width: 60px !important;
  height: 2px !important;
  background: linear-gradient(90deg, #e2e8f0 0%, #1e40af 50%, #e2e8f0 100%) !important;
  position: relative !important;
  margin: 0 -10px !important;
}

#landing .process-connector::before {
  content: '' !important;
  position: absolute !important;
  right: -5px !important;
  top: -3px !important;
  width: 0 !important;
  height: 0 !important;
  border-top: 4px solid transparent !important;
  border-bottom: 4px solid transparent !important;
}

/* Responsive Design for Process */
@media (max-width: 768px) {
  #landing .process-timeline {
    flex-direction: column !important;
    gap: 2rem !important;
  }

  #landing .process-connector {
    width: 2px !important;
    height: 40px !important;
    margin: 0 !important;
    background: linear-gradient(180deg, #e2e8f0 0%, #1e40af 50%, #e2e8f0 100%) !important;
  }

  #landing .process-connector::before {
    right: -3px !important;
    bottom: -5px !important;
    top: auto !important;
    border-left: 4px solid transparent !important;
    border-right: 4px solid transparent !important;
    border-bottom: none !important;
  }

  #landing .process-item {
    max-width: 100% !important;
  }
}

/* Footer Section */
.site-footer {
  background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
  color: white !important;
  padding: 4rem 0 1rem !important;
  position: relative !important;
  background-image:

  background-size: 25px 25px !important;
  border-top: 3px solid transparent !important;
  border-image: linear-gradient(90deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%) 1 !important;
}

#landing .footer-content {
  max-width: 1200px !important;
  margin: 0 auto !important;
}

#landing .footer-main {
  display: grid !important;
  grid-template-columns: 2fr 1fr 1fr 1fr !important;
  gap: 3rem !important;
  margin-bottom: 3rem !important;
  padding-bottom: 2rem !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
}

#landing .footer-brand .brand-logo {
  display: flex !important;
  align-items: center !important;
  gap: 1rem !important;
  margin-bottom: 2rem !important;
}

#landing .footer-brand .brand-logo i {
  font-size: 2.5rem !important;
  color: #60a5fa !important;
}

#landing .footer-brand .brand-logo h3 {
  font-size: 1.5rem !important;
  font-weight: 700 !important;
  color: white !important;
  margin: 0 !important;
}

#landing .contact-info {
  display: flex !important;
  flex-direction: column !important;
  gap: 0.75rem !important;
}

#landing .contact-item {
  display: flex !important;
  align-items: center !important;
  gap: 0.75rem !important;
  font-size: 0.875rem !important;
  color: rgba(255, 255, 255, 0.9) !important;
}

#landing .contact-item i {
  font-size: 1rem !important;
  color: #93c5fd !important;
  width: 16px !important;
  flex-shrink: 0 !important;
}

#landing .footer-links h4,
#landing .footer-info h4 {
  font-size: 1.125rem !important;
  font-weight: 700 !important;
  color: white !important;
  margin-bottom: 1.5rem !important;
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  padding-bottom: 0.5rem !important;
  border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
}

#landing .footer-links h4 i,
#landing .footer-info h4 i {
  color: #60a5fa !important;
}

#landing .links-grid {
  display: flex !important;
  flex-direction: column !important;
  gap: 0.75rem !important;
}

#landing .footer-links,
#landing .footer-info {
  position: relative !important;
}

#landing .footer-links::after,
#landing .footer-info::after {
  content: '' !important;
  position: absolute !important;
  right: -1.5rem !important;
  top: 0 !important;
  bottom: 0 !important;
  width: 1px !important;
  background: linear-gradient(180deg, transparent, rgba(255, 255, 255, 0.3), transparent) !important;
}

#landing .links-grid a {
  color: rgba(255, 255, 255, 0.9) !important;
  text-decoration: none !important;
  font-size: 0.875rem !important;
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  transition: all 0.3s ease !important;
  padding: 0.5rem 0 !important;
  border-left: 2px solid transparent !important;
  padding-left: 0.75rem !important;
}

#landing .links-grid a:hover {
  border-left: 2px solid transparent !important;
  border-image: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%) 1 !important;
}



#landing .links-grid a i {
  font-size: 0.875rem !important;
  color: #60a5fa !important;
  width: 16px !important;
}

#landing .info-items {
  display: flex !important;
  flex-direction: column !important;
  gap: 0.75rem !important;
}

#landing .info-item {
  display: flex !important;
  align-items: center !important;
  gap: 0.75rem !important;
  font-size: 0.875rem !important;
  color: rgba(255, 255, 255, 0.9) !important;
}

#landing .info-item i {
  font-size: 1rem !important;
  color: #34d399 !important;
  width: 16px !important;
  flex-shrink: 0 !important;
}

#landing .footer-divider {
  height: 1px !important;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent) !important;
  margin: 2rem 0 !important;
}

#landing .footer-bottom {
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  padding-top: 2rem !important;
  border-top: 2px solid rgba(255, 255, 255, 0.2) !important;
  margin-top: 1rem !important;
}

#landing .copyright,
#landing .system-info {
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  font-size: 0.875rem !important;
  color: rgba(255, 255, 255, 0.8) !important;
}

#landing .copyright i {
 
 
  color: #93c5fd !important;
}

#landing .system-info i {
  color: #60a5fa !important;
}

/* Responsive Footer */
@media (max-width: 768px) {
  .site-footer {
    padding: 2rem 0 0.75rem !important;
  }

  #landing .footer-main {
    grid-template-columns: 1fr !important;
    gap: 1.2rem !important;
    margin-bottom: 1.5rem !important;
    padding-bottom: 1rem !important;
  }

  #landing .footer-brand {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.75rem !important;
    justify-content: space-between !important;
    align-items: center !important;
  }

  #landing .footer-brand > * {
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }

  #landing .footer-bottom {
    flex-direction: row !important;
    flex-wrap: wrap !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 0.75rem !important;
    text-align: left !important;
  }

  #landing .footer-brand .brand-logo {
    justify-content: flex-start !important;
    gap: 0.75rem !important;
  }

  #landing .footer-brand .brand-logo h3 {
    font-size: 1.1rem !important;
  }

  #landing .footer-links h4,
  #landing .footer-info h4 {
    font-size: 1rem !important;
    margin-bottom: 1rem !important;
  }

  #landing .footer-links,
  #landing .footer-info,
  #landing .contact-info {
    font-size: 0.95rem !important;
  }

  #landing a[href*="facebook.com"] {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1rem !important;
    padding: 0 !important;
  }
}

/* DepEd Badge Top Right */
.hero-badge-top-right {
  position: fixed;
  top: 6rem;
  right: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
  padding: 1rem 1.5rem;
  border-radius: 12px;
  border: 1px solid rgba(30, 64, 175, 0.2);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(20px);
  transition: all 0.3s ease;
  z-index: 1001;
}

.hero-badge-top-right:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2), 0 6px 24px rgba(30, 64, 175, 0.15);
}

.deped-seal {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(251, 191, 36, 0.55);
  box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
}

.deped-seal i {
  font-size: 1.25rem;
  color: white;
}

.badge-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.badge-title {
  color: #1e40af;
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0;
}

.badge-subtitle {
  color: #475569;
  font-weight: 400;
  font-size: 0.875rem;
  letter-spacing: 0.01em;
}

.badge-id {
  color: #64748b;
  font-weight: 700;
  font-size: 0.8125rem;
  letter-spacing: 0.025em;
  text-transform: uppercase;
}

@media (max-width: 768px) {
  .hero-badge-top-right {
    top: 1rem;
    right: 1rem;
    padding: 1rem 1.5rem;
    gap: 0.75rem;
  }

  .deped-seal {
    width: 50px;
    height: 50px;
  }

  .deped-seal i {
    font-size: 1.5rem;
  }

  .badge-title {
    font-size: 1rem;
  }

  .badge-subtitle {
    font-size: 0.8rem;
  }

  .badge-id {
    font-size: 0.7rem;
  }
}

/* Small Badges */
.hero-badge-small {
  position: absolute;
  right: 2rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.85) 100%);
  padding: 1rem 1.5rem;
  border-radius: 12px;
  border: 1px solid rgba(30, 64, 175, 0.15);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(15px);
  transition: all 0.3s ease;
  z-index: 9;
}

.hero-badge-small.tesda {
  top: 9rem;
}

.hero-badge-small.iso {
  top: 12rem;
}

.hero-badge-small:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
}

.small-seal {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(251, 191, 36, 0.55);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.small-seal i {
  font-size: 1.25rem;
  color: white;
}

.small-content {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.small-title {
  color: #1e40af;
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0;
}

.small-subtitle {
  color: #64748b;
  font-weight: 400;
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
}

@media (max-width: 768px) {
  .hero-badge-small {
    right: 1rem;
    padding: 0.75rem 1rem;
    gap: 0.5rem;
  }

  .hero-badge-small.tesda {
    top: 10rem;
  }

  .hero-badge-small.iso {
    top: 13rem;
  }

  .small-seal {
    width: 35px;
    height: 35px;
  }

  .small-seal i {
    font-size: 1rem;
  }

  .small-title {
    font-size: 0.8rem;
  }

  .small-subtitle {
    font-size: 0.7rem;
  }
}

/* Section backgrounds — portal and other landing sections

   These used to hotlink batthern.png and dotnoise from transparenttextures.com,
   which put a third-party request on every visitor's page load and broke the
   texture entirely if that host was unreachable. The local Tappy tile replaces
   both when it is installed; the external URL stays as the fallback so a site
   with no artwork is unchanged.

   The tile is thin line art at 640x213, so it renders at natural size rather
   than stretched: stretching a 3:1 tile across a 1200px section would smear the
   motifs. Remove the background-size line to tile it denser instead. */
<?php
$tappyPattern = mascot_pattern_url();
$lightTexture = $tappyPattern !== ''
  ? "url('" . $tappyPattern . "')"
  : 'url("https://www.transparenttextures.com/patterns/batthern.png")';
$darkTexture = $tappyPattern !== ''
  ? "url('" . $tappyPattern . "')"
  : 'url("https://www.transparenttextures.com/patterns/dotnoise-light-grey.png")';
?>
#landing .section-light {
  background-color: #ffffff !important;
  background-image: <?= $lightTexture ?> !important;
  background-size: auto !important;
  background-repeat: repeat !important;
}
#landing .section-dark {
  background-color: #dbeafe !important;
  background-image: <?= $darkTexture ?> !important;
  background-size: auto !important;
  background-repeat: repeat !important;
}
</style>

<div id="landing">
  <?= $this->include('partials/landing_hero') ?>
</div>

<script>
(function () {
  // PageLoader.show/hide already no-op when the overlay is absent, so there is
  // no need to probe for the element here: an early return would skip the
  // hide() call and could leave the loader stuck on screen.
  var api = window.PageLoader;
  if (!api || !api.show || !api.hide) { return; }

  // Lock page scroll while the landing page loads (handled by PageLoader).
  api.show('landingPageLoader');

  var start = Date.now();
  var minMs = 1100;

  function dismiss() {
    var wait = Math.max(0, minMs - (Date.now() - start));
    window.setTimeout(function () {
      api.hide('landingPageLoader');
    }, wait);
  }

  if (document.readyState === 'complete') {
    dismiss();
  } else {
    window.addEventListener('load', dismiss, { once: true });
  }
})();
</script>
<script>
(function () {
  var hero = document.querySelector('#landing .hero-slideshow');
  if (!hero) return;

  var slides = hero.querySelectorAll('.hero-slide');
  var dots = hero.querySelectorAll('.hero-slideshow__dot');
  if (!slides.length) return;

  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (slides.length === 1 || reducedMotion) return;

  var current = 0;
  var timer = null;
  var intervalMs = 6000;

  function goTo(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach(function (slide, i) {
      slide.classList.toggle('is-active', i === current);
    });
    dots.forEach(function (dot, i) {
      dot.classList.toggle('is-active', i === current);
      dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
    });
  }

  function next() {
    goTo(current + 1);
  }

  function start() {
    stop();
    timer = window.setInterval(next, intervalMs);
  }

  function stop() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      var idx = parseInt(dot.getAttribute('data-goto'), 10);
      if (!isNaN(idx)) {
        goTo(idx);
        start();
      }
    });
  });

  // Prev/Next arrow handlers
  var prevBtn = hero.querySelector('.hero-prev');
  var nextBtn = hero.querySelector('.hero-next');
  if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); start(); });
  if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); start(); });

  // Keyboard navigation (left/right)
  hero.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowLeft') { ev.preventDefault(); goTo(current - 1); start(); }
    if (ev.key === 'ArrowRight') { ev.preventDefault(); goTo(current + 1); start(); }
  });

  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);
  hero.addEventListener('focusin', stop);
  hero.addEventListener('focusout', start);

  start();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script>
(function () {
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  var root = document.querySelector('#landing');
  if (!root) return;

  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion) return;

  var heroEase = 'power3.out';
  var scrollEase = 'power2.out';

  // Hero entrance (formal + subtle)
  var heroDesc = root.querySelector('.hero-description');
  if (heroDesc) {
    gsap.timeline({ defaults: { ease: heroEase } }).from(heroDesc, { opacity: 0, y: 20, duration: 0.55 });
  }

  // Utility: animate in without hiding content permanently.
  // We do NOT set opacity:0 upfront in CSS; ScrollTrigger.batch will only animate when entering view.
  function batchReveal(targets, opts) {
    ScrollTrigger.batch(targets, {
      start: 'top 90%',
      once: true,
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { autoAlpha: 1, y: (opts && opts.fromY) || 18, x: (opts && opts.fromX) || 0 },
          {
            autoAlpha: 1,
            y: 0,
            x: 0,
            duration: (opts && opts.duration) || 0.6,
            ease: scrollEase,
            stagger: (opts && opts.stagger) || 0.06,
            overwrite: 'auto',
            immediateRender: false
          }
        );
      }
    });
  }

  // Titles + section subtitles
  batchReveal('#landing .section-title', { fromY: 20, duration: 0.55, stagger: 0.08 });
  batchReveal('#landing .section-subtitle', { fromY: 14, duration: 0.5, stagger: 0.08 });

  // Features, process, accreditations
  batchReveal('#landing .feature-item', { fromY: 18, duration: 0.6, stagger: 0.06 });
  batchReveal('#landing .process-item', { fromY: 18, duration: 0.6, stagger: 0.08 });
  batchReveal('#landing .accreditation-badge', { fromY: 14, duration: 0.55, stagger: 0.06 });

  // CTA
  ScrollTrigger.create({
    trigger: '#landing .cta-section',
    start: 'top 90%',
    once: true,
    onEnter: function () {
      var cta = root.querySelector('#landing .cta-content');
      if (!cta) return;
      gsap.fromTo(cta, { autoAlpha: 1, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.6, ease: scrollEase, immediateRender: false });
    }
  });

  // Ensure triggers account for images/layout
  window.addEventListener('load', function () {
    ScrollTrigger.refresh();
  });
})();
</script>
<script>
(function () {
  var track = document.querySelector('#landing .landing-announcement-strip__track');
  if (!track) return;

  function updateStripDuration() {
    var distance = track.offsetWidth / 2;
    var speed = 70; // pixels per second
    var duration = Math.max(16, Math.min(60, Math.round(distance / speed)));
    track.style.setProperty('--landing-strip-duration', duration + 's');
  }

  updateStripDuration();
  window.addEventListener('resize', updateStripDuration);
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script>
(function () {
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  var root = document.querySelector('#landing');
  if (!root) return;

  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion) return;

  var heroEase = 'power3.out';
  var scrollEase = 'power2.out';

  // Hero entrance (formal + subtle)
  var heroDesc = root.querySelector('.hero-description');
  if (heroDesc) {
    gsap.timeline({ defaults: { ease: heroEase } }).from(heroDesc, { opacity: 0, y: 20, duration: 0.55 });
  }

  // Utility: animate in without hiding content permanently.
  // We do NOT set opacity:0 upfront in CSS; ScrollTrigger.batch will only animate when entering view.
  function batchReveal(targets, opts) {
    ScrollTrigger.batch(targets, {
      start: 'top 90%',
      once: true,
      onEnter: function (batch) {
        gsap.fromTo(
          batch,
          { autoAlpha: 1, y: (opts && opts.fromY) || 18, x: (opts && opts.fromX) || 0 },
          {
            autoAlpha: 1,
            y: 0,
            x: 0,
            duration: (opts && opts.duration) || 0.6,
            ease: scrollEase,
            stagger: (opts && opts.stagger) || 0.06,
            overwrite: 'auto',
            immediateRender: false
          }
        );
      }
    });
  }

  // Titles + section subtitles
  batchReveal('#landing .section-title', { fromY: 20, duration: 0.55, stagger: 0.08 });
  batchReveal('#landing .section-subtitle', { fromY: 14, duration: 0.5, stagger: 0.08 });

  // Features, process, accreditations
  batchReveal('#landing .feature-item', { fromY: 18, duration: 0.6, stagger: 0.06 });
  batchReveal('#landing .process-item', { fromY: 18, duration: 0.6, stagger: 0.08 });
  batchReveal('#landing .accreditation-badge', { fromY: 14, duration: 0.55, stagger: 0.06 });

  // CTA
  ScrollTrigger.create({
    trigger: '#landing .cta-section',
    start: 'top 90%',
    once: true,
    onEnter: function () {
      var cta = root.querySelector('#landing .cta-content');
      if (!cta) return;
      gsap.fromTo(cta, { autoAlpha: 1, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.6, ease: scrollEase, immediateRender: false });
    }
  });

  // Ensure triggers account for images/layout
  window.addEventListener('load', function () {
    ScrollTrigger.refresh();
  });
})();
</script>

<?= $this->endSection() ?>


