<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('api/v1/auth/password')]
#[Middleware(['api', 'throttle:6,1'])]
class PasswordResetController extends Controller
{
    #[Post('/forgot', name: 'api.auth.password.forgot')]
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        // Always the same response — don't leak which emails exist.
        return $this->jsonResponse(null, 'If the email exists, a reset link has been sent.');
    }

    #[Post('/reset', name: 'api.auth.password.reset')]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, string $password) {
                $user->password = $password;
                $user->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            return $this->jsonError(__($status), 422);
        }

        return $this->jsonResponse(null, 'Password reset successfully.');
    }
}
