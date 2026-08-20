<?php
namespace Database\Seeders;
use App\Models\CommerceProviderConfig;use Illuminate\Database\Seeder;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Str;
/** Provides p11 commerce seeder behavior within the WorkIntel application. */ class CommerceSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run():void
    {
        if(!Schema::hasTable('commerce_provider_configs'))return;
        $providers=['manual'=>'Manual Settlement','bank_transfer'=>'Bank Transfer','stripe'=>'Stripe','paypal'=>'PayPal','paddle'=>'Paddle','custom_http'=>'Custom HTTP'];
        foreach($providers as $key=>$name)CommerceProviderConfig::firstOrCreate(['provider'=>$key],['uuid'=>(string)Str::uuid(),'display_name'=>$name,'enabled'=>$key==='manual','is_default'=>$key==='manual','test_mode'=>true,'settings'=>$key==='manual'?['instructions'=>'Contact your billing administrator to settle this checkout.']:[],'health_status'=>$key==='manual'?'healthy':'unknown']);
    }
}
