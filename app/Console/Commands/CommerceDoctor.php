<?php
namespace App\Console\Commands;
use App\Models\CommerceProviderConfig;use App\Services\Commerce\CommerceProviderRegistry;use Illuminate\Console\Command;use Illuminate\Support\Facades\Schema;
/** Provides p11 commerce doctor behavior within the WorkIntel application. */ class CommerceDoctor extends Command
{
    protected $signature='workintel:p11-doctor';protected $description='Validate P11 SaaS Seller / Buyer Commerce schema and provider registry.';
    /** Executes the command, job, or request handler. */ public function handle(CommerceProviderRegistry $registry):int{$errors=[];foreach(['commerce_provider_configs','commerce_coupons','commerce_tax_rules','commerce_checkout_sessions','commerce_coupon_redemptions','commerce_refunds','commerce_webhook_events','commerce_dunning_attempts'] as $t)if(!Schema::hasTable($t))$errors[]="Missing {$t}.";if(Schema::hasTable('commerce_provider_configs')&&!CommerceProviderConfig::where('provider','manual')->exists())$errors[]='Manual commerce provider is not seeded.';$keys=array_column($registry->catalog(),'key');foreach(['manual','bank_transfer','stripe','paypal','paddle','custom_http'] as $k)if(!in_array($k,$keys,true))$errors[]="Provider {$k} missing from registry.";if($errors){foreach($errors as $e)$this->error($e);return self::FAILURE;}$this->info('P11 SaaS Seller / Buyer Commerce: PASS');return self::SUCCESS;}
}
