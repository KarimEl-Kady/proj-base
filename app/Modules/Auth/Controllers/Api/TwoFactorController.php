<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Modules\Auth\Jobs\SendSecurityAlert;
use App\Modules\Auth\Requests\ConfirmSensitiveActionRequest;
use App\Modules\Auth\Requests\TwoFactorCodeRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Support\Totp;
use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TwoFactorController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function enable(ConfirmSensitiveActionRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $user = $request->user();
        $this->authService->confirmSensitiveAction(
            $user,
            $request->validated('current_password'),
            $request->validated('code'),
        );

        if ($user->hasTwoFactorEnabled()) {
            return $this->failedResponse('Two-factor authentication is already enabled.', 422);
        }

        $secret = Totp::generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->successResponse([
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user->email),
        ], 'Scan the QR code, then confirm with a code.');
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return $this->failedResponse('Two-factor authentication has not been initiated.', 422);
        }

        if (! Totp::verify($user->two_factor_secret, $request->validated('code'))) {
            return $this->failedResponse('The two-factor authentication code is invalid.', 422);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        SendSecurityAlert::dispatchAfterResponse(
            $user->email,
            'Two-factor authentication was enabled',
            'Two-factor authentication was turned on for your account. If this wasn\'t you, secure your account immediately.',
        );

        return $this->successResponse(null, 'Two-factor authentication enabled.');
    }

    public function disable(TwoFactorCodeRequest $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->failedResponse('Two-factor authentication is not enabled.', 422);
        }

        if (! Totp::verify($user->two_factor_secret, $request->validated('code'))) {
            return $this->failedResponse('The two-factor authentication code is invalid.', 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        SendSecurityAlert::dispatchAfterResponse(
            $user->email,
            'Two-factor authentication was disabled',
            'Two-factor authentication was turned off for your account. If this wasn\'t you, secure your account immediately.',
        );

        return $this->successResponse(null, 'Two-factor authentication disabled.');
    }

    protected function ensureFeatureEnabled(): void
    {
        abort_unless(config('project.features.two_factor_auth', false), 403, 'Two-factor authentication is disabled.');
    }
}
