<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Jobs\SendEmailVerification;
use App\Modules\Auth\Jobs\SendSecurityAlert;
use App\Modules\Auth\Support\Totp;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $result = DB::transaction(function () use ($data): array {
            $user = User::query()->create($data);
            UserRegistered::dispatch($user);

            return ['user' => $user, 'token' => $this->issueToken($user)];
        });

        if (config('project.features.email_verification', false)) {
            SendEmailVerification::dispatchAfterResponse($result['user']);
        }

        return $result;
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, ?string $code = null, string $device = 'api'): array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect.'),
            ]);
        }

        $this->verifyTwoFactor($user, $code);

        return ['user' => $user, 'token' => $this->issueToken($user, $device)];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function confirmSensitiveAction(User $user, string $password, ?string $code = null): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The password is incorrect.'),
            ]);
        }

        $this->verifyTwoFactor($user, $code);
    }

    public function changeEmail(User $user, string $email, string $password, ?string $code = null): void
    {
        $this->confirmSensitiveAction($user, $password, $code);
        $oldEmail = $user->email;
        $email = mb_strtolower(trim($email));

        DB::transaction(function () use ($user, $email): void {
            $user->forceFill([
                'email' => $email,
                'email_verified_at' => null,
            ])->save();
            $user->tokens()->delete();
        });

        SendSecurityAlert::dispatchAfterResponse(
            $oldEmail,
            'Your email address was changed',
            "Your account email address was changed to {$email}.",
        );

        if (config('project.features.email_verification', false)) {
            SendEmailVerification::dispatchAfterResponse($user);
        }
    }

    public function changePassword(User $user, string $password, string $newPassword, ?string $code = null): void
    {
        $this->confirmSensitiveAction($user, $password, $code);

        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill(['password' => $newPassword])->save();
            $user->tokens()->delete();
        });

        SendSecurityAlert::dispatchAfterResponse(
            $user->email,
            'Your password was changed',
            'Your account password was changed and existing access tokens were revoked.',
        );
    }

    protected function verifyTwoFactor(User $user, ?string $code): void
    {
        if (! config('project.features.two_factor_auth', false) || ! $user->hasTwoFactorEnabled()) {
            return;
        }

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => __('Two-factor authentication code is required.'),
            ]);
        }

        if (! Totp::verify($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => __('The two-factor authentication code is invalid.'),
            ]);
        }
    }

    protected function issueToken(User $user, string $device = 'api'): string
    {
        $expiration = (int) config('project.auth.token_expiration', 1440);

        return $user->createToken(
            $device,
            ['*'],
            $expiration > 0 ? now()->addMinutes($expiration) : null
        )->plainTextToken;
    }
}
