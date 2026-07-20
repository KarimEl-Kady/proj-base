<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\ChangeEmailRequest;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AccountSecurityController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function changeEmail(ChangeEmailRequest $request): JsonResponse
    {
        $this->authService->changeEmail(
            $request->user(),
            $request->validated('email'),
            $request->validated('current_password'),
            $request->validated('code'),
        );

        return $this->successResponse(null, 'Email changed. Sign in again to continue.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request->validated('code'),
        );

        return $this->successResponse(null, 'Password changed. Sign in again to continue.');
    }
}
