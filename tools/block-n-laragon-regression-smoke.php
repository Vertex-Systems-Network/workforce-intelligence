<?php

/** Guard the root fixes identified by the 2026-08-14 Laragon full-suite report. */
$root = dirname(__DIR__);
$checks = [
    'member enum + enrollment' => ['app/Enums/MemberStatus.php', ["Inactive = 'inactive'"]],
    'member active normalization' => ['app/Models/WorkspaceMember.php', ['function isActive()', 'MemberStatus::Active']],
    'agent enrollment enum safety' => ['app/Services/Installation/AgentEnrollmentService.php', ['->isActive()']],
    'approval delegation default' => ['app/Http/Controllers/Api/V1/ApprovalController.php', ["\$data['delegator_member_id']??null"]],
    'API JSON validation' => ['bootstrap/app.php', ['shouldRenderJsonWhen', "is('api/*')"]],
    'scheduling time parsing' => ['app/Http/Controllers/Api/V1/SchedulingController.php', ['shiftTime', "'H:i:s' : 'H:i'"]],
    'intelligence time parsing' => ['app/Services/Intelligence/WorkforceIntelligenceService.php', ['shiftTime', "'H:i:s' : 'H:i'"]],
    'release catalog closure' => ['app/Http/Controllers/Api/V1/ReleaseController.php', ['use ($catalog)']],
    'legacy access-control payload' => ['app/Http/Controllers/Api/V1/AccessControlController.php', ["'permission_slugs'=>'sometimes|array'", 'array_fill_keys']],
    'nested platform entitlements' => ['app/Http/Controllers/Api/V1/PlatformController.php', ['Arr::undot', '$flatEntitlements']],
    'locale header preservation' => ['app/Http/Middleware/ApplyRequestLocale.php', ["headers->has('Content-Language')"]],
    'chat viewer role' => ['app/Http/Controllers/Api/V1/ChatController.php', ["viewer_role", "'member'"]],
    'remote provider invalid credentials' => ['app/Services/ClientPortal/ClientPaymentGatewayService.php', ['sk_(?:test|live)', 'invalid|example|changeme|replace']],
    'task status resolution' => ['app/Http/Controllers/Api/V1/TaskController.php', ["array_key_exists('status'", 'task_status_id']],
    'task item validation key' => ['app/Http/Controllers/Api/V1/TaskCollaborationController.php', ["assignee_ids.'", 'ValidationException']],
    'owner work-scope bypass' => ['app/Services/Access/WorkScopeService.php', ['projects.manage', 'tasks.manage']],
    'semantic migration history' => ['database/migrations/2026_08_14_000700_normalize_legacy_migration_history.php', ['repair_phase18_23_role_permissions', 'repair_phase23_retention_and_scim']],
    'document pure validation' => ['app/Services/Documents/DocumentExpressionEngine.php', ['ValidatorFactory', 'private function invalid']],
    'document dropdown API' => ['resources/js/pages/Documents.tsx', ['<Dropdown', 'items={[']],
    'activity tone typing' => ['resources/js/pages/Activity.tsx', ['] as const).map']],
    'final finance hierarchy' => ['database/seeders/DemoWorkspaceSeeder.php', ["'employee@acme.test'", '$james']],
    'workspace locale early resolution' => ['app/Http/Middleware/ApplyRequestLocale.php', ["header('X-Workspace-Id')", "with('workspace.preferences')"]],
    'repeatable platform departments' => ['app/Services/Platform/IndustryTemplateService.php', ["orWhere('name', \$row['name'])", 'if ($department)']],
    'scheduling approval capture' => ['app/Http/Controllers/Api/V1/SchedulingController.php', ['use($request,$swap,$data,$workspace,$approvals)']],
    'timer consistency before scope' => ['app/Http/Controllers/Api/V1/TimerController.php', ['selected task does not belong to the selected project', 'if ($project) abort_unless']],
    'release version contract' => ['tests/Feature/ProductionReleaseFlowTest.php', ["config('workintel.agent.latest_version')"]],
    'custom domain plan fixture' => ['tests/Feature/WebsitePortalBuilderFlowTest.php', ["where('slug', 'platinum')", 'subscription_plan_id']],
    'document tabs API' => ['resources/js/pages/Documents.tsx', ['<Tabs value={tab}', ' tabs={[', '<Tabs value={inspectorTab}']],
];

$errors = [];
foreach ($checks as $name => [$relative, $needles]) {
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        $errors[] = "{$name}: missing {$relative}";
        continue;
    }
    $source = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) $errors[] = "{$name}: {$relative} missing {$needle}";
    }
}

$forbiddenMigrationNames = array_filter(glob($root.'/database/migrations/*.php') ?: [], static fn (string $file): bool => (bool) preg_match('/(?:^|_)(?:(?:phase|milestone|block)[0-9_\-]*|[pm][0-9]+(?:_|\.|$))/i', basename($file)));
foreach ($forbiddenMigrationNames as $file) $errors[] = 'stage-coded migration filename: '.basename($file);

if ($errors) {
    fwrite(STDERR, "Block N Laragon regression smoke FAILED\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "Block N Laragon regression smoke PASS — shared roots for the reported Laragon failures are guarded and migration filenames are semantic.\n";
