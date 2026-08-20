<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides field checkpoint behavior within the WorkIntel application. */ class FieldCheckpoint extends Model{protected $fillable=['uuid','workspace_id','project_id','name','type','scan_token_hash','token_prefix','latitude','longitude','radius_meters','status','created_by'];}
