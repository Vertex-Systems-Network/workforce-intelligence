<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides partner account member behavior within the WorkIntel application. */ class PartnerAccountMember extends Model{protected $fillable=['partner_account_id','user_id','role','status'];/** Handles the account operation for the current WorkIntel workflow. */ public function account():BelongsTo{return $this->belongsTo(PartnerAccount::class,'partner_account_id');}/** Handles the user operation for the current WorkIntel workflow. */ public function user():BelongsTo{return $this->belongsTo(User::class);}}
