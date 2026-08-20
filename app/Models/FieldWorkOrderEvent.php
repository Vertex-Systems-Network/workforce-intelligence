<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field work order event behavior within the WorkIntel application. */ class FieldWorkOrderEvent extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','field_work_order_id','member_id','event_type','note','latitude','longitude','accuracy_meters','metadata','occurred_at','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['metadata'=>'array','occurred_at'=>'datetime','created_at'=>'datetime'];}}
