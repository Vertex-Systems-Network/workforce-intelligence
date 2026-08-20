<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides employee custom value behavior within the WorkIntel application. */ class EmployeeCustomValue extends Model { protected $fillable=['workspace_id','member_id','custom_field_id','value']; }
