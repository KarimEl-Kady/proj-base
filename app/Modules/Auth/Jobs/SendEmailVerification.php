<?php

namespace App\Modules\Auth\Jobs;

use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public User $user)
    {
        $this->onQueue((string) config('project.events.lanes.notifications', 'notifications'));
    }

    public function handle(): void
    {
        if (config('project.features.email_verification', false) && ! $this->user->hasVerifiedEmail()) {
            $this->user->sendEmailVerificationNotification();
        }
    }
}
