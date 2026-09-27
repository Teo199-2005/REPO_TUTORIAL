/**
 * Tappy — guided tour of the portal.
 *
 * Behaviour (agreed with the user):
 *   - starts automatically the first time a role signs in;
 *   - a permanent "Take a tour" button in the top bar replays it.
 *
 * Design notes:
 *   - the mascot images may not exist yet, in which case the card renders
 *     without art and the tour still works (same rule as mascot.js);
 *   - steps are declared per path and any step whose target is missing is
 *     skipped, so a page that changes shape never strands the tour;
 *   - the overlay is an accessible dialog: focus is trapped, Escape closes it,
 *     and focus returns to whatever opened it.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'mascot.tour.v1';
  var FALLBACK_POSE = 'point-right';

  /**
   * Steps per page. `sel` is a CSS selector; a step whose target is not on the
   * page is skipped, so this list can safely describe several layouts.
   */
  function steps() {
    return {
      'admin/dashboard': [
        { sel: '.app-sidebar', title: 'Everything lives here',
          text: 'Students, Teachers, Sections and Settings are grouped in this menu. Collapse it with the button at the top to get more space.',
          pose: 'point-right' },
        { sel: '.top-bar-actions', title: 'Your tools',
          text: 'Notifications and your profile are here, on every page. Tappy sits in the corner - click him to ask for help.',
          pose: 'point-up' },
        { sel: '.admin-stat, .stats-card, .stat-tile', title: 'Your numbers at a glance',
          text: 'Each tile summarises one thing. Open any of them to drill into the full list.',
          pose: 'chart' },
        { sel: 'main, .app-content, .content', title: 'Start anywhere',
          text: 'Whatever you came here to do is one click away. Nothing is more than two menus deep.',
          pose: 'point-down' }
      ],
      'admin/students': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Filters that just work',
          text: 'Search or pick a filter and the list updates by itself. Anything extra is tucked behind "More filters".',
          pose: 'search' },
        { sel: '.admin-filter-bar__more, .admin-filter-bar button', title: 'More filters',
          text: 'The remaining filters live behind this button. The number tells you how many are narrowing the list right now.',
          pose: 'point-down' },
        { sel: '.admin-table, .data-table, table', title: 'Your list',
          text: 'Sort by any column heading, and open a student with the eye button to see everything on file.',
          pose: 'point-right' },
        { sel: '.empty-state, .alert-info', title: 'Nothing here yet?',
          text: 'An empty list is normal on a fresh install. Use the Add button and Tappy will keep score for you.',
          pose: 'search' }
      ],
      'admin/teachers': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Find a teacher fast',
          text: 'Search by name, Employee No., TIN, PRC or email. Filters apply as soon as you choose them.',
          pose: 'search' },
        { sel: '.admin-table, .data-table, table', title: 'Teacher records',
          text: 'Open a teacher to edit personnel details, assignments and schedules.',
          pose: 'point-right' },
        { sel: '.app-sidebar', title: 'Where the rest lives',
          text: 'Sections, subjects, schedules and grading rules all hang off the same menu.',
          pose: 'point-right' }
      ],
      'admin/sections': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Filter sections',
          text: 'Narrow by grade level, or find the sections that still need an adviser.',
          pose: 'search' },
        { sel: '.admin-table, .data-table, table', title: 'Each section',
          text: 'Create sections here, assign an adviser and set the capacity for each class.',
          pose: 'laptop' },
        { sel: '.app-sidebar', title: 'Students roll up from here',
          text: 'A student belongs to a section, and their grades and attendance follow it.',
          pose: 'point-right' }
      ],
      'admin/subjects': [
        { sel: '.admin-table, .data-table, table', title: 'Subjects',
          text: 'Add each subject once, then attach it to the grade levels that offer it.',
          pose: 'reading' },
        { sel: '.app-sidebar', title: 'Why this matters',
          text: 'Subjects drive the gradebook. A grade cannot be entered for a subject that does not exist here.',
          pose: 'point-right' }
      ],
      'admin/grades': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Pick a class and a quarter',
          text: 'Grades are entered per section, per subject, per quarter. Set those three first.',
          pose: 'search' },
        { sel: '.admin-table, .data-table, table', title: 'The gradebook',
          text: 'Type straight into the cells. Everything saves as you go, and the general average updates itself.',
          pose: 'chart' }
      ],
      'admin/analytics': [
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'Trends, not just totals',
          text: 'Each chart answers one question: enrolment over time, attendance, or performance per year level.',
          pose: 'chart' },
        { sel: '.app-sidebar', title: 'Back to the data',
          text: 'Analytics is read-only. Use Students or Grades to change anything.',
          pose: 'point-right' }
      ],
      // Keyed 'admin/schedules', not 'admin/schedule': the route and the sidebar
      // entry are plural, and the old singular key matched nothing at all.
      'admin/schedules': [
        { sel: '.admin-table, .data-table, table', title: 'Building a schedule',
          text: 'Pick a section, then place each subject into a time slot. Conflicts are flagged as you go.',
          pose: 'laptop' },
        { sel: '.app-sidebar', title: 'Applies to everyone',
          text: 'Once saved, students and teachers both see the new schedule immediately.',
          pose: 'point-right' }
      ],
      'admin/announcements': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Post an announcement',
          text: 'Write it, choose who can see it, and publish. It appears on the right dashboards straight away.',
          pose: 'bust' },
        { sel: '.app-sidebar', title: 'Check who is reading',
          text: 'Announcements show up on the dashboard of every role you targeted.',
          pose: 'point-up' }
      ],
      'admin/materials': [
        { sel: '.admin-table, .data-table, table', title: 'Learning materials',
          text: 'Upload a file, file it under a subject, and the students and teachers who need it will see it.',
          pose: 'laptop' },
        { sel: '.app-sidebar', title: 'Kept with the subject',
          text: 'Materials are organised by subject, so they stay findable as the library grows.',
          pose: 'point-right' }
      ],
      'admin/audit-log': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'What happened, and who did it',
          text: 'Every important action is recorded here. This log cannot be edited or deleted.',
          pose: 'reading' },
        { sel: '.admin-table, .data-table, table', title: 'Read it in plain English',
          text: 'Each row says what happened in words, who did it, and where from.',
          pose: 'point-right' }
      ],
      'admin/id-cards': [
        { sel: '.admin-table, .data-table, table', title: 'Student ID cards',
          text: 'Every enrolled student gets a card with their photo, name and ID number.',
          pose: 'tap-card' },
        { sel: '.app-sidebar', title: 'Print in a batch',
          text: 'Select a section and print the whole set at once, or one card at a time.',
          pose: 'laptop' }
      ],
      'admin/settings': [
        { sel: '.admin-table, .data-table, table, .card', title: 'School settings',
          text: 'School identity, grading rules and the school year live here. Changes apply across the whole portal.',
          pose: 'laptop' },
        { sel: '.app-sidebar', title: 'Takes effect everywhere',
          text: 'Anything you change on this page is shared by every dashboard in the school.',
          pose: 'point-right' }
      ],
      'admin/backups': [
        { sel: '.card, .admin-table, button', title: 'Backups',
          text: 'Download a copy of the whole system before any large change. It is the fastest way back if something goes wrong.',
          pose: 'laptop' }
      ],

      // The pages that publish something. Each tour step has to say WHERE the
      // content ends up, not just what the controls are - the whole reason these
      // pages exist is that the admin is editing a page nobody is standing on.
      'admin/landing-page': [
        { sel: '.card, .admin-table, button', title: 'The front page',
          text: 'Three hero slides and one announcement strip. This is the first thing a visitor sees at the home address, so save and then go and look at it.',
          pose: 'hero' }
      ],
      'admin/childpro-gad': [
        { sel: '.card, .admin-table, button', title: 'Two public pages',
          text: 'CHILDPRO and GAD are separate tabs. Each takes a header image and up to six sections, and that is exactly what appears on its public page.',
          pose: 'celebrate' },
        { sel: 'form', title: 'And my own message',
          text: 'The last card writes what I say on that public page. Leave it empty and I go back to my built-in wording.',
          pose: 'megaphone' }
      ],
      'admin/programs-projects': [
        { sel: '.card, .admin-table, button', title: 'Two public pages',
          text: 'Programs and Projects are separate tabs. Each takes a header image and up to six sections, and that is exactly what appears on its public page.',
          pose: 'celebrate' },
        { sel: 'form', title: 'And my own message',
          text: 'The last card writes what I say on that public page. Leave it empty and I go back to my built-in wording.',
          pose: 'megaphone' }
      ],
      'admin/records': [
        { sel: '.card, .admin-table, button', title: 'Everything on file',
          text: 'Report cards, learner development reports and archived enrolments, one section at a time.',
          pose: 'reading' }
      ],
      'admin/password-resets': [
        { sel: '.card, .admin-table, table, button', title: 'Someone cannot get in',
          text: 'Approve the request and send them a one-time link. Until you do, they stay locked out.',
          pose: 'worried' }
      ],
      'admin/student-nutrition': [
        { sel: '.card, .admin-table, table, button', title: 'Weigh-ins and BMI',
          text: 'Record what each learner weighs and keep the notes beside it. The export is what goes into the school records.',
          pose: 'chart' }
      ],
      'admin/platform-ratings': [
        { sel: '.card, .admin-table, table', title: 'What people are saying',
          text: 'Ratings and comments from staff and learners. This is the page that reaches the people who can act on it.',
          pose: 'star' }
      ],
      'admin/profile': [
        { sel: '.card, form', title: 'Your own details',
          text: 'Your name, your contact details and your password. Nothing here is shared with anyone else.',
          pose: 'bust' }
      ],
      'student/dashboard': [
        { sel: '.app-sidebar', title: 'Your portal',
          text: 'Your subjects, grades, profile and announcements are all in this menu.',
          pose: 'point-right' },
        { sel: '.top-bar-actions', title: 'Notifications',
          text: 'Anything new from your teachers shows up here.',
          pose: 'point-up' },
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'Your day at a glance',
          text: 'Your next class, your general average and the latest announcements are all on this page.',
          pose: 'chart' }
      ],
      'student/grades': [
        { sel: '.stats-card, .card, .admin-stat', title: 'Your grades',
          text: 'This is your general weighted average, and how it compares with last term.',
          pose: 'chart' },
        { sel: '.admin-table, .data-table, table', title: 'Subject by subject',
          text: 'Every quarter you have been graded for, one row per subject.',
          pose: 'reading' }
      ],
      'student/schedule': [
        { sel: '.admin-table, .data-table, table', title: 'Your daily schedule',
          text: 'Subjects, times and teachers, in the order your day runs.',
          pose: 'point-down' },
        { sel: '.app-sidebar', title: 'Check it any time',
          text: 'If your teacher changes a slot, the new schedule is here straight away.',
          pose: 'point-right' }
      ],
      // Keyed 'student/id-cards' to match the route and the sidebar. The old
      // singular key matched nothing at all.
      'student/id-cards': [
        { sel: '.card, .admin-table', title: 'Your ID card',
          text: 'Here is your card with your photo and ID number. Print it whenever you need it.',
          pose: 'tap-card' },
        { sel: '.app-sidebar', title: 'Keep it accurate',
          text: 'If your photo or name is wrong, ask your adviser to fix it in the Students list.',
          pose: 'point-up' }
      ],
      'student/materials': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Learning materials',
          text: 'Everything your teachers have uploaded, organised by subject.',
          pose: 'reading' },
        { sel: '.app-sidebar', title: 'New files appear here',
          text: 'When a teacher uploads something new, it lands at the top of this list.',
          pose: 'point-up' }
      ],
      'student/announcements': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Announcements',
          text: 'News from your teachers and the school office. The newest is at the top.',
          pose: 'bust' }
      ],
      'teacher/dashboard': [
        { sel: '.app-sidebar', title: 'Your teaching tools',
          text: 'Your sections, grades, attendance and schedules are grouped in this menu.',
          pose: 'point-right' },
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'Your day at a glance',
          text: 'Your next class, how many students you have and anything you still need to mark.',
          pose: 'chart' }
      ],
      'teacher/attendance': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Pick a section',
          text: 'Attendance is marked per section. Choose one, and only that class is listed.',
          pose: 'search' },
        { sel: '.admin-table, .data-table, table', title: 'Marking attendance',
          text: 'Tap a status for each student. Everything saves as you go.',
          pose: 'tap-card' }
      ],
      'teacher/grades': [
        { sel: '.admin-filter-bar, .filter-bar', title: 'Section, subject, quarter',
          text: 'Narrow to exactly the classbook you are marking before you type a single grade.',
          pose: 'search' },
        { sel: '.admin-table, .data-table, table', title: 'Your gradebook',
          text: 'Type straight into the cells. The general average updates itself.',
          pose: 'chart' }
      ],
      'teacher/sections': [
        { sel: '.admin-table, .data-table, table', title: 'Your sections',
          text: 'The classes assigned to you, with the grade level and student count for each.',
          pose: 'point-right' },
        { sel: '.app-sidebar', title: 'Work from here',
          text: 'Attendance, grades and schedules all start from the section you pick.',
          pose: 'point-right' }
      ],
      'teacher/schedule': [
        { sel: '.admin-table, .data-table, table', title: 'Your teaching schedule',
          text: 'Every class you handle, with the time and the section.',
          pose: 'laptop' }
      ],
      'teacher/messages': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Messages',
          text: 'From parents and the school office. The newest is at the top.',
          pose: 'bust' }
      ],
      'teacher/sned': [
        { sel: '.admin-table, .data-table, table, .card', title: 'SNED records',
          text: 'Special Needs and Education act entries for the learners in your care.',
          pose: 'reading' }
      ],
      'parent/dashboard': [
        { sel: '.app-sidebar', title: 'Following your child',
          text: 'Attendance, grades and announcements are all in this menu.',
          pose: 'point-right' },
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'At a glance',
          text: "Your child's latest average, attendance rate and any new announcements.",
          pose: 'chart' }
      ],
      'parent/grades': [
        { sel: '.stats-card, .card, .admin-stat', title: 'Grades',
          text: "Your child's general average, and how it compares with the previous quarter.",
          pose: 'chart' },
        { sel: '.admin-table, .data-table, table', title: 'Subject by subject',
          text: 'Every quarter that has been encoded so far, one row per subject.',
          pose: 'reading' }
      ],
      'parent/announcements': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Announcements',
          text: 'News from the school office and your child\'s teachers.',
          pose: 'megaphone' }
      ],
      'parent/children': [
        { sel: '.app-sidebar, .card, .admin-table', title: 'Your children',
          text: 'Everyone linked to your account is listed here. Pick one and their own attendance, grades and reports open up.',
          pose: 'point-down' }
      ],

      // Student pages that had no tour. `profile` and `notifications` below are
      // keyed without an area because there is no top-level /profile or
      // /notifications route; the real pages are student/profile and
      // student/notifications, so both sets exist.
      'student/profile': [
        { sel: '.card, form', title: 'Finish this to unlock the rest',
          text: 'Your photo, your BMI and your contact details. The dashboard stays locked until all three are done.',
          pose: 'bust' }
      ],
      'student/notifications': [
        { sel: '.app-content, main, .card', title: 'Notifications',
          text: 'Everything the school and your teachers have sent you. Read ones stay here so you can come back to them.',
          pose: 'point-up' }
      ],
      'student/report-card': [
        { sel: '.card, .admin-table, table', title: 'Your report card',
          text: 'Subject by subject for the term, with your general average at the bottom.',
          pose: 'medal' }
      ],
      'student/learner-development-report': [
        { sel: '.card, .admin-table, table', title: 'Learner development report',
          text: 'The DepEd report for the period: what you are good at, and what you are working on.',
          pose: 'reading' }
      ],
      'student/platform-rating': [
        { sel: '.card, form, .modal', title: 'Tell us honestly',
          text: 'Say what is working and what is not. It goes straight to the people who can fix it.',
          pose: 'star' }
      ],
      'student/analytics': [
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'How you are doing',
          text: 'Your averages and your attendance rate next to how they looked last term, so you can see which way you are moving.',
          pose: 'star' }
      ],

      // Teacher pages that had no tour. Without these, opening a teacher's own
      // page showed the generic three-step tour about the sidebar.
      'teacher/students': [
        { sel: '.app-sidebar, .card, .admin-table', title: 'Your learners',
          text: 'Everyone in the sections assigned to you. Search a name to jump straight to one of them.',
          pose: 'search' }
      ],
      'teacher/analytics': [
        { sel: '.admin-stat, .stats-card, .stat-tile, .card', title: 'Class analytics',
          text: 'Averages, attendance rate and term trends for each of your sections.',
          pose: 'chart' }
      ],
      'teacher/announcements': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Announcements',
          text: 'News from the school office and your fellow teachers. Open one to see who it was sent to.',
          pose: 'megaphone' }
      ],
      'teacher/materials': [
        { sel: '.admin-table, .data-table, table, .card', title: 'Shared materials',
          text: 'The files the school has shared with your classes. Anything you upload for a section shows up here.',
          pose: 'reading' }
      ],
      'teacher/platform-rating': [
        { sel: '.card, form, .modal', title: 'Tell us honestly',
          text: 'Say what is working and what is not. It goes straight to the people who can fix it.',
          pose: 'star' }
      ],
      'teacher/profile': [
        { sel: '.card, form', title: 'Your details',
          text: 'Your name, your contact details and your password. Nothing here is shared with anyone else.',
          pose: 'bust' }
      ],
      'notifications': [
        { sel: '.app-content, main, .card', title: 'Notifications',
          text: 'Anything new addressed to you. Read ones stay here so you can come back to them.',
          pose: 'point-up' }
      ],
      'profile': [
        { sel: '.card, form', title: 'Your profile',
          text: 'Keep your name, contact details and password up to date.',
          pose: 'bust' }
      ]
    };
  }

  /**
   * Fallback used when a page has no specific list.
   *
   * Only the first step points at the menu, because the sidebar really is what
   * that step is describing. The last step used to be point-right as well, so
   * every page without its own tour showed a pointing hand twice - which made
   * it the single most repeated pose in the app.
   */
  var GENERIC = [
    { sel: '.app-sidebar', title: 'Welcome to CSCS Tap n Track',
      text: 'Use the menu on the left to move around. Everything you need is grouped there.',
      pose: 'point-right' },
    { sel: '.top-bar-actions', title: 'Your tools',
      text: 'Notifications and your profile are always in the top bar.',
      pose: 'point-up' },
    { sel: 'main, .app-content, .content, .card', title: 'And I am always here',
      text: 'Click Tappy in the corner of any page and ask him to show you around again.',
      pose: 'hero' }
  ];

  var state = { steps: [], index: 0, opener: null, root: null, scrim: null, card: null };

  function path() {
    return (window.location.pathname || '').replace(/\/+$/, '').replace(/^\/+/, '');
  }

  /**
   * The route key for the current page: the first two path segments.
   *
   * Deliberately the same rule as mascot_segment_key() on the PHP side, so the
   * tour and the floating dock always agree about which page this is.
   *
   * It used to match the FULL pathname, which meant the 30-odd tours below only
   * worked on those exact URLs. Every sub-page fell through to GENERIC:
   * `admin/schedules` did not match the declared `admin/schedule`, and
   * `admin/students/pending` or `admin/students/view/12` did not match
   * `admin/students` at all - so opening a single learner showed the generic
   * three-step tour about the sidebar instead of anything about that page.
   *
   * The base path is stripped first. App::$baseURL is auto-detected, so a site
   * mounted at /portal would otherwise fail to match anything at all.
   */
  function routeKey() {
    var full = path();
    var body = document.body;
    var base = body && body.getAttribute ? (body.getAttribute('data-mascot-basepath') || '') : '';

    if (base && full.indexOf(base) === 0) {
      full = full.slice(base.length);
    }

    var parts = full.replace(/^\/+/, '').split('/');
    if (parts[0] === '') {
      return '';
    }

    return parts.length > 1 ? parts[0] + '/' + parts[1] : parts[0];
  }

  /**
   * Which tour the visitor gets.
   *
   * The layout states the role on <body data-mascot-role>; the class check is
   * only a fallback so the tour still behaves if that attribute is ever lost.
   * 'student' and 'staff' are the same names mascot_tour_played() uses on the
   * PHP side, so the server and the browser agree on one key.
   */
  function role() {
    var body = document.body;
    if (!body) { return 'staff'; }

    var declared = body.getAttribute ? body.getAttribute('data-mascot-role') : null;
    if (declared === 'student' || declared === 'staff') { return declared; }

    return body.className.indexOf('dashboard-app') !== -1 ? 'staff' : 'public';
  }

  /**
   * Keyed by ROLE ONLY, never by path.
   *
   * The first release appended the current path, so "seen" was recorded per
   * page: the tour was dismissed on one dashboard and then popped open again on
   * the next one. One automatic tour per role is what was actually wanted —
   * replay stays available through __startMascotTour().
   */
  function tourKey() {
    return STORAGE_KEY + '.' + role();
  }

  function alreadySeen() {
    var key = tourKey();

    try {
      if (window.localStorage.getItem(key) === 'done') { return true; }
    } catch (e) { /* private mode / storage disabled */ }

    // sessionStorage still works when localStorage is blocked, which stops the
    // tour re-opening on every navigation for those visitors too.
    try { return window.sessionStorage.getItem(key) === 'done'; } catch (e) { return false; }
  }

  function markSeen() {
    var key = tourKey();

    try { window.localStorage.setItem(key, 'done'); } catch (e) { /* private mode */ }
    try { window.sessionStorage.setItem(key, 'done'); } catch (e) { /* ignore */ }
  }

  /**
   * Remove the old per-path keys (mascot.tour.v1.staff.admin/dashboard) the
   * previous release left behind in every returning visitor's browser. They are
   * dead weight, and leaving them would keep the old shape around for ever.
   */
  function clearLegacyKeys() {
    var legacyPrefix = tourKey() + '.';
    var store;
    var keys;

    try { store = window.localStorage; } catch (e) { return; }
    try { keys = Object.keys(store); } catch (e) { return; }

    keys.forEach(function (k) {
      if (k.indexOf(legacyPrefix) === 0) { store.removeItem(k); }
    });
  }

  function clearSeen() {
    [window.localStorage, window.sessionStorage].forEach(function (store) {
      try {
        Object.keys(store)
          .filter(function (k) { return k.indexOf(STORAGE_KEY) === 0; })
          .forEach(function (k) { store.removeItem(k); });
      } catch (e) { /* ignore */ }
    });
  }

  function mascotHtml(pose) {
    var name = pose || FALLBACK_POSE;
    // data-mascot-base, set by the layout, because the URL cannot be trusted: the
    // "strip the last segment" guess produced /admin/assets/mascot/ on a page like
    // /admin/teachers, and every tour step opened on a broken image.
    var host = document.querySelector('[data-mascot-base]');
    var base = (host && host.getAttribute('data-mascot-base')) || '';

    if (!base) {
      var a = document.createElement('a');
      a.href = window.location.href;
      base = a.href.replace(/\/[^/]*$/, '') + '/assets/mascot/';
    }

    // The size goes on the wrapper, which is where mascot.css reads it from. It
    // used to land on the <img>, where it did nothing, and the card sized the
    // artwork itself. var(--mascot-tour) keeps it responsive.
    return '<span class="mascot mascot--' + name + ' mascot--pop" ' +
      'style="--mascot-size:var(--mascot-tour)">' +
      '<img src="' + base + name + '.png" alt="" aria-hidden="true" ' +
      'loading="lazy" decoding="async"></span>';
  }

  function build() {
    var root = document.createElement('div');
    root.className = 'mascot-tour';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Guided tour');
    root.innerHTML =
      '<div class="mascot-tour__scrim"></div>' +
      '<div class="mascot-tour__card">' +
        '<div class="mascot-tour__head"><span data-tour-mascot></span>' +
          '<h2 class="mascot-tour__title" data-tour-title></h2></div>' +
        '<p class="mascot-tour__text" data-tour-text></p>' +
        '<div class="mascot-tour__foot">' +
          '<span class="mascot-tour__dots" data-tour-dots role="presentation"></span>' +
          '<span class="mascot-tour__actions">' +
            '<button type="button" class="btn btn-sm btn-link mascot-tour__skip" data-tour-skip>Skip</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-tour-prev>Back</button>' +
            '<button type="button" class="btn btn-sm btn-primary" data-tour-next>Next</button>' +
          '</span>' +
        '</div>' +
      '</div>';

    document.body.appendChild(root);

    state.root = root;
    state.scrim = root.querySelector('.mascot-tour__scrim');
    state.card = root.querySelector('.mascot-tour__card');

    root.querySelector('[data-tour-next]').addEventListener('click', next);
    root.querySelector('[data-tour-prev]').addEventListener('click', prev);
    root.querySelector('[data-tour-skip]').addEventListener('click', function () {
      markSeen();
      stop();
    });
  }

  function onKey(e) {
    if (e.key === 'Escape') { markSeen(); stop(); return; }
    if (e.key !== 'Tab') return;

    // Trap focus inside the card while the tour is open.
    var focusables = state.card.querySelectorAll('button:not([disabled])');
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function stepsForCurrentPage() {
    var list = steps()[routeKey()] || GENERIC;
    return list.filter(function (step) {
      return document.querySelector(step.sel) !== null;
    });
  }

  function start(force) {
    if (state.root) { state.root.classList.add('is-open'); position(); return; }
    if (!force && alreadySeen()) return;

    var found = stepsForCurrentPage();
    if (!found.length) { markSeen(); return; }

    // An automatic tour counts as seen the moment it opens. Recording it only on
    // exit meant that closing the tab half way through replayed it for ever.
    if (!force) { markSeen(); }

    state.steps = found;
    state.index = 0;
    state.opener = document.activeElement;

    if (!state.root) build();
    state.root.classList.add('is-open');
    document.addEventListener('keydown', onKey, true);
    render();
  }

  function stop() {
    if (state.root) state.root.classList.remove('is-open');
    document.removeEventListener('keydown', onKey, true);
    if (state.opener && state.opener.focus) {
      try { state.opener.focus(); } catch (e) { /* ignore */ }
    }
  }

  function next() {
    if (state.index >= state.steps.length - 1) { markSeen(); stop(); return; }
    state.index += 1;
    render();
  }

  function prev() {
    if (state.index <= 0) return;
    state.index -= 1;
    render();
  }

  function render() {
    var step = state.steps[state.index];
    if (!step) { markSeen(); stop(); return; }

    state.card.querySelector('[data-tour-title]').textContent = step.title;
    state.card.querySelector('[data-tour-text]').textContent = step.text;
    state.card.querySelector('[data-tour-prev]').disabled = state.index === 0;
    state.card.querySelector('[data-tour-next]').textContent =
      state.index === state.steps.length - 1 ? 'Done' : 'Next';

    var holder = state.card.querySelector('[data-tour-mascot]');
    holder.innerHTML = mascotHtml(step.pose);
    hideIfBroken(holder);
    renderDots();
    position();
  }

  /**
   * Empty the mascot slot if the artwork failed to load.
   *
   * mascot.js already does this for alerts and dialogs. The tour builds its markup
   * by hand, so it has to do it too - otherwise a missing file leaves the browser's
   * broken-image glyph sitting in the middle of the card, which is exactly what
   * this whole system is built to avoid.
   */
  function hideIfBroken(holder) {
    if (!holder) return;

    var img = holder.querySelector('img');
    if (!img) return;

    // A cached image can already have failed before the listener was attached.
    if (img.complete) {
      if (img.naturalWidth === 0) { holder.innerHTML = ''; }
      return;
    }

    img.addEventListener('error', function () { holder.innerHTML = ''; });
  }

  /**
   * One dot per step, with the current one stretched into a bar.
   *
   * Rebuilt rather than toggled because the step list is filtered per page, so its
   * length is only known here.
   */
  function renderDots() {
    var host = state.card.querySelector('[data-tour-dots]');
    if (!host) return;

    var html = '';
    for (var i = 0; i < state.steps.length; i++) {
      html += '<span class="mascot-tour__dot' + (i === state.index ? ' is-current' : '') + '"></span>';
    }
    host.innerHTML = html;
    host.setAttribute('aria-label', 'Step ' + (state.index + 1) + ' of ' + state.steps.length);
  }

  function position() {
    var step = state.steps[state.index];
    if (!step) return;

    var target = document.querySelector(step.sel);
    var card = state.card;
    var scrim = state.scrim;
    var pad = 8;

    if (!target) {
      scrim.style.boxShadow = '0 0 0 9999px rgba(15,23,42,.55)';
      card.style.top = '50%';
      card.style.left = '50%';
      card.style.transform = 'translate(-50%, -50%)';
      return;
    }

    var r = target.getBoundingClientRect();

    // Cut the scrim out around the target so the thing being explained stays
    // readable inside the spotlight.
    scrim.style.top = (r.top - pad) + 'px';
    scrim.style.left = (r.left - pad) + 'px';
    scrim.style.width = (r.width + pad * 2) + 'px';
    scrim.style.height = (r.height + pad * 2) + 'px';
    scrim.style.boxShadow = '0 0 0 9999px rgba(15,23,42,.55)';

    card.style.transform = 'none';

    var ch = card.offsetHeight || 200;
    var cw = card.offsetWidth || 340;
    var below = r.bottom + 14;
    var top = below + ch < window.innerHeight ? below : Math.max(12, r.top - ch - 14);

    var left = r.left;
    if (left + cw > window.innerWidth - 12) left = window.innerWidth - cw - 12;
    if (left < 12) left = 12;

    card.style.top = top + 'px';
    card.style.left = left + 'px';
  }

  window.__startMascotTour = function () { start(true); };
  window.__resetMascotTour = clearSeen;

  function boot() {
    clearLegacyKeys();

    // Let the page settle before measuring targets.
    window.setTimeout(function () {
      if (!document.body || document.body.className.indexOf('dashboard-app') === -1) return;

      // The first-login welcome modal is the front door now, so the automatic
      // tour waits for it. Without this the tour would open *behind* the modal
      // and record itself as seen at the same moment, which meant the user never
      // got a tour at all.
      var welcome = document.querySelector('[data-mascot-welcome]');
      if (welcome) {
        if (welcome.dataset.mascotWelcomeDismissed === '1') {
          start(false);
        } else {
          // The modal script clears the dataset flag once it has closed, either
          // way, and then pokes us. If it never loads, do not leave the tour
          // permanently unstarted.
          var waited = 0;
          var poll = window.setInterval(function () {
            waited += 300;
            if (welcome.dataset.mascotWelcomeDismissed === '1') {
              window.clearInterval(poll);
              start(false);
            } else if (waited > 60000) {
              window.clearInterval(poll);
            }
          }, 300);
        }
        return;
      }

      start(false);
    }, 700);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('resize', function () {
    if (state.root && state.root.classList.contains('is-open')) position();
  });
})();

