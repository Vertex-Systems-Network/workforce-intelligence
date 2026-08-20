<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides data governance policy behavior within the WorkIntel application. */ class DataGovernancePolicy extends Model{protected $fillable=['uuid','workspace_id','dataset','retention_days','residency_region','storage_class','deletion_mode','legal_hold','settings'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['legal_hold'=>'boolean','settings'=>'array'];}}
