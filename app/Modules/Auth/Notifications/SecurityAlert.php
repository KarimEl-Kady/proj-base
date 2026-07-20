<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->message)
            ->line('If you did not make this change, contact support immediately.');
    }
}
