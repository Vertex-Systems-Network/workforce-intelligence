<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides plan entitlement behavior within the WorkIntel application. */ class PlanEntitlement extends Model
{
    protected $fillable=['subscription_plan_id','key','value_type','value','label'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['value'=>'array']; }
    /** Handles the plan operation for the current WorkIntel workflow. */ public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id'); }
    /** Returns resolved value data required by the current workflow. */ public function resolvedValue(): mixed { $raw=$this->value['value']??null; return match($this->value_type){'boolean'=>(bool)$raw,'integer'=>(int)$raw,'number'=>(float)$raw,default=>$raw}; }
}
