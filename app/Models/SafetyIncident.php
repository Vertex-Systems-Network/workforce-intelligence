<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides safety incident behavior within the WorkIntel application. */ class SafetyIncident extends Model{protected $fillable=['uuid','workspace_id','reporter_member_id','field_work_order_id','incident_number','type','severity','status','title','description','occurred_at','latitude','longitude','immediate_action','resolution','reviewed_by','reviewed_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['occurred_at'=>'datetime','reviewed_at'=>'datetime'];}}
