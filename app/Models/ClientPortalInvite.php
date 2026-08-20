<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides client portal invite behavior within the WorkIntel application. */ class ClientPortalInvite extends Model
{
    protected $fillable=['workspace_id','client_id','created_by','name','email','token_hash','expires_at','accepted_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['expires_at'=>'datetime','accepted_at'=>'datetime']; }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
