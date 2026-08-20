<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides project cost allocation behavior within the WorkIntel application. */ class ProjectCostAllocation extends Model{protected $fillable=['workspace_id','project_id','cost_center_id','allocation_percent'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['allocation_percent'=>'decimal:2'];}}
