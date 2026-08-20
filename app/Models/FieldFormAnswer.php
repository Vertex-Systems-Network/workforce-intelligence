<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field form answer behavior within the WorkIntel application. */ class FieldFormAnswer extends Model{protected $fillable=['field_form_submission_id','field_form_field_id','value'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['value'=>'array'];}}
