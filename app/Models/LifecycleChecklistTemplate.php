<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides lifecycle checklist template behavior within the WorkIntel application. */ class LifecycleChecklistTemplate extends Model { protected $fillable=['uuid','workspace_id','name','type','status','created_by']; /** Handles the items operation for the current WorkIntel workflow. */ public function items():HasMany{return $this->hasMany(LifecycleChecklistTemplateItem::class,'template_id')->orderBy('sort_order');} }
