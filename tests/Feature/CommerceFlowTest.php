<?php

namespace Tests\Feature;

use App\Models\BillingTransaction;
use App\Models\CommerceCheckoutSession;
use App\Models\CommerceProviderConfig;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p11 commerce flow test behavior within the WorkIntel application. */ class CommerceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test public pricing and manual checkout activate only after settlement operation for the current WorkIntel workflow. */ public function test_public_pricing_and_manual_checkout_activate_only_after_settlement(): void
    {
        $this->getJson('/api/v1/commerce/pricing')->assertOk()->assertJsonFragment(['slug'=>'gold']);
        [$owner,$member]=$this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);$h=$this->headers($member->workspace_id);
        $before=$member->workspace->subscription()->with('plan')->firstOrFail()->plan->slug;
        $this->assertSame('gold',$before);

        $checkout=$this->postJson('/api/v1/commerce/checkout',[
            'plan_slug'=>'platinum','billing_interval'=>'monthly','provider'=>'manual',
        ],$h)->assertCreated()->assertJsonPath('checkout.status','pending')->json('checkout');
        $this->assertSame('gold',$member->workspace->subscription()->with('plan')->firstOrFail()->plan->slug);

        $this->postJson('/api/v1/seller/checkouts/'.$checkout['id'].'/settle',['reference'=>'BANK-TEST-001'])
            ->assertOk()->assertJsonPath('data.status','completed');
        $this->assertSame('platinum',$member->workspace->subscription()->with('plan')->firstOrFail()->plan->slug);
        $this->assertTrue(BillingTransaction::where('workspace_id',$member->workspace_id)->where('status','succeeded')->where('type','payment')->exists());
    }

    /** Handles the test coupon and country tax are calculated server side operation for the current WorkIntel workflow. */ public function test_coupon_and_country_tax_are_calculated_server_side(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$h=$this->headers($member->workspace_id);
        $member->workspace->update(['country'=>'AE']);
        $this->postJson('/api/v1/seller/coupons',['code'=>'SAVE10','name'=>'Ten percent','discount_type'=>'percent','discount_value'=>10,'eligible_plans'=>['platinum'],'max_redemptions'=>50,'active'=>true])->assertCreated();
        $this->postJson('/api/v1/seller/tax-rules',['name'=>'AE VAT','country'=>'AE','rate_percent'=>5,'active'=>true,'priority'=>1])->assertCreated();
        $quote=$this->postJson('/api/v1/commerce/quote',['plan_slug'=>'platinum','billing_interval'=>'monthly','coupon_code'=>'SAVE10'],$h)->assertOk()->json('quote');
        $this->assertGreaterThan(0,$quote['discount']);
        $this->assertSame(5.0,(float)$quote['tax']['rate']);
        $this->assertGreaterThan($quote['subtotal']-$quote['discount'],$quote['total']);
    }

    /** Handles the test state region tax overrides country only rule operation for the current WorkIntel workflow. */ public function test_state_region_tax_overrides_country_only_rule(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$h=$this->headers($member->workspace_id);
        $member->workspace->update(['country'=>'AE']);
        $member->workspace->preferences()->updateOrCreate(['workspace_id'=>$member->workspace_id],['uuid'=>(string)\Illuminate\Support\Str::uuid(),'state_region'=>'Dubai']);
        $this->postJson('/api/v1/seller/tax-rules',['name'=>'AE base','country'=>'AE','rate_percent'=>5,'active'=>true,'priority'=>1])->assertCreated();
        $this->postJson('/api/v1/seller/tax-rules',['name'=>'Dubai override','country'=>'AE','state_region'=>'Dubai','rate_percent'=>7,'active'=>true,'priority'=>1])->assertCreated();
        $quote=$this->postJson('/api/v1/commerce/quote',['plan_slug'=>'platinum','billing_interval'=>'monthly'],$h)->assertOk()->json('quote');
        $this->assertSame(7.0,(float)$quote['tax']['rate']);
    }

    /** Handles the test platform operator boundary blocks normal workspace users operation for the current WorkIntel workflow. */ public function test_platform_operator_boundary_blocks_normal_workspace_users(): void
    {
        [$employee]=$this->userAndMember('employee@acme.test');Sanctum::actingAs($employee);
        $this->getJson('/api/v1/seller')->assertForbidden();
        [$owner]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);
        $this->getJson('/api/v1/seller')->assertOk()->assertJsonStructure(['summary'=>['mrr','arr','customers'],'providers','plans']);
    }

    /** Handles the test provider credentials are encrypted and not returned operation for the current WorkIntel workflow. */ public function test_provider_credentials_are_encrypted_and_not_returned(): void
    {
        [$owner]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);
        $secret='sk_test_super_secret_value';
        $response=$this->putJson('/api/v1/seller/providers/stripe',[
            'display_name'=>'Stripe','enabled'=>false,'is_default'=>false,'test_mode'=>true,
            'credentials'=>['secret_key'=>$secret,'webhook_secret'=>'whsec_test'],
            'settings'=>['price_map'=>['gold.monthly'=>'price_test']],
        ])->assertOk();
        $response->assertJsonMissing(['secret_key'=>$secret]);
        $row=CommerceProviderConfig::where('provider','stripe')->firstOrFail();
        $this->assertSame($secret,$row->credentials['secret_key']);
        $raw=(string)DB::table('commerce_provider_configs')->where('id',$row->id)->value('credentials');
        $this->assertStringNotContainsString($secret,$raw);
    }

    /** Handles the test manual refund creates refund ledger and negative transaction operation for the current WorkIntel workflow. */ public function test_manual_refund_creates_refund_ledger_and_negative_transaction(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$h=$this->headers($member->workspace_id);
        $checkout=$this->postJson('/api/v1/commerce/checkout',['plan_slug'=>'platinum','billing_interval'=>'monthly','provider'=>'manual'],$h)->assertCreated()->json('checkout');
        $this->postJson('/api/v1/seller/checkouts/'.$checkout['id'].'/settle',['reference'=>'MANUAL-PAY-1'])->assertOk();
        $payment=BillingTransaction::where('workspace_id',$member->workspace_id)->where('type','payment')->latest('id')->firstOrFail();
        $amount=min(1.0,(float)$payment->amount);
        $this->postJson('/api/v1/seller/transactions/'.$payment->id.'/refund',['amount'=>$amount,'reason'=>'Test partial refund'])->assertOk()->assertJsonPath('data.status','succeeded');
        $this->assertTrue(BillingTransaction::where('workspace_id',$member->workspace_id)->where('type','refund')->where('amount','<',0)->exists());
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user=User::where('email',$email)->firstOrFail();
        $member=$user->memberships()->with('workspace')->where('status','active')->firstOrFail();
        return[$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId):array{return['X-Workspace-Id'=>(string)$workspaceId];}
}
