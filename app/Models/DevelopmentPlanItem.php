<?php
namespace App\Models;use App\Casts\DateOnly;use Illuminate\Database\Eloquent\Model;
/** Provides development plan item behavior within the WorkIntel application. */ class DevelopmentPlanItem extends Model{protected $fillable=['development_plan_id','skill_id','title','description','status','due_date','completed_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['due_date'=>DateOnly::class,'completed_at'=>'datetime'];}}
