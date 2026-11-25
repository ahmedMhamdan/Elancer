<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyReplacementEmail extends Notification
{
    public function __construct(public readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Confirm your new Elancer email address'))
            ->line(__('Your current email stays on your account until you confirm this change.'))
            ->action(__('Confirm email change'), $this->url)
            ->line(__('This link expires in one hour.'));
    }
}
