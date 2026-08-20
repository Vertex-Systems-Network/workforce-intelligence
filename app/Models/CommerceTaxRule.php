<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides commerce tax rule behavior within the WorkIntel application. */ class CommerceTaxRule extends Model{protected $fillable=['uuid','name','country','state_region','rate_percent','active','priority','created_by'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['rate_percent'=>'decimal:4','active'=>'boolean'];}}
