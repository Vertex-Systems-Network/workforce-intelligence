<?php
namespace App\Models;
use App\Casts\DateOnly;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides member availability behavior within the WorkIntel application. */ class MemberAvailability extends Model { protected $table='member_availability'; protected $fillable=['workspace_id','member_id','date','status','start_time','end_time','note']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['date'=>DateOnly::class];} /** Handles the member operation for the current WorkIntel workflow. */ public function member():BelongsTo{return $this->belongsTo(WorkspaceMember::class,'member_id');} }
