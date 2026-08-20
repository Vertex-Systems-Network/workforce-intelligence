<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides employee dependent behavior within the WorkIntel application. */ class EmployeeDependent extends Model { protected $fillable=['workspace_id','member_id','name','relationship','date_of_birth','benefits_eligible','notes']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['date_of_birth'=>'date','benefits_eligible'=>'boolean'];} }
