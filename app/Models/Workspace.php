<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides workspace behavior within the WorkIntel application. */ class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'timezone', 'currency', 'country', 'week_starts_on', 'status', 'workspace_type', 'parent_workspace_id', 'sandbox_expires_at', 'owner_id',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['sandbox_expires_at' => 'datetime'];
    }

    /** Handles the owner operation for the current WorkIntel workflow. */ public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Handles the members operation for the current WorkIntel workflow. */ public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /** Handles the departments operation for the current WorkIntel workflow. */ public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** Handles the clients operation for the current WorkIntel workflow. */ public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** Handles the projects operation for the current WorkIntel workflow. */ public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }


    /** Handles the devices operation for the current WorkIntel workflow. */ public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /** Handles the payroll runs operation for the current WorkIntel workflow. */ public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /** Handles the subscription operation for the current WorkIntel workflow. */ public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkspaceSubscription::class);
    }

    /** Handles the branding operation for the current WorkIntel workflow. */ public function branding(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(WorkspaceBranding::class); }
    /** Handles the preferences operation for the current WorkIntel workflow. */ public function preferences(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(WorkspacePreference::class); }
    /** Handles the modules operation for the current WorkIntel workflow. */ public function modules(): HasMany { return $this->hasMany(WorkspaceModule::class); }
    /** Handles the module events operation for the current WorkIntel workflow. */ public function moduleEvents(): HasMany { return $this->hasMany(WorkspaceModuleEvent::class); }
    /** Handles the custom domains operation for the current WorkIntel workflow. */ public function customDomains(): HasMany { return $this->hasMany(WorkspaceDomain::class); }
    /** Handles the addons operation for the current WorkIntel workflow. */ public function addons(): HasMany { return $this->hasMany(WorkspaceAddon::class); }
    /** Handles the parent workspace operation for the current WorkIntel workflow. */ public function parentWorkspace(): BelongsTo { return $this->belongsTo(self::class, 'parent_workspace_id'); }
    /** Handles the sandbox workspaces operation for the current WorkIntel workflow. */ public function sandboxWorkspaces(): HasMany { return $this->hasMany(self::class, 'parent_workspace_id'); }

    /** Handles the billing invoices operation for the current WorkIntel workflow. */ public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /** Handles the client invoices operation for the current WorkIntel workflow. */ public function clientInvoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    /** Returns the public website owned by this workspace. */ public function websiteSite(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WebsiteSite::class);
    }

    /** Returns website pages owned by this workspace. */ public function websitePages(): HasMany
    {
        return $this->hasMany(WebsitePage::class);
    }
}
