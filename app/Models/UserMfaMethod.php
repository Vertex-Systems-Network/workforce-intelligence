<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides user mfa method behavior within the WorkIntel application. */ class UserMfaMethod extends Model{protected $fillable=['user_id','type','secret_encrypted','recovery_code_hashes','confirmed_at','last_used_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['recovery_code_hashes'=>'array','confirmed_at'=>'datetime','last_used_at'=>'datetime'];}}
