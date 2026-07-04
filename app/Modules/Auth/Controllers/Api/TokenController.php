<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\CreateTokenRequest;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Named personal access tokens (PROJECT_FEATURE_API_TOKENS) — for
 * integrations/CLI use, separate from the login token flow.
 */
class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $tokens = $request->user()->tokens()
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return $this->jsonResponse($tokens, 'Tokens retrieved successfully.');
    }

    public function store(CreateTokenRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $token = $request->user()->createToken($request->validated('name'));

        return $this->jsonResponse([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Token created successfully. Store it now — it will not be shown again.', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $request->user()->tokens()->where('id', $id)->delete();

        return $this->jsonResponse(null, 'Token revoked successfully.');
    }

    protected function ensureFeatureEnabled(): void
    {
        abort_unless(config('project.features.api_tokens', false), 403, 'API tokens are disabled.');
    }
}
