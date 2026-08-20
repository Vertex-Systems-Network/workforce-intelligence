<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
/** Provides workspace alert mail behavior within the WorkIntel application. */ class WorkspaceAlertMail extends Notification
{
    use Queueable;
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $title,private readonly ?string $body=null,private readonly string $workspaceName='WorkIntel'){}
    /** Handles the via operation for the current WorkIntel workflow. */ public function via(object $notifiable): array { return ['mail']; }
    /** Handles the to mail operation for the current WorkIntel workflow. */ public function toMail(object $notifiable): MailMessage { $mail=(new MailMessage)->subject($this->workspaceName.' — '.$this->title)->greeting($this->title);if($this->body)$mail->line($this->body);return $mail->line(__('messages.alert_footer')); }
}
