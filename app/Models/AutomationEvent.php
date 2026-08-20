<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides automation event behavior within the WorkIntel application. */ class AutomationEvent extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','event_type','source','idempotency_key','payload','occurred_at','processed_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['payload'=>'array','occurred_at'=>'datetime','processed_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(): HasMany { return $this->hasMany(AutomationRun::class); }
}
