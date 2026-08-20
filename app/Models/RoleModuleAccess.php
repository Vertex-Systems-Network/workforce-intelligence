<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides role module access behavior within the WorkIntel application. */ class RoleModuleAccess extends Model
{
    protected $table='role_module_access';
    protected $fillable=['role_id','module_key','access'];
    /** Handles the role operation for the current WorkIntel workflow. */ public function role():BelongsTo{return $this->belongsTo(Role::class);}
}
