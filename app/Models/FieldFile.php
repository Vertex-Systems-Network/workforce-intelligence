<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field file behavior within the WorkIntel application. */ class FieldFile extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','field_work_order_id','incident_id','member_id','kind','file_path','file_name','mime_type','size_bytes','sha256','caption','captured_at','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['captured_at'=>'datetime','created_at'=>'datetime'];}}
