<?php

namespace App\Modules\Auth\Events;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronous registration lifecycle event. It is intentionally not a
 * DomainEvent: role assignment is part of registration and must succeed or
 * roll back in the same transaction.
 */
class UserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public User $user) {}
}
