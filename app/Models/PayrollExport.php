<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides payroll export behavior within the WorkIntel application. */ class PayrollExport extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','payroll_run_id','provider','format','file_path','file_name','sha256','size_bytes','created_by','created_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['created_at'=>'datetime'];}/** Handles the run operation for the current WorkIntel workflow. */ public function run():BelongsTo{return $this->belongsTo(PayrollRun::class,'payroll_run_id');}}
