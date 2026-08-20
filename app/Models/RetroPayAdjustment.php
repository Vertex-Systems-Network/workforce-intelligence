<?php
namespace App\Models;
use App\Casts\DateOnly;use Illuminate\Database\Eloquent\Model;
/** Provides retro pay adjustment behavior within the WorkIntel application. */ class RetroPayAdjustment extends Model{protected $fillable=['uuid','workspace_id','member_id','currency','amount','source_period_start','source_period_end','reason','status','payroll_run_id','payroll_adjustment_id','created_by','applied_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['amount'=>'decimal:2','source_period_start'=>DateOnly::class,'source_period_end'=>DateOnly::class,'applied_at'=>'datetime'];}}
