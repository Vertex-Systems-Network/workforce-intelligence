<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides audit log behavior within the WorkIntel application. */ class AuditLog extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','user_id','member_id','actor_type','actor_id','action','category','method','path','route_name','status_code','ip_address','user_agent','subject_type','subject_id','metadata','risk_level','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata'=>'array','created_at'=>'datetime']; }
}
