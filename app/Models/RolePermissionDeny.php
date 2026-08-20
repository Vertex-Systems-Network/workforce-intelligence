<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides role permission deny behavior within the WorkIntel application. */ class RolePermissionDeny extends Model
{
    protected $table = 'role_permission_denies';
    public $incrementing = false;
    protected $fillable = ['role_id','permission_id'];
}
