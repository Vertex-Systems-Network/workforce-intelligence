<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides employee lifecycle checklist behavior within the WorkIntel application. */ class EmployeeLifecycleChecklist extends Model { protected $fillable=['uuid','workspace_id','member_id','template_id','type','name','effective_date','status','completed_at','created_by']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['effective_date'=>'date','completed_at'=>'datetime'];} /** Handles the items operation for the current WorkIntel workflow. */ public function items():HasMany{return $this->hasMany(EmployeeLifecycleChecklistItem::class,'checklist_id')->orderBy('sort_order');} }
