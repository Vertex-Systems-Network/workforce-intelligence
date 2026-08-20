<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides training course behavior within the WorkIntel application. */ class TrainingCourse extends Model{protected $fillable=['uuid','workspace_id','name','provider','description','duration_hours','validity_months','status'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['duration_hours'=>'decimal:2'];}}
