<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides commerce provider config behavior within the WorkIntel application. */ class CommerceProviderConfig extends Model
{
    protected $fillable=['uuid','provider','display_name','enabled','is_default','test_mode','credentials','settings','last_tested_at','health_status','health_message','updated_by'];
    protected $hidden=['credentials'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['enabled'=>'boolean','is_default'=>'boolean','test_mode'=>'boolean','credentials'=>'encrypted:array','settings'=>'array','last_tested_at'=>'datetime'];}
}
