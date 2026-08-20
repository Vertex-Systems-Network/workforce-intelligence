<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides addon usage event behavior within the WorkIntel application. */ class AddonUsageEvent extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','workspace_addon_id','metric','quantity','idempotency_key','occurred_at','metadata','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['quantity'=>'decimal:4','occurred_at'=>'datetime','metadata'=>'array','created_at'=>'datetime'];}/** Handles the subscription operation for the current WorkIntel workflow. */ public function subscription():BelongsTo{return $this->belongsTo(WorkspaceAddon::class,'workspace_addon_id');}}
