<?php
namespace App\Models;
use App\Casts\DateOnly;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides open shift behavior within the WorkIntel application. */ class OpenShift extends Model { protected $fillable=['workspace_id','shift_id','project_id','date','slots','claimed_slots','work_mode','status','note','created_by']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['date'=>DateOnly::class];} /** Handles the shift operation for the current WorkIntel workflow. */ public function shift():BelongsTo{return $this->belongsTo(Shift::class);} /** Handles the project operation for the current WorkIntel workflow. */ public function project():BelongsTo{return $this->belongsTo(Project::class);} }
