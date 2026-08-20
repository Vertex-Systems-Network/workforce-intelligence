<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides screenshot storage job behavior within the WorkIntel application. */ class ScreenshotStorageJob extends Model
{
    protected $fillable = ['uuid','workspace_id','screenshot_id','storage_provider_id','status','attempts','max_attempts','next_attempt_at','started_at','completed_at','remote_key','remote_object_id','checksum_sha256','last_error'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['next_attempt_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime']; }
    /** Handles the screenshot operation for the current WorkIntel workflow. */ public function screenshot(): BelongsTo { return $this->belongsTo(Screenshot::class); }
    /** Handles the provider operation for the current WorkIntel workflow. */ public function provider(): BelongsTo { return $this->belongsTo(ScreenshotStorageProvider::class, 'storage_provider_id'); }
}
