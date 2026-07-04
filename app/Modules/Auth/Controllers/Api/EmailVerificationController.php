<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function resend(Request $request): JsonResponse
    {
        abort_unless(config('project.features.email_verification', false), 403, 'Email verification is disabled.');

        if ($request->user()->hasVerifiedEmail()) {
            return $this->successResponse(null, 'Email is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse(null, 'Verification email sent.');
    }

    /**
     * Target of the signed URL in the verification email. Named
     * verification.verify (see Routes/api.php) so Laravel's VerifyEmail
     * notification builds the link automatically.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        abort_unless(config('project.features.email_verification', false), 403, 'Email verification is disabled.');

        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->successResponse(null, 'Email verified successfully.');
    }
}
