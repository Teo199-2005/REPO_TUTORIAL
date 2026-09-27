/* Shared styles for the password-requirement indicator rendered by
   Views/partials/password_requirements.php.

   Loaded on every page that asks for a password so the same component looks the
   same everywhere, on both the light admin theme and the dark registration
   card. Colours come from CSS custom properties that each page can override, so
   no per-page copies are needed. */

/* Light theme (admin/dashboard) is the default. */
:root {
    --pw-req-bg: #f8fafc;
    --pw-req-border: #e2e8f0;
    --pw-req-border-left: #2563eb;
    --pw-req-title: #334155;
    --pw-req-text: #475569;
    --pw-req-icon: #16a34a;
}

/* Dark theme (the public registration card). */
.register-card {
    --pw-req-bg: rgba(255, 255, 255, 0.08);
    --pw-req-border: rgba(255, 255, 255, 0.18);
    --pw-req-border-left: #f59e0b;
    --pw-req-title: rgba(255, 255, 255, 0.95);
    --pw-req-text: rgba(255, 255, 255, 0.75);
    --pw-req-icon: #4ade80;
}

.password-requirements {
    display: block;
    margin-top: 0.35rem;
    padding: 0.45rem 0.6rem;
    border: 1px solid var(--pw-req-border, #e2e8f0);
    border-radius: 6px;
    background: var(--pw-req-bg, #f8fafc);
    font-size: 0.8125rem;
    line-height: 1.35;
}

.password-requirements-title {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--pw-req-title, #334155);
    font-weight: 700;
    margin-bottom: 0.2rem;
}

.password-requirements-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.15rem 0.9rem;
}

.password-requirement {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--pw-req-text, #475569);
}

/* Compact variant for tight layouts (reset forms, modals). */
.password-requirements[data-compact="1"] .password-requirements-title {
    display: inline-flex;
    margin-bottom: 0;
    margin-right: 0.4rem;
}

.password-requirements[data-compact="1"] .password-requirements-list {
    display: inline-flex;
}

.password-requirements[data-compact="1"] .password-requirements-title::after {
    content: '';
}

.password-requirement .bi {
    color: var(--pw-req-icon, #16a34a);
    font-size: 0.7rem;
    flex-shrink: 0;
}

/* Live validation feedback: the existing scripts add is-met / is-unmet. */
.password-requirement.is-unmet {
    color: #b91c1c;
}

.password-requirement.is-unmet .bi {
    color: #dc2626;
}
