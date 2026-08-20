<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;/** Provides chat message pin behavior within the WorkIntel application. */ class ChatMessagePin extends Model{public $timestamps=false;protected $fillable=['conversation_id','message_id','pinned_by_member_id','created_at'];}
