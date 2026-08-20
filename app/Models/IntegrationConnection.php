<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides integration connection behavior within the WorkIntel application. */ class IntegrationConnection extends Model
{
    protected $fillable=['uuid','workspace_id','created_by','provider','name','status','config_encrypted','last_tested_at','last_error'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['last_tested_at'=>'datetime']; }
}
