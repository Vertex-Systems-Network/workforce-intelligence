<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides employee lifecycle checklist item behavior within the WorkIntel application. */ class EmployeeLifecycleChecklistItem extends Model { protected $fillable=['checklist_id','title','description','owner_type','due_date','status','required','sort_order','completed_by','completed_at','completion_note']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['due_date'=>'date','required'=>'boolean','completed_at'=>'datetime'];} }
