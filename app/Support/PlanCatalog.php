<?php

namespace App\Support;

use App\Models\SubscriptionPlan;

/** Defines the install-time WorkIntel plan defaults while preserving seller customizations after creation. */
final class PlanCatalog
{
    public const DEFINITIONS = [
        'free' => [
            'name' => 'Free', 'description' => 'Core workforce basics for small teams.', 'monthly' => 0, 'annual' => 0, 'trial_days' => 0, 'popular' => false,
            'entitlements' => [
                'limit.members' => 5, 'limit.projects' => 3, 'limit.clients' => 3, 'limit.devices' => 1,
                'limit.screenshot_retention_days' => 7, 'limit.saved_reports' => 0, 'limit.scheduled_reports' => 0, 'limit.automation_workflows' => 0,
                'feature.desktop_agent' => true, 'feature.activity_tracking' => false, 'feature.browser_tracking' => false,
                'feature.screenshots' => false, 'feature.external_screenshot_storage' => false, 'feature.payroll' => false, 'feature.advanced_reports' => false,
                'feature.scheduled_reports' => false, 'feature.client_portal' => false, 'feature.client_invoicing' => false, 'feature.client_payments' => false, 'feature.recurring_client_invoices' => false, 'feature.website_builder' => false, 'feature.website_forms' => false, 'limit.website_pages' => 0, 'feature.api_access' => false, 'feature.automations' => false, 'feature.workforce_intelligence' => false, 'feature.priority_support' => false,
                'feature.addon_marketplace' => true, 'feature.import_wizard' => false, 'feature.sandbox_workspace' => false, 'feature.white_label' => false, 'feature.custom_domains' => false, 'feature.partner_platform' => false, 'feature.partner_api' => false, 'limit.sandbox_workspaces' => 0, 'limit.custom_domains' => 0, 'limit.partner_workspaces' => 0,
            ],
        ],
        'silver' => [
            'name' => 'Silver', 'description' => 'Tracking and visibility for growing teams.', 'monthly' => 6, 'annual' => 60, 'trial_days' => 14, 'popular' => false,
            'entitlements' => [
                'limit.members' => 25, 'limit.projects' => -1, 'limit.clients' => 25, 'limit.devices' => 25,
                'limit.screenshot_retention_days' => 90, 'limit.saved_reports' => 5, 'limit.scheduled_reports' => 0, 'limit.automation_workflows' => 0,
                'feature.desktop_agent' => true, 'feature.activity_tracking' => true, 'feature.browser_tracking' => true,
                'feature.screenshots' => true, 'feature.external_screenshot_storage' => false, 'feature.payroll' => false, 'feature.advanced_reports' => false,
                'feature.scheduled_reports' => false, 'feature.client_portal' => true, 'feature.client_invoicing' => false, 'feature.client_payments' => false, 'feature.recurring_client_invoices' => false, 'feature.website_builder' => false, 'feature.website_forms' => false, 'limit.website_pages' => 0, 'feature.api_access' => false, 'feature.automations' => false, 'feature.workforce_intelligence' => false, 'feature.priority_support' => false,
                'feature.addon_marketplace' => true, 'feature.import_wizard' => true, 'feature.sandbox_workspace' => false, 'feature.white_label' => false, 'feature.custom_domains' => false, 'feature.partner_platform' => false, 'feature.partner_api' => false, 'limit.sandbox_workspaces' => 0, 'limit.custom_domains' => 0, 'limit.partner_workspaces' => 0,
            ],
        ],
        'gold' => [
            'name' => 'Gold', 'description' => 'Payroll, advanced reporting and automation for established teams.', 'monthly' => 12, 'annual' => 120, 'trial_days' => 14, 'popular' => true,
            'entitlements' => [
                'limit.members' => 100, 'limit.projects' => -1, 'limit.clients' => -1, 'limit.devices' => 100,
                'limit.screenshot_retention_days' => 365, 'limit.saved_reports' => 100, 'limit.scheduled_reports' => 25, 'limit.automation_workflows' => 25,
                'feature.desktop_agent' => true, 'feature.activity_tracking' => true, 'feature.browser_tracking' => true,
                'feature.screenshots' => true, 'feature.external_screenshot_storage' => true, 'feature.payroll' => true, 'feature.advanced_reports' => true,
                'feature.scheduled_reports' => true, 'feature.client_portal' => true, 'feature.client_invoicing' => true, 'feature.client_payments' => true, 'feature.recurring_client_invoices' => true, 'feature.website_builder' => true, 'feature.website_forms' => true, 'limit.website_pages' => 25, 'feature.api_access' => true, 'feature.automations' => true, 'feature.workforce_intelligence' => true, 'feature.priority_support' => false,
                'feature.addon_marketplace' => true, 'feature.import_wizard' => true, 'feature.sandbox_workspace' => true, 'feature.white_label' => false, 'feature.custom_domains' => false, 'feature.partner_platform' => false, 'feature.partner_api' => false, 'limit.sandbox_workspaces' => 1, 'limit.custom_domains' => 0, 'limit.partner_workspaces' => 0,
            ],
        ],
        'platinum' => [
            'name' => 'Platinum', 'description' => 'Maximum limits, long retention and priority support.', 'monthly' => 24, 'annual' => 240, 'trial_days' => 14, 'popular' => false,
            'entitlements' => [
                'limit.members' => -1, 'limit.projects' => -1, 'limit.clients' => -1, 'limit.devices' => -1,
                'limit.screenshot_retention_days' => 3650, 'limit.saved_reports' => -1, 'limit.scheduled_reports' => -1, 'limit.automation_workflows' => -1,
                'feature.desktop_agent' => true, 'feature.activity_tracking' => true, 'feature.browser_tracking' => true,
                'feature.screenshots' => true, 'feature.external_screenshot_storage' => true, 'feature.payroll' => true, 'feature.advanced_reports' => true,
                'feature.scheduled_reports' => true, 'feature.client_portal' => true, 'feature.client_invoicing' => true, 'feature.client_payments' => true, 'feature.recurring_client_invoices' => true, 'feature.website_builder' => true, 'feature.website_forms' => true, 'limit.website_pages' => -1, 'feature.api_access' => true, 'feature.automations' => true, 'feature.workforce_intelligence' => true, 'feature.priority_support' => true,
                'feature.addon_marketplace' => true, 'feature.import_wizard' => true, 'feature.sandbox_workspace' => true, 'feature.white_label' => true, 'feature.custom_domains' => true, 'feature.partner_platform' => true, 'feature.partner_api' => true, 'limit.sandbox_workspaces' => 5, 'limit.custom_domains' => 3, 'limit.partner_workspaces' => 100,
            ],
        ],
    ];

