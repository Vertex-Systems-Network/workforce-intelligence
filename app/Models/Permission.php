<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides permission behavior within the WorkIntel application. */ class Permission extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'group'];
}
