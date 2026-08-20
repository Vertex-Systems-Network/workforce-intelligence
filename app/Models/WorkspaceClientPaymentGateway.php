<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stores one workspace-owned client payment gateway without exposing credentials to the client UI. */
class WorkspaceClientPaymentGateway extends Model
{
    protected $fillable=['uuid','workspace_id','provider','display_name','enabled','is_default','test_mode','client_portal_enabled','sort_order','credentials','settings','last_tested_at','health_status','health_message','updated_by'];
    protected $hidden=['credentials'];
    /** Defines secure casts for gateway configuration. */
    protected function casts(): array { return ['enabled'=>'boolean','is_default'=>'boolean','test_mode'=>'boolean','client_portal_enabled'=>'boolean','credentials'=>'encrypted:array','settings'=>'array','last_tested_at'=>'datetime']; }
    /** Returns the workspace that owns the gateway. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Returns hosted client checkout sessions created through this gateway. */ public function checkouts(): HasMany { return $this->hasMany(ClientPaymentCheckoutSession::class); }
}
