<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\CreateTokenRequest;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Named personal access tokens (PROJECT_FEATURE_PERSONAL_ACCESS_TOKENS) —
 * for integrations/CLI use. Deliberately separate from the login flow:
 * login always issues its session token regardless of this flag; the flag
 * only gates creating/listing/revoking *named* tokens here.
 */
class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $tokens = $request->user()->tokens()
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return $this->successResponse($tokens, 'Tokens retrieved successfully.');
    }

    public function store(CreateTokenRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $token = $request->user()->createToken($request->validated('name'));

        return $this->successResponse([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Token created successfully. Store it now — it will not be shown again.', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $request->user()->tokens()->where('id', $id)->delete();

        return $this->successResponse(null, 'Token revoked successfully.');
    }

    protected function ensureFeatureEnabled(): void
    {
        abort_unless(config('project.features.personal_access_tokens', false), 403, 'Personal access tokens are disabled.');
    }
}
