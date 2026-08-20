<?php

namespace App\Services\Access;

/** Provides role template catalog behavior within the WorkIntel application. */ final class RoleTemplateCatalog
{
    /** @return array<string,array{name:string,description:string,permissions:array<int,string>,scopes:array<string,array{scope_type:string,scope_ids:array<int,int>}>,modules:array<string,string>}> */
    /** Handles the all operation for the current WorkIntel workflow. */ public static function all(): array
    {
        return [
            'project-coordinator' => [
                'name' => 'Project Coordinator',
                'description' => 'Coordinates assigned projects and team tasks without company-wide people or payroll access.',
                'permissions' => ['projects.view_assigned','tasks.view_team','tasks.manage_team','time.view_own','time.view_team','reports.view','approvals.view_own','approvals.review'],
                'scopes' => [
                    'people' => ['scope_type' => 'team', 'scope_ids' => []],
                    'projects' => ['scope_type' => 'team', 'scope_ids' => []],
                    'tasks' => ['scope_type' => 'team', 'scope_ids' => []],
                ],
                'modules' => [],
            ],
            'finance-analyst' => [
                'name' => 'Finance Analyst',
                'description' => 'Read-focused finance role for reports, expenses, job costing and payroll summaries.',
                'permissions' => ['reports.view','expenses.view_team','procurement.view','job_costing.view','payroll.view_all','intelligence.view_all'],
                'scopes' => ['people' => ['scope_type' => 'workspace', 'scope_ids' => []]],
                'modules' => [],
            ],
            'field-supervisor' => [
                'name' => 'Field Supervisor',
                'description' => 'Manages field work, team attendance and assigned project tasks.',
                'permissions' => ['people.view_team','projects.view_assigned','tasks.view_team','tasks.manage_team','attendance.view_team','scheduling.view_team','field.view_team','field.manage','field.incidents.manage','reports.view'],
                'scopes' => [
                    'people' => ['scope_type' => 'team', 'scope_ids' => []],
                    'projects' => ['scope_type' => 'team', 'scope_ids' => []],
                    'tasks' => ['scope_type' => 'team', 'scope_ids' => []],
                    'field' => ['scope_type' => 'team', 'scope_ids' => []],
                ],
                'modules' => [],
            ],
            'read-only-auditor' => [
                'name' => 'Read-only Auditor',
                'description' => 'Company-wide reporting and audit visibility without mutation permissions.',
                'permissions' => ['people.view_all','organization.view','projects.view_all','tasks.view_all','time.view_all','attendance.view_team','reports.view','security.audit.view','approvals.audit','intelligence.view_all'],
                'scopes' => [
                    'people' => ['scope_type' => 'workspace', 'scope_ids' => []],
                    'projects' => ['scope_type' => 'workspace', 'scope_ids' => []],
                    'tasks' => ['scope_type' => 'workspace', 'scope_ids' => []],
                ],
                'modules' => [],
            ],
        ];
    }
}
