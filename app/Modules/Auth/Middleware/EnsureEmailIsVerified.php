<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature-flag-aware email verification gate, aliased as `verified.feature`
 * (registered in AuthServiceProvider). Put it on any route that should
 * require a verified email *when the project enforces verification*:
 *
 *     Route::middleware(['auth:sanctum', 'verified.feature'])->...
 *
 * A pass-through while PROJECT_FEATURE_EMAIL_VERIFICATION is off, so
 * modules can annotate routes once and the flag flips enforcement globally
 * — this makes the base's posture explicit: verification emails are
 * informational until a route opts in with this middleware.
 *
 * Differs from Laravel's built-in `verified` middleware only in being
 * feature-flag aware and returning a JSON-envelope 403 instead of
 * redirecting to a (non-existent, API-first) verification notice route.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('project.features.email_verification', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            abort(403, 'Your email address is not verified.');
        }

        return $next($request);
    }
}
