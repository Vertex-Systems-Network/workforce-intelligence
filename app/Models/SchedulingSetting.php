<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides scheduling setting behavior within the WorkIntel application. */ class SchedulingSetting extends Model { protected $fillable=['workspace_id','max_weekly_hours','overtime_warning_hours','minimum_rest_hours','daily_coverage_target','weekly_labor_budget','currency','allow_open_shift_claims','allow_shift_swaps']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['weekly_labor_budget'=>'decimal:2','allow_open_shift_claims'=>'boolean','allow_shift_swaps'=>'boolean'];} }
