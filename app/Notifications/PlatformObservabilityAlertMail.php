<?php

namespace App\Notifications;

use App\Models\SystemObservabilityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Delivers one platform observability alert to configured platform operators. */
class PlatformObservabilityAlertMail extends Notification
{
    use Queueable;

    /** Create an alert mail notification from a persisted incident. */
    public function __construct(private readonly SystemObservabilityAlert $alert) {}

    /** Deliver observability alerts by email. */
    public function via(object $notifiable): array { return ['mail']; }

    /** Build the privacy-safe alert email. */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('WorkIntel Observability — '.$this->alert->title)->greeting($this->alert->title)->line($this->alert->message)->line('Severity: '.strtoupper($this->alert->severity))->line('Triggered: '.$this->alert->triggered_at?->toIso8601String());
    }
}
