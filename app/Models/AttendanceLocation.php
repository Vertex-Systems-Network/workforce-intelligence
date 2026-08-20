<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides attendance location behavior within the WorkIntel application. */ class AttendanceLocation extends Model
{
    protected $fillable=['uuid','workspace_id','name','latitude','longitude','radius_meters','status'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['latitude'=>'float','longitude'=>'float','radius_meters'=>'integer']; }
}
