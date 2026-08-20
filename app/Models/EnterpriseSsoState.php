<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides enterprise sso state behavior within the WorkIntel application. */ class EnterpriseSsoState extends Model{public $timestamps=false;protected $fillable=['state_hash','enterprise_identity_provider_id','code_verifier_encrypted','nonce','redirect_uri','expires_at','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['expires_at'=>'datetime','created_at'=>'datetime'];}}
