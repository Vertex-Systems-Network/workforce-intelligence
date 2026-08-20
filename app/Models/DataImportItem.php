<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides data import item behavior within the WorkIntel application. */ class DataImportItem extends Model{protected $fillable=['data_import_job_id','row_number','external_key','fingerprint','status','target_type','target_id','source_data','normalized_data','error'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['source_data'=>'array','normalized_data'=>'array'];}/** Handles the job operation for the current WorkIntel workflow. */ public function job():BelongsTo{return $this->belongsTo(DataImportJob::class,'data_import_job_id');}}
