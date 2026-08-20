<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides scim access token behavior within the WorkIntel application. */ class ScimAccessToken extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','name','token_hash','token_prefix','scopes','last_used_at','last_used_ip','expires_at','revoked_at','created_by','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['scopes'=>'array','last_used_at'=>'datetime','expires_at'=>'datetime','revoked_at'=>'datetime','created_at'=>'datetime'];}}
