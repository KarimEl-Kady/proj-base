<?php

namespace App\Modules\User\Events;

use App\Modules\Core\Events\DomainEvent;
use App\Modules\User\Models\User;

/**
 * Fired from the User model's created hook, so it's true no matter which
 * entry point created the user (UserService CRUD, Auth registration,
 * factories, seeders). Dispatched after the surrounding DB transaction
 * commits (DomainEvent).
 */
class UserCreated extends DomainEvent
{
    public function __construct(
        public User $user,
    ) {
        parent::__construct();
    }
}
