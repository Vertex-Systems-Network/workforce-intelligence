<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides attendance event behavior within the WorkIntel application. */ class AttendanceEvent extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','member_id','attendance_record_id','attendance_location_id','event_type','source','occurred_at','latitude','longitude','accuracy_meters','within_geofence','metadata','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['occurred_at'=>'datetime','created_at'=>'datetime','latitude'=>'float','longitude'=>'float','accuracy_meters'=>'float','within_geofence'=>'boolean','metadata'=>'array']; }
}
