<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides employee emergency contact behavior within the WorkIntel application. */ class EmployeeEmergencyContact extends Model { protected $fillable=['workspace_id','member_id','name','relationship','phone','alternate_phone','email','is_primary']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['is_primary'=>'boolean'];} }
