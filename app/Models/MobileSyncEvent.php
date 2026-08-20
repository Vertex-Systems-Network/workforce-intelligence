<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides mobile sync event behavior within the WorkIntel application. */ class MobileSyncEvent extends Model{public $timestamps=false;protected $fillable=['event_uuid','workspace_id','member_id','event_type','payload','status','error','client_occurred_at','processed_at','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['payload'=>'array','client_occurred_at'=>'datetime','processed_at'=>'datetime','created_at'=>'datetime'];}}
