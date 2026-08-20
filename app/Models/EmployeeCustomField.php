<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides employee custom field behavior within the WorkIntel application. */ class EmployeeCustomField extends Model { protected $fillable=['uuid','workspace_id','label','key','field_type','options','visibility','required','active','sort_order']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['options'=>'array','required'=>'boolean','active'=>'boolean'];} /** Handles the values operation for the current WorkIntel workflow. */ public function values():HasMany{return $this->hasMany(EmployeeCustomValue::class,'custom_field_id');} }
