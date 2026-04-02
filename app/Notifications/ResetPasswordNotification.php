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
        $email = $notifiable->email ?? '';
        $frontendUrl = env('FRONTEND_URL', 'http://127.0.0.1:5500');
        $resetUrl = $frontendUrl . '/reset-password.html'
            . '?token=' . urlencode($this->token)
            . '&email=' . urlencode($email);

        return (new MailMessage)
            ->subject('Password Reset — Hinweisgeberportal')
            ->greeting('Hello,')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
