<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatusChangedNotification extends Notification
{
    public function __construct(
        public Report $report,
        public string $oldStatus,
        public string $newStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabels = [
            'received'   => 'Received',
            'reviewing'  => 'Under Review',
            'clarification' => 'Clarification Needed',
            'closed'     => 'Closed',
        ];

        return (new MailMessage)
            ->subject('Status Update on Your Report — ' . $this->report->reference_number)
            ->greeting('Hello,')
            ->line('The status of your report has been updated.')
            ->line('**Reference:** ' . $this->report->reference_number)
            ->line('**Previous status:** ' . ($statusLabels[$this->oldStatus] ?? $this->oldStatus))
            ->line('**New status:** ' . ($statusLabels[$this->newStatus] ?? $this->newStatus))
            ->line('Please log in to the portal for more details.')
            ->salutation('Hinweisgeberporal Team');
    }
}
