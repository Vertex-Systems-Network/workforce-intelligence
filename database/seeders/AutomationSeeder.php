<?php
namespace Database\Seeders;

use App\Models\AutomationWorkflow;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides phase24 automation seeder behavior within the WorkIntel application. */ class AutomationSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if(!Schema::hasTable('automation_workflows')||!Schema::hasTable('automation_actions'))return;
        $workspace=Workspace::where('slug','acme-corp')->first();if(!$workspace)return;
        $workflow=AutomationWorkflow::updateOrCreate(
            ['workspace_id'=>$workspace->id,'name'=>'Payroll Paid · Admin Notification'],
            ['uuid'=>(string)Str::uuid(),'description'=>'Phase 24 demo: notify workspace admins after payroll is marked paid.','status'=>'draft','trigger_type'=>'event','trigger_event'=>'payroll.paid','trigger_config'=>[],'conditions'=>[],'condition_mode'=>'all','failure_policy'=>'stop','max_run_seconds'=>30,'created_by'=>$workspace->owner_id,'updated_by'=>$workspace->owner_id]
        );
        $workflow->actions()->updateOrCreate(['position'=>1],['name'=>'Notify admins','action_type'=>'notification','action_key'=>'notify','integration_connection_id'=>null,'config'=>['role_slugs'=>['owner','admin'],'title'=>'Payroll paid','body'=>'Payroll event {{event.id}} has completed.','severity'=>'success'],'continue_on_error'=>false,'retry_max'=>1,'timeout_seconds'=>8]);
    }
}
