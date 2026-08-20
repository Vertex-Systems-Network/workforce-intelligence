<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\Workspace;
use App\Services\Documents\DocumentTemplateCatalog;
use App\Services\Documents\DocumentTemplateService;
use App\Services\Modules\WorkspaceModuleService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides p6 document template seeder behavior within the WorkIntel application. */ class DocumentTemplateSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        PermissionCatalog::sync();
        if(!Schema::hasTable('document_templates')) return;
        $this->ensureRolePermissions();
        $types=['invoice','client_report','payslip','billing_invoice','receipt','quote','purchase_order','timesheet','attendance_report','employment_contract','offer_letter','custom'];
        Workspace::query()->orderBy('id')->each(function(Workspace $workspace) use($types){
            app(WorkspaceModuleService::class)->initializeWorkspace($workspace);
            $owner=$workspace->members()->with('user')->whereHas('roles',fn($q)=>$q->where('slug','owner'))->first() ?: $workspace->members()->with('user')->first();
            if(!$owner) return;
            foreach($types as $type){
                $existing=DocumentTemplate::where('workspace_id',$workspace->id)->where('document_type',$type)->where('language','en')->first();
                if($existing) continue;
                $template=app(DocumentTemplateService::class)->create($workspace,$owner,['name'=>DocumentTemplateCatalog::TYPES[$type].' · Default','document_type'=>$type,'language'=>'en','content_schema'=>DocumentTemplateCatalog::defaultSchema($type)]);
                if(in_array($type,['invoice','client_report','payslip','billing_invoice'],true)) app(DocumentTemplateService::class)->setDefault($template);
            }
        });
    }

    /** Handles the ensure role permissions operation for the current WorkIntel workflow. */ private function ensureRolePermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) return;
        $ids = DB::table('permissions')->whereIn('slug', ['documents.view','documents.generate','documents.manage','documents.templates_manage','documents.share','documents.sign','documents.approve','documents.components_manage'])->pluck('id', 'slug');
        $map = [
            'owner' => ['documents.view','documents.generate','documents.manage','documents.templates_manage','documents.share','documents.sign','documents.approve','documents.components_manage'],
            'admin' => ['documents.view','documents.generate','documents.manage','documents.templates_manage','documents.share','documents.sign','documents.approve','documents.components_manage'],
            'hr' => ['documents.view','documents.generate','documents.share','documents.sign','documents.approve'],
            'payroll-manager' => ['documents.view','documents.generate','documents.share','documents.sign','documents.approve'],
            'manager' => ['documents.view','documents.sign'],
            'team-lead' => ['documents.view','documents.sign'],
        ];
        $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
        foreach (DB::table('roles')->where('status', 'active')->get(['id','slug']) as $role) {
            foreach ($map[$role->slug] ?? [] as $slug) {
                $permissionId = $ids[$slug] ?? null;
                if (! $permissionId) continue;
                $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }
}
