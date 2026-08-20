<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides pulse question behavior within the WorkIntel application. */ class PulseQuestion extends Model{protected $fillable=['pulse_survey_id','position','question','question_type','required'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['required'=>'boolean'];}}
