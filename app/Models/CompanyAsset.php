<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides company asset behavior within the WorkIntel application. */ class CompanyAsset extends Model { protected $fillable=['uuid','workspace_id','asset_tag','name','category','serial_number','status','purchased_on','purchase_cost','currency','warranty_expires_on','notes']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['purchased_on'=>'date','warranty_expires_on'=>'date','purchase_cost'=>'decimal:2'];} /** Handles the assignments operation for the current WorkIntel workflow. */ public function assignments():HasMany{return $this->hasMany(AssetAssignment::class,'asset_id');} }
