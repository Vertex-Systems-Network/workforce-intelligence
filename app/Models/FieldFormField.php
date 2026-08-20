<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field form field behavior within the WorkIntel application. */ class FieldFormField extends Model{protected $fillable=['field_form_template_id','key','label','type','required','options','validation','position'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['required'=>'boolean','options'=>'array','validation'=>'array'];}}
