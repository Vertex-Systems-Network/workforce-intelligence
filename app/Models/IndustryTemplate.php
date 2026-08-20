<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides industry template behavior within the WorkIntel application. */ class IndustryTemplate extends Model{protected $fillable=['uuid','name','slug','industry','description','version','status','blueprint'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['version'=>'integer','blueprint'=>'array'];}}
