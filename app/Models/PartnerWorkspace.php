<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides partner workspace behavior within the WorkIntel application. */ class PartnerWorkspace extends Model{protected $fillable=['partner_account_id','workspace_id','relationship_type','external_reference','status'];/** Handles the account operation for the current WorkIntel workflow. */ public function account():BelongsTo{return $this->belongsTo(PartnerAccount::class,'partner_account_id');}/** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}}