    /** Creates missing plans/capabilities and optionally restores install defaults when explicitly requested. */
    public static function sync(bool $overwrite=false): void
    {
        $sort=0;
        foreach(self::DEFINITIONS as $slug=>$definition){
            $defaults=['name'=>$definition['name'],'description'=>$definition['description'],'currency'=>'USD','monthly_price_per_seat'=>$definition['monthly'],'annual_price_per_seat'=>$definition['annual'],'trial_days'=>$definition['trial_days'],'is_active'=>true,'is_public'=>true,'is_popular'=>$definition['popular'],'sort_order'=>$sort++];
            $plan=SubscriptionPlan::firstOrCreate(['slug'=>$slug],$defaults);
            if($overwrite)$plan->update($defaults);
            foreach($definition['entitlements'] as $key=>$value){
                $type=is_bool($value)?'boolean':(is_int($value)?'integer':'string');$payload=['value_type'=>$type,'value'=>['value'=>$value],'label'=>self::label($key)];
                $row=$plan->entitlements()->firstOrCreate(['key'=>$key],$payload);if($overwrite)$row->update($payload);
            }
        }
    }

    /** Returns the seller-editable capability catalog shared by every subscription plan. */
    public static function capabilities(): array
    {
        $keys=[];
        foreach(self::DEFINITIONS as $definition)foreach($definition['entitlements'] as $key=>$value)$keys[$key]=['key'=>$key,'label'=>self::label($key),'value_type'=>is_bool($value)?'boolean':(is_int($value)?'integer':'string'),'category'=>str_starts_with($key,'limit.')?'limits':'features'];
        ksort($keys);return array_values($keys);
    }

    /** Converts an entitlement key into a human-readable seller label. */
    private static function label(string $key): string { return ucwords(str_replace(['feature.','limit.','_','.'],['','',' ',' '],$key)); }
}
