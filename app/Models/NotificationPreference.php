<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides notification preference behavior within the WorkIntel application. */ class NotificationPreference extends Model
{
    protected $fillable=['workspace_id','user_id','category','in_app','email','digest'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['in_app'=>'boolean','email'=>'boolean']; }
}
