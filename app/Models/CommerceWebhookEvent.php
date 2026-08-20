<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides commerce webhook event behavior within the WorkIntel application. */ class CommerceWebhookEvent extends Model{public $timestamps=false;protected $fillable=['provider','event_id','event_type','payload_hash','status','processed_at','error_message','metadata','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['processed_at'=>'datetime','created_at'=>'datetime','metadata'=>'array'];}}
