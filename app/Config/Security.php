<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * CSRF Protection Method
     * @var string 'cookie' or 'session'
     */
    public string $csrfProtection = 'session';

    /**
     * CSRF Token Randomization — randomize on each request for added security
     */
    public bool $tokenRandomize = true;

    /**
     * CSRF Token Name
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * CSRF Header Name
     *
     * MUST NOT contain underscores. CodeIgniter parses request headers by
     * converting underscores to dashes (HTTP_CSRF_TEST_NAME -> "Csrf-Test-Name")
     * and looks headers up case-insensitively by the dashed name, so a header
     * named "csrf_test_name" can never be resolved by hasHeader() — any AJAX
     * call that sent the CSRF token only in that header was always rejected
     * with a 403 CSRF error (e.g. "Failed to remove student. Please try again."
     * on the Manage Sections page). The AJAX layer sends the token in the
     * X-CSRF-TOKEN header (and/or inside the request body), which the framework
     * can resolve correctly.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * CSRF Cookie Name (unused with session-based CSRF, kept for compatibility)
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * CSRF Expires (2 hours in seconds)
     */
    public int $expires = 7200;

    /**
     * CSRF Regenerate — regenerate token on every submission.
     *
     * Kept OFF on purpose: this application performs bulk operations (bulk
     * section edits, bulk student approvals, multiple AJAX actions from one page
     * load) that fire several POST requests using the token embedded when the
     * page was rendered. Regenerating on every submission invalidated the token
     * after the first request, so the remaining requests were rejected with a
     * CSRF error — which surfaced as "Failed to update ..." / "Failed to
     * approve ..." messages even though the first request had succeeded.
     *
     * CSRF protection is still fully active (session-stored token, randomized
     * value to mitigate BREACH, 2 hour expiry).
     */
    public bool $regenerate = false;

    /**
     * CSRF Redirect — redirect to previous page with error on failure
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     *
     * Kept ON in every environment: with the previous dev-only setting, a user
     * who sat on a page long enough for its embedded token to go stale (e.g.
     * waiting out a login lockout countdown in another tab that rotated the
     * session) and then submitted got a bare SecurityException #403 stack-trace
     * page instead of a usable form. Redirecting back with an error flash (and
     * a freshly rendered token) keeps the flow recoverable. The login page
     * additionally refreshes its token automatically, see Auth::csrfToken().
     */
    public bool $redirect = true;
}