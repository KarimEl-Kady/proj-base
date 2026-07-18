<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Support\Totp;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: ?string}
     */
    public function register(array $data): array
    {
        $result = DB::transaction(function () use ($data): array {
            $user = User::query()->create($data);
            UserRegistered::dispatch($user);

            return ['user' => $user, 'token' => $this->issueToken($user)];
        });

        if (config('project.features.email_verification', false)) {
            $result['user']->sendEmailVerificationNotification();
        }

        return $result;
    }

    /**
     * @return array{user: User, token: ?string}
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

        if (config('project.auth.driver', 'session') === 'session') {
            Auth::guard('web')->login($user);

            // New session id on privilege change — prevents session fixation.
            if (request()->hasSession()) {
                request()->session()->regenerate();
            }

            return ['user' => $user, 'token' => null];
        }

        return ['user' => $user, 'token' => $this->issueToken($user, $device)];
    }

    public function logout(User $user): void
    {
        if (config('project.auth.driver', 'session') === 'session') {
            Auth::guard('web')->logout();

            return;
        }

        $user->currentAccessToken()?->delete();
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

    protected function issueToken(User $user, string $device = 'api'): ?string
    {
        if (config('project.auth.driver', 'session') === 'session') {
            return null;
        }

        $expiration = (int) config('project.auth.token_expiration', 1440);

        return $user->createToken(
            $device,
            ['*'],
            $expiration > 0 ? now()->addMinutes($expiration) : null
        )->plainTextToken;
    }
}
