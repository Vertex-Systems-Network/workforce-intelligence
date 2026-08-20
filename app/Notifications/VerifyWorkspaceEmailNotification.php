<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;use Illuminate\Notifications\Messages\MailMessage;use Illuminate\Notifications\Notification;
/** Provides verify workspace email notification behavior within the WorkIntel application. */ class VerifyWorkspaceEmailNotification extends Notification
{
 use Queueable;/** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $verifyUrl){}/** Handles the via operation for the current WorkIntel workflow. */ public function via(object $notifiable):array{return ['mail'];}
 /** Handles the to mail operation for the current WorkIntel workflow. */ public function toMail(object $notifiable):MailMessage{return (new MailMessage)->subject(__('messages.verify_subject'))->greeting(__('messages.verify_greeting'))->line(__('messages.verify_line'))->action(__('messages.verify_action'),$this->verifyUrl)->line(__('messages.verify_expiry'));}
}
