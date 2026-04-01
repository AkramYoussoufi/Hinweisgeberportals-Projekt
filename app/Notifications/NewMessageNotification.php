<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    public function __construct(public Report $report) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Message on Your Report — ' . $this->report->reference_number)
            ->greeting('Hello,')
            ->line('There is a new message on your report.')
            ->line('**Reference:** ' . $this->report->reference_number)
            ->line('Please log in to the portal to read and respond.')
            ->line('For your privacy, message content is never included in this email.')
            ->salutation('Hinweisgeberporal Team');
    }
}
