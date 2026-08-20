<?php
namespace App\Services\Commerce;
use App\Services\Commerce\Providers\{CommerceProvider,CustomHttpProvider,ManualProvider,PayPalProvider,PaddleProvider,StripeProvider};use InvalidArgumentException;
/** Provides commerce provider registry behavior within the WorkIntel application. */ class CommerceProviderRegistry
{
    /** Returns get data required by the current workflow. */ public function get(string $key):CommerceProvider{return match($key){'manual'=>new ManualProvider('manual'),'bank_transfer'=>new ManualProvider('bank_transfer'),'stripe'=>new StripeProvider(),'paypal'=>new PayPalProvider(),'paddle'=>new PaddleProvider(),'custom_http'=>new CustomHttpProvider(),default=>throw new InvalidArgumentException("Unsupported commerce provider: {$key}")};}
    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog():array{return [['key'=>'manual','name'=>'Manual Settlement'],['key'=>'bank_transfer','name'=>'Bank Transfer'],['key'=>'stripe','name'=>'Stripe'],['key'=>'paypal','name'=>'PayPal'],['key'=>'paddle','name'=>'Paddle'],['key'=>'custom_http','name'=>'Custom HTTP']];}
}
