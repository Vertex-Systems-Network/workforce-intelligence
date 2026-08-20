<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides screenshot storage provider behavior within the WorkIntel application. */ class ScreenshotStorageProvider extends Model
{
    protected $fillable = ['uuid','workspace_id','name','provider_type','enabled','is_primary','fallback_to_local','delete_local_after_sync','root_path','encrypted_config','health_status','consecutive_failures','last_tested_at','last_success_at','last_failure_at','last_error','created_by','updated_by'];
    protected $hidden = ['encrypted_config'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['encrypted_config'=>'encrypted:array','enabled'=>'boolean','is_primary'=>'boolean','fallback_to_local'=>'boolean','delete_local_after_sync'=>'boolean','last_tested_at'=>'datetime','last_success_at'=>'datetime','last_failure_at'=>'datetime']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the jobs operation for the current WorkIntel workflow. */ public function jobs(): HasMany { return $this->hasMany(ScreenshotStorageJob::class, 'storage_provider_id'); }
}
