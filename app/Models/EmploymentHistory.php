<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides employment history behavior within the WorkIntel application. */ class EmploymentHistory extends Model { protected $table='employment_history'; protected $fillable=['uuid','workspace_id','member_id','event_type','effective_date','from_value','to_value','note','metadata','recorded_by']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['effective_date'=>'date','metadata'=>'array'];} }
