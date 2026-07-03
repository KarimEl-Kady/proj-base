<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Requests\CreateTokenRequest;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * Named personal access tokens (PROJECT_FEATURE_API_TOKENS) — for
 * integrations/CLI use, separate from the login token flow.
 */
#[Prefix('api/v1/auth/tokens')]
#[Middleware(['api', 'auth:sanctum'])]
class TokenController extends Controller
{
    #[Get('/', name: 'api.auth.tokens.index')]
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $tokens = $request->user()->tokens()
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return $this->jsonResponse($tokens, 'Tokens retrieved successfully.');
    }

    #[Post('/', name: 'api.auth.tokens.store')]
    public function store(CreateTokenRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $token = $request->user()->createToken($request->validated('name'));

        return $this->jsonResponse([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Token created successfully. Store it now — it will not be shown again.', 201);
    }

    #[Delete('/{id}', name: 'api.auth.tokens.destroy')]
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
