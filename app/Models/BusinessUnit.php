<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides business unit behavior within the WorkIntel application. */ class BusinessUnit extends Model{protected $fillable=['uuid','workspace_id','legal_entity_id','parent_id','code','name','leader_member_id','status'];}
