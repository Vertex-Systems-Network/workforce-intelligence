<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides policy acknowledgement behavior within the WorkIntel application. */ class PolicyAcknowledgement extends Model { protected $fillable=['uuid','workspace_id','policy_id','member_id','signed_name','acknowledged_at','ip_address','user_agent','content_hash']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['acknowledged_at'=>'datetime'];} }
