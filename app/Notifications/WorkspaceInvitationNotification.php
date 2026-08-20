<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;use Illuminate\Notifications\Messages\MailMessage;use Illuminate\Notifications\Notification;
/** Provides workspace invitation notification behavior within the WorkIntel application. */ class WorkspaceInvitationNotification extends Notification
{
 use Queueable;/** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $workspaceName,private readonly string $inviteUrl){}/** Handles the via operation for the current WorkIntel workflow. */ public function via(object $notifiable):array{return ['mail'];}
 /** Handles the to mail operation for the current WorkIntel workflow. */ public function toMail(object $notifiable):MailMessage{return (new MailMessage)->subject(__('messages.invite_subject',['workspace'=>$this->workspaceName]))->greeting(__('messages.invite_greeting'))->line(__('messages.invite_line',['workspace'=>$this->workspaceName]))->action(__('messages.invite_action'),$this->inviteUrl)->line(__('messages.invite_ignore'));}
}
