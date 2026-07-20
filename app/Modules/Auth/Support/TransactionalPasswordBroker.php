<?php

namespace App\Modules\Auth\Support;

use Closure;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;

class TransactionalPasswordBroker extends PasswordBroker
{
    /**
     * Keep credential mutation, credential revocation, and reset-token
     * consumption in one database transaction.
     */
    public function reset(#[\SensitiveParameter] array $credentials, Closure $callback)
    {
        return DB::transaction(fn () => parent::reset($credentials, $callback));
    }
}
