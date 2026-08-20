<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides employee document folder behavior within the WorkIntel application. */ class EmployeeDocumentFolder extends Model { protected $fillable=['workspace_id','member_id','name','category']; /** Handles the member operation for the current WorkIntel workflow. */ public function member():BelongsTo{return $this->belongsTo(WorkspaceMember::class,'member_id');} /** Handles the documents operation for the current WorkIntel workflow. */ public function documents():HasMany{return $this->hasMany(EmployeeDocument::class,'folder_id');} }
