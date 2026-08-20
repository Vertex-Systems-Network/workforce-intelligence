<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides legal entity behavior within the WorkIntel application. */ class LegalEntity extends Model{protected $fillable=['uuid','workspace_id','code','name','country_code','registration_number','tax_identifier','currency','timezone','status'];}
