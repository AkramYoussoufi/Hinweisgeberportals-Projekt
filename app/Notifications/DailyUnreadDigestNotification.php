<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyUnreadDigestNotification extends Notification
{
    public function __construct(public array $reports) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Daily Digest — Unread Messages Awaiting Your Response')
            ->greeting('Good morning,')
            ->line('The following reports have unread messages that require your attention:');

        foreach ($this->reports as $report) {
            $mail->line('---')
                ->line('**Reference:** ' . $report['reference_number'])
                ->line('**Subject:** ' . $report['subject'])
                ->line('**Unread messages:** ' . $report['unread_count']);
        }

        $mail->line('---')
            ->line('Please log in to the admin panel to respond.')
            ->salutation('Hinweisgeberporal Team');

        return $mail;
    }
}
