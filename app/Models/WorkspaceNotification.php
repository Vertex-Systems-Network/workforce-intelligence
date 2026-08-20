<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides workspace notification behavior within the WorkIntel application. */ class WorkspaceNotification extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','user_id','category','type','severity','title','body','data','read_at','email_sent_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['data'=>'array','read_at'=>'datetime','email_sent_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the user operation for the current WorkIntel workflow. */ public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
