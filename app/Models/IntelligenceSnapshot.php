<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

/** Provides intelligence snapshot behavior within the WorkIntel application. */ class IntelligenceSnapshot extends Model
{
    public $timestamps = false;
    protected $fillable = ['workspace_id','snapshot_date','scope_key','scope_type','scope_id','metric_key','metric_value','unit','dimensions','generated_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['snapshot_date'=>DateOnly::class,'metric_value'=>'decimal:4','dimensions'=>'array','generated_at'=>'datetime']; }
}
