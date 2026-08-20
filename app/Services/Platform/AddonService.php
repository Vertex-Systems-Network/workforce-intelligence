<?php
namespace App\Services\Platform;

use App\Models\AddonUsageEvent;
use App\Models\PlatformAddon;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides addon service behavior within the WorkIntel application. */ class AddonService
{
    /** Handles the catalog for operation for the current WorkIntel workflow. */ public function catalogFor(Workspace $workspace): array
    {
        $slug = $workspace->subscription?->plan?->slug ?? $workspace->subscription()->with('plan')->first()?->plan?->slug ?? 'free';
        return PlatformAddon::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get()
            ->filter(fn (PlatformAddon $addon) => empty($addon->eligible_plans) || in_array($slug, $addon->eligible_plans, true))
            ->map(fn (PlatformAddon $addon) => $this->addonPayload($addon, WorkspaceAddon::query()->where('workspace_id', $workspace->id)->where('platform_addon_id', $addon->id)->first()))->values()->all();
    }

    /** Handles the subscribe operation for the current WorkIntel workflow. */ public function subscribe(Workspace $workspace, PlatformAddon $addon, float $quantity = 1): WorkspaceAddon
    {
        if ($addon->status !== 'active') throw ValidationException::withMessages(['addon' => ['This add-on is not available.']]);
        $slug = $workspace->subscription()->with('plan')->first()?->plan?->slug ?? 'free';
        if ($addon->eligible_plans && ! in_array($slug, $addon->eligible_plans, true)) throw ValidationException::withMessages(['addon' => ['This add-on is not available on the current plan.']]);
        return WorkspaceAddon::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'platform_addon_id' => $addon->id],
            ['uuid' => (string) Str::uuid(), 'status' => 'active', 'quantity' => max(0.0001, $quantity), 'started_at' => now(), 'current_period_start' => now(), 'current_period_end' => now()->addMonth(), 'canceled_at' => null]
        );
    }

    /** Determines whether the cancel condition is satisfied. */ public function cancel(Workspace $workspace, WorkspaceAddon $subscription): WorkspaceAddon
    {
        abort_unless((int) $subscription->workspace_id === (int) $workspace->id, 404);
        $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        return $subscription->fresh('addon');
    }

    /** Handles the record usage operation for the current WorkIntel workflow. */ public function recordUsage(Workspace $workspace, WorkspaceAddon $subscription, string $metric, float $quantity, string $idempotencyKey, array $metadata = []): AddonUsageEvent
    {
        abort_unless((int) $subscription->workspace_id === (int) $workspace->id, 404);
        abort_unless($subscription->status === 'active', 422, 'The add-on is not active.');
        if ($quantity <= 0) throw ValidationException::withMessages(['quantity' => ['Usage quantity must be greater than zero.']]);
        try {
            return DB::transaction(function () use ($workspace, $subscription, $metric, $quantity, $idempotencyKey, $metadata) {
                $existing = AddonUsageEvent::query()
                    ->where('workspace_addon_id', $subscription->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) return $existing;

                return AddonUsageEvent::create([
                    'uuid' => (string) Str::uuid(),
                    'workspace_id' => $workspace->id,
                    'workspace_addon_id' => $subscription->id,
                    'metric' => $metric,
                    'quantity' => $quantity,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => now(),
                    'metadata' => $metadata,
                    'created_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return AddonUsageEvent::query()
                ->where('workspace_addon_id', $subscription->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    }

    /** Handles the monthly estimate operation for the current WorkIntel workflow. */ public function monthlyEstimate(Workspace $workspace): array
    {
        $rows = WorkspaceAddon::with('addon')->where('workspace_id', $workspace->id)->where('status', 'active')->get();
        $items=[];$total=0.0;
        foreach ($rows as $row) {
            $addon=$row->addon;if(!$addon)continue;$base=(float)$addon->monthly_price*(float)$row->quantity;$usage=0.0;$used=0.0;
            if($addon->pricing_mode==='metered'){
                $start=$row->current_period_start??now()->startOfMonth();$end=$row->current_period_end??now()->endOfMonth();
                $used=(float)AddonUsageEvent::where('workspace_addon_id',$row->id)->whereBetween('occurred_at',[$start,$end])->sum('quantity');
                $billable=max(0,$used-(float)$addon->included_quantity);$usage=$billable*(float)$addon->unit_price;
            }
            $amount=$base+$usage;$total+=$amount;$items[]=['subscription_id'=>$row->id,'addon'=>$addon->name,'pricing_mode'=>$addon->pricing_mode,'quantity'=>(float)$row->quantity,'usage_quantity'=>$used,'amount'=>round($amount,2),'currency'=>$addon->currency];
        }
        return ['items'=>$items,'estimated_total'=>round($total,2),'currency'=>'USD'];
    }

    /** Handles the entitlement value operation for the current WorkIntel workflow. */ public static function entitlementValue(Workspace $workspace, string $key, mixed $base): mixed
    {
        if (! Schema::hasTable('workspace_addons') || ! Schema::hasTable('platform_addons')) return $base;
        $rows=WorkspaceAddon::query()->with('addon')->where('workspace_id',$workspace->id)->where('status','active')->get();
        $value=$base;
        foreach($rows as $row){$addon=$row->addon;if(!$addon||$addon->entitlement_key!==$key)continue;$addonValue=$addon->entitlement_value['value']??null;
            if($addon->entitlement_mode==='additive'&&is_numeric($addonValue)){if((int)$value<0)continue;$value=(float)$value+((float)$addonValue*(float)$row->quantity);}
            elseif($addon->entitlement_mode==='grant'){if(is_bool($addonValue))$value=(bool)$value||$addonValue;elseif($addonValue!==null)$value=$addonValue;}
        }
        return $value;
    }

    /** Handles the addon payload operation for the current WorkIntel workflow. */ private function addonPayload(PlatformAddon $addon, ?WorkspaceAddon $sub): array
    {
        return ['id'=>$addon->id,'uuid'=>$addon->uuid,'name'=>$addon->name,'slug'=>$addon->slug,'description'=>$addon->description,'category'=>$addon->category,'pricing_mode'=>$addon->pricing_mode,'currency'=>$addon->currency,'monthly_price'=>(float)$addon->monthly_price,'unit_price'=>(float)$addon->unit_price,'included_quantity'=>(float)$addon->included_quantity,'unit_name'=>$addon->unit_name,'entitlement_key'=>$addon->entitlement_key,'subscription'=>$sub?['id'=>$sub->id,'status'=>$sub->status,'quantity'=>(float)$sub->quantity]:null];
    }
}
