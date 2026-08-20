<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides security event behavior within the WorkIntel application. */ class SecurityEvent extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','user_id','event_type','severity','ip_address','user_agent','metadata','resolved_at','resolved_by','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata'=>'array','resolved_at'=>'datetime','created_at'=>'datetime']; }
}
