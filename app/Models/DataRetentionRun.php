<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides data retention run behavior within the WorkIntel application. */ class DataRetentionRun extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','dataset','started_at','completed_at','candidate_count','deleted_count','status','error','metadata'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['started_at'=>'datetime','completed_at'=>'datetime','metadata'=>'array'];}}
