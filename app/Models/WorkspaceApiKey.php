<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides workspace api key behavior within the WorkIntel application. */ class WorkspaceApiKey extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','created_by','name','prefix','token_hash','scopes','last_used_at','last_used_ip','expires_at','revoked_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['scopes'=>'array','last_used_at'=>'datetime','expires_at'=>'datetime','revoked_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    /** Handles the allows operation for the current WorkIntel workflow. */ public function allows(string $scope): bool { $scopes=$this->scopes??[]; return in_array('*',$scopes,true)||in_array($scope,$scopes,true); }
}
