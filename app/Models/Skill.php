<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides skill behavior within the WorkIntel application. */ class Skill extends Model{protected $table='skill_catalog';protected $fillable=['uuid','workspace_id','name','category','description','max_proficiency','active'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['active'=>'boolean'];}}
