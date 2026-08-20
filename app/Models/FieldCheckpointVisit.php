<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field checkpoint visit behavior within the WorkIntel application. */ class FieldCheckpointVisit extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','field_checkpoint_id','member_id','field_work_order_id','scan_method','latitude','longitude','accuracy_meters','within_geofence','visited_at','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['within_geofence'=>'boolean','visited_at'=>'datetime','created_at'=>'datetime'];}}
