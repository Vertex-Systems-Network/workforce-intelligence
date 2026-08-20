<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides workspace preference behavior within the WorkIntel application. */ class WorkspacePreference extends Model
{
    protected $fillable = [
        'uuid','workspace_id','app_title','company_name','legal_name','website_url','support_email','support_phone',
        'address_line_1','address_line_2','city','state_region','postal_code','default_language','date_format','time_format',
        'fiscal_year_start_month','number_format','decimal_separator','thousands_separator','default_theme','sidebar_density',
        'accent_color','secondary_color','logo_path','logo_mime','favicon_path','favicon_mime','login_title','login_subtitle','updated_by',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['fiscal_year_start_month' => 'integer'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Updates updated by data for the requested resource. */ public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
