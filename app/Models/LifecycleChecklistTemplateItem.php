<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides lifecycle checklist template item behavior within the WorkIntel application. */ class LifecycleChecklistTemplateItem extends Model { protected $fillable=['template_id','title','description','owner_type','due_offset_days','required','sort_order']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['required'=>'boolean'];} }
