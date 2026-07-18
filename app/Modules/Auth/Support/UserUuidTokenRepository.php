<?php

namespace App\Modules\Auth\Support;

use App\Modules\User\Models\User;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Carbon;
use UnexpectedValueException;

class UserUuidTokenRepository extends DatabaseTokenRepository
{
    public function create(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);
        $token = $this->createNewToken();

        $this->getTable()->insert([
            'user_uuid' => $this->userUuid($user),
            'email' => $user->getEmailForPasswordReset(),
            'token' => $this->hasher->make($token),
            'created_at' => new Carbon,
        ]);

        return $token;
    }

    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->getTable()->where('user_uuid', $this->userUuid($user))->delete();
    }

    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->getTable()
            ->where('user_uuid', $this->userUuid($user))
            ->first();

        return $record
            && ! $this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->getTable()
            ->where('user_uuid', $this->userUuid($user))
            ->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    protected function userUuid(CanResetPasswordContract $user): string
    {
        if (! $user instanceof User || ! is_string($user->uuid) || $user->uuid === '') {
            throw new UnexpectedValueException('Password-reset users must have a persisted UUID.');
        }

        return $user->uuid;
    }
}
