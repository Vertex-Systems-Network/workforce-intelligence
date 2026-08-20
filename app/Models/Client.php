<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Provides client behavior within the WorkIntel application. */ class Client extends Model
{
    use SoftDeletes;
    protected $fillable = ['workspace_id', 'name', 'company_name', 'email', 'billing_email', 'phone', 'billing_address', 'tax_id', 'currency', 'billing_rate', 'status'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['billing_rate' => 'decimal:2']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the projects operation for the current WorkIntel workflow. */ public function projects(): HasMany { return $this->hasMany(Project::class); }
    /** Handles the portal accounts operation for the current WorkIntel workflow. */ public function portalAccounts(): HasMany { return $this->hasMany(ClientPortalAccount::class); }
    /** Handles the invoices operation for the current WorkIntel workflow. */ public function invoices(): HasMany { return $this->hasMany(ClientInvoice::class); }
    /** Handles the reports operation for the current WorkIntel workflow. */ public function reports(): HasMany { return $this->hasMany(ClientReport::class); }
    /** Handles the payments operation for the current WorkIntel workflow. */ public function payments(): HasMany { return $this->hasMany(ClientPayment::class); }
}
