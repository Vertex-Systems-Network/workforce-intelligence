<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides cost center behavior within the WorkIntel application. */ class CostCenter extends Model{
 protected $fillable=['uuid','workspace_id','legal_entity_id','business_unit_id','parent_id','manager_member_id','code','name','annual_budget','currency','active'];
 /** Defines attribute casting rules for the model. */ protected function casts():array{return ['annual_budget'=>'decimal:2','active'=>'boolean'];}
 /** Handles the legal entity operation for the current WorkIntel workflow. */ public function legalEntity():BelongsTo{return $this->belongsTo(LegalEntity::class);}
 /** Handles the business unit operation for the current WorkIntel workflow. */ public function businessUnit():BelongsTo{return $this->belongsTo(BusinessUnit::class);}
}
