<?php
namespace App\Notifications;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
/** Provides work intel password reset notification behavior within the WorkIntel application. */ class WorkIntelPasswordResetNotification extends ResetPassword
{
 /** Handles the to mail operation for the current WorkIntel workflow. */ public function toMail($notifiable):MailMessage{$url=url('/reset-password?token='.rawurlencode($this->token).'&email='.rawurlencode($notifiable->getEmailForPasswordReset()));return (new MailMessage)->subject(__('messages.reset_subject'))->greeting(__('messages.reset_greeting'))->line(__('messages.reset_line'))->action(__('messages.reset_action'),$url)->line(__('messages.reset_ignore'));}
}
