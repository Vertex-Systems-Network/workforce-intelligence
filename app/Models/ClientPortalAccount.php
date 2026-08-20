<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
/** Provides client portal account behavior within the WorkIntel application. */ class ClientPortalAccount extends Model
{
    protected $fillable=['workspace_id','client_id','name','email','password','status','activated_at','last_login_at'];
    protected $hidden=['password'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['password'=>'hashed','activated_at'=>'datetime','last_login_at'=>'datetime']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Handles the tokens operation for the current WorkIntel workflow. */ public function tokens(): HasMany { return $this->hasMany(ClientPortalToken::class); }
}
