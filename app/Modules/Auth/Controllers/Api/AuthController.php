<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        abort_unless(config('project.features.registration', true), 403, 'Registration is disabled.');

        $result = $this->authService->register($request->validated());

        return $this->successResponse(
            $this->authPayload($result),
            'Registered successfully.',
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('code'),
            $request->validated('device', 'api'),
        );

        return $this->successResponse($this->authPayload($result), 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'Authenticated user retrieved successfully.'
        );
    }

    /**
     * @param  array{user: User, token: ?string}  $result
     */
    protected function authPayload(array $result): array
    {
        $payload = ['user' => new UserResource($result['user'])];

        if ($result['token'] !== null) {
            $payload['token'] = $result['token'];
            $payload['token_type'] = 'Bearer';

            // 0 means "never expires" (see AuthService::issueToken) — report
            // that as null, not 0 seconds, which clients would read as
            // "already expired".
            $expiration = (int) config('project.auth.token_expiration', 1440);
            $payload['expires_in'] = $expiration > 0 ? $expiration * 60 : null;
        }

        return $payload;
    }
}
