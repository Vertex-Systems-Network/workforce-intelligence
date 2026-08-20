<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides email verification token behavior within the WorkIntel application. */ class EmailVerificationToken extends Model
{
    public $timestamps=false;
    protected $fillable=['user_id','member_id','token_hash','expires_at','used_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['expires_at'=>'datetime','used_at'=>'datetime','created_at'=>'datetime'];}
    /** Handles the user operation for the current WorkIntel workflow. */ public function user():BelongsTo{return $this->belongsTo(User::class);}
    /** Handles the member operation for the current WorkIntel workflow. */ public function member():BelongsTo{return $this->belongsTo(WorkspaceMember::class);}
}
