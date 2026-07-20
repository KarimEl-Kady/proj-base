<?php

namespace App\Modules\Auth\Jobs;

use App\Modules\Auth\Notifications\SecurityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendSecurityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $email,
        public string $subject,
        public string $message,
    ) {
        $this->onQueue((string) config('project.events.lanes.notifications', 'notifications'));
    }

    public function handle(): void
    {
        Notification::route('mail', $this->email)
            ->notify(new SecurityAlert($this->subject, $this->message));
    }
}
