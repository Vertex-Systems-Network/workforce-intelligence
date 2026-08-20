<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides client portal token behavior within the WorkIntel application. */ class ClientPortalToken extends Model
{
    public $timestamps=false;
    protected $fillable=['client_portal_account_id','token_hash','name','last_used_at','expires_at','revoked_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['last_used_at'=>'datetime','expires_at'=>'datetime','revoked_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the account operation for the current WorkIntel workflow. */ public function account(): BelongsTo { return $this->belongsTo(ClientPortalAccount::class,'client_portal_account_id'); }
}
