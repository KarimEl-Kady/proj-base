<?php

namespace App\Modules\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline clickjacking/MIME-sniffing protection for the "web" group (and
 * therefore the dashboard) — see config/project.php "Security Headers".
 * Not applied to "api": JSON responses to non-browser clients don't need it.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = config('project.security.csp');

        if (is_string($csp) && $csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
