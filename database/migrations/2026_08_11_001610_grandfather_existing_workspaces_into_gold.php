<?php

use App\Support\PlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        PlanCatalog::sync();
        $goldId = DB::table('subscription_plans')->where('slug', 'gold')->value('id');
        if (! $goldId) return;

        DB::table('workspaces')->orderBy('id')->get(['id'])->each(function ($workspace) use ($goldId) {
            if (DB::table('workspace_subscriptions')->where('workspace_id', $workspace->id)->exists()) return;
            $seats = max(1, DB::table('workspace_members')->where('workspace_id', $workspace->id)->where('status', 'active')->count());
            DB::table('workspace_subscriptions')->insert([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $goldId,
                'status' => 'active',
                'billing_interval' => 'monthly',
                'provider' => 'manual',
                'seat_quantity' => $seats,
                'current_period_start' => now(),
                'current_period_end' => null,
                'provider_metadata' => json_encode(['grandfathered' => true, 'source' => 'm11_upgrade']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        DB::table('workspace_subscriptions')
            ->where('provider', 'manual')
            ->where('provider_metadata', json_encode(['grandfathered' => true, 'source' => 'm11_upgrade']))
            ->delete();
    }
};
