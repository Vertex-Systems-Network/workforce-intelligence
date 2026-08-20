<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;

/** Provides holiday behavior within the WorkIntel application. */ class Holiday extends Model
{
    protected $fillable = ['workspace_id', 'name', 'date', 'type', 'paid', 'status'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['date' => DateOnly::class, 'paid' => 'boolean']; }
}
