<?php
namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
/** Provides billing usage counter behavior within the WorkIntel application. */ class BillingUsageCounter extends Model
{
    protected $fillable=['workspace_id','metric','period_start','period_end','quantity'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['period_start'=>DateOnly::class,'period_end'=>DateOnly::class,'quantity'=>'decimal:4']; }
}
