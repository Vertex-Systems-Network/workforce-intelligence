<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        $matrix = [
            'free' => ['feature.client_portal' => false, 'feature.client_invoicing' => false],
            'silver' => ['feature.client_portal' => true, 'feature.client_invoicing' => false],
            'gold' => ['feature.client_portal' => true, 'feature.client_invoicing' => true],
            'platinum' => ['feature.client_portal' => true, 'feature.client_invoicing' => true],
        ];
        foreach ($matrix as $slug => $features) {
            $planId = DB::table('subscription_plans')->where('slug', $slug)->value('id');
            if (! $planId) continue;
            foreach ($features as $key => $value) {
                DB::table('plan_entitlements')->updateOrInsert(
                    ['subscription_plan_id' => $planId, 'key' => $key],
                    ['value_type'=>'boolean','value'=>json_encode(['value'=>$value]),'label'=>ucwords(str_replace(['feature.','_','.'],['',' ',' '],$key)),'created_at'=>now(),'updated_at'=>now()]
                );
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        DB::table('plan_entitlements')->whereIn('key', ['feature.client_portal','feature.client_invoicing'])->delete();
    }
};
