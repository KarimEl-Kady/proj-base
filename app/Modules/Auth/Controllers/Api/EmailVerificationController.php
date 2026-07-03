<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('api/v1/auth/email')]
#[Middleware('api')]
class EmailVerificationController extends Controller
{
    #[Post('/resend', name: 'api.auth.email.resend', middleware: ['auth:sanctum', 'throttle:6,1'])]
    public function resend(Request $request): JsonResponse
    {
        abort_unless(config('project.features.email_verification', false), 403, 'Email verification is disabled.');

        if ($request->user()->hasVerifiedEmail()) {
            return $this->jsonResponse(null, 'Email is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->jsonResponse(null, 'Verification email sent.');
    }

    /**
     * Target of the signed URL in the verification email. Named
     * verification.verify so Laravel's VerifyEmail notification builds
     * the link automatically.
     */
    #[Get('/verify/{id}/{hash}', name: 'verification.verify', middleware: ['signed', 'throttle:6,1'])]
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        abort_unless(config('project.features.email_verification', false), 403, 'Email verification is disabled.');

        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->jsonResponse(null, 'Email verified successfully.');
    }
}
