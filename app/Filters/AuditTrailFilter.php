<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Baseline audit trail + request correlation id.
 *
 * Every state-changing request (POST/PUT/PATCH/DELETE) that is not already
 * covered by an explicit, richer domain event is recorded as `http.request`
 * with its actor, IP, user agent, method, path and HTTP status. Read-only
 * requests are ignored, so the overhead per page view is a single randomness
 * call for the correlation id.
 *
 * The health of the app comes first: any failure here is swallowed (and
 * written to the file log at most).
 */
class AuditTrailFilter implements FilterInterface
{
    public const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Path prefixes that write their own, more detailed audit events.
     * (The verify action of the audit page is logged explicitly.)
     */
    public const SKIP_PREFIXES = ['admin/audit-log'];

    /**
     * Should a request method/path pair be recorded by the trail?
     *
     * Public + static so it can be unit-tested without a request object.
     */
    public static function shouldRecord(string $method, string $path): bool
    {
        if (! in_array(strtoupper($method), self::MUTATING_METHODS, true)) {
            return false;
        }

        $path = strtolower(trim($path, '/'));

        if ($path === '') {
            return false;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            if (! is_cli()) {
                service('auditLogger')->setRequestId(bin2hex(random_bytes(8)));
            }
        } catch (Throwable $e) {
            // Correlation is best-effort.
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            if (is_cli()) {
                return null;
            }

            $logger = service('auditLogger');
            $path   = trim((string) $request->getUri()->getPath(), '/');
            $method = strtoupper((string) $request->getMethod());

            // Support-friendly correlation id on every response.
            $response->setHeader('X-Request-Id', $logger->requestId());

            if (! self::shouldRecord($method, $path)) {
                return null;
            }

            $status = (int) $response->getStatusCode();

            $logger->record([
                'action'      => 'http.request',
                'category'    => 'system',
                'status'      => $status < 400 ? 'success' : 'failure',
                'description' => $method . ' /' . $path . ' → HTTP ' . $status,
                'http_method' => $method,
                'route'       => $path,
                'ip_address'  => (string) $request->getIPAddress(),
                'user_agent'  => (string) $request->getUserAgent()->getAgentString(),
                'metadata'    => ['status_code' => $status],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Audit trail filter failed: ' . $e->getMessage());
        }

        return null;
    }
}
