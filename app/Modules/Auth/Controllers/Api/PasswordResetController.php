<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        // Always the same response — don't leak which emails exist.
        return $this->successResponse(null, 'If the email exists, a reset link has been sent.');
    }

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
            return $this->failedResponse(__($status), 422);
        }

        return $this->successResponse(null, 'Password reset successfully.');
    }
}
