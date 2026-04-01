<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset — Hinweisgeberporal')
            ->greeting('Hello,')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Your password reset token is:')
            ->line('**' . $this->token . '**')
            ->line('This token expires in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
