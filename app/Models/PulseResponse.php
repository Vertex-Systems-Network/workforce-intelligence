<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides pulse response behavior within the WorkIntel application. */ class PulseResponse extends Model{public $timestamps=false;protected $fillable=['workspace_id','pulse_survey_id','pulse_question_id','member_id','rating','response','submitted_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['submitted_at'=>'datetime'];}}
