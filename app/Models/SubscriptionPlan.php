<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides subscription plan behavior within the WorkIntel application. */ class SubscriptionPlan extends Model
{
    protected $fillable = ['name','slug','description','currency','monthly_price_per_seat','annual_price_per_seat','trial_days','is_active','is_public','is_popular','sort_order'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['monthly_price_per_seat'=>'decimal:2','annual_price_per_seat'=>'decimal:2','is_active'=>'boolean','is_public'=>'boolean','is_popular'=>'boolean']; }
    /** Handles the entitlements operation for the current WorkIntel workflow. */ public function entitlements(): HasMany { return $this->hasMany(PlanEntitlement::class); }
    /** Handles the subscriptions operation for the current WorkIntel workflow. */ public function subscriptions(): HasMany { return $this->hasMany(WorkspaceSubscription::class); }
}
