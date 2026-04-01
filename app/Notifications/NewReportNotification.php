<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReportNotification extends Notification
{
    public function __construct(public Report $report) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Report Received — ' . $this->report->reference_number)
            ->greeting('Hello,')
            ->line('A new report has been submitted and requires your attention.')
            ->line('**Reference:** ' . $this->report->reference_number)
            ->line('**Category:** ' . ucfirst($this->report->category))
            ->line('**Subject:** ' . $this->report->subject)
            ->line('**Submitted:** ' . $this->report->created_at->format('d.m.Y H:i'))
            ->line('Please log in to the admin panel to review this report.')
            ->salutation('Hinweisgeberporal Team');
    }
}
