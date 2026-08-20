<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;use Illuminate\Notifications\Messages\MailMessage;use Illuminate\Notifications\Notification;
/** Provides workspace digest mail behavior within the WorkIntel application. */ class WorkspaceDigestMail extends Notification
{
 use Queueable;/** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $workspaceName,private readonly string $frequency,private readonly array $items){}/** Handles the via operation for the current WorkIntel workflow. */ public function via(object $notifiable):array{return ['mail'];}
 /** Handles the to mail operation for the current WorkIntel workflow. */ public function toMail(object $notifiable): MailMessage { $frequency=ucfirst($this->frequency);$mail=(new MailMessage)->subject(__('messages.digest_subject',['workspace'=>$this->workspaceName,'frequency'=>$frequency]))->greeting(__('messages.digest_greeting',['frequency'=>$frequency]));foreach(array_slice($this->items,0,30) as $item)$mail->line('• '.$item['title'].($item['body']?' — '.$item['body']:''));return $mail->line(__('messages.digest_count',['count'=>count($this->items)])); }
}
