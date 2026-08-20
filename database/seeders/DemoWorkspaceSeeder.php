<?php

namespace Database\Seeders;

use App\Services\Approvals\ApprovalEngine;

use App\Enums\MemberStatus;
use App\Models\Client;
use App\Services\ClientPortal\ClientReportService;
use App\Services\ClientPortal\ClientInvoiceService;
use App\Models\ClientReport;
use App\Models\ClientInvoice;
use App\Models\ClientPortalAccount;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Attendance\AttendancePolicyService;
use App\Models\PayrollRun;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAction;
use App\Models\CompensationProfile;
use App\Models\ActivityTrackingSetting;
use App\Models\Screenshot;
use App\Models\SavedReport;
use App\Models\ReportSchedule;
use App\Services\Reporting\ReportScheduleService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Str;
use App\Models\ScreenshotSetting;
use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\ProductivityRule;
use App\Models\TrackingExclusion;
use App\Models\WebsiteSession;
use App\Models\Department;
use App\Models\Device;
use App\Models\AgentEvent;
use App\Models\AgentSyncBatch;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\JobTitle;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\SchedulingSetting;
use App\Models\MemberAvailability;
use App\Models\OpenShift;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskRecurrence;
use App\Models\TaskComment;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\SecurityEvent;
use App\Models\WorkspaceNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

/** Provides demo workspace seeder behavior within the WorkIntel application. */ class DemoWorkspaceSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        DB::transaction(function () {
            $owner = $this->user('Sarah', 'Chen', 'owner@acme.test', 'Asia/Dubai');

            $workspace = Workspace::updateOrCreate(
                ['slug' => 'acme-corp'],
                [
                    'owner_id' => $owner->id,
                    'name' => 'Acme Corp',
                    'timezone' => 'Asia/Dubai',
                    'currency' => 'USD',
                    'country' => 'AE',
                    'week_starts_on' => 1,
                    'status' => 'active',
                ]
            );

            if (Schema::hasTable('attendance_policies')) app(AttendancePolicyService::class)->policy($workspace);

            $departments = collect([
                'Engineering' => 'ENG',
                'Design' => 'DSN',
                'Analytics' => 'ANL',
                'Infrastructure' => 'INF',
                'People Operations' => 'HR',
                'Finance' => 'FIN',
            ])->mapWithKeys(function (string $code, string $name) use ($workspace) {
                $department = Department::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $name],
                    ['code' => $code]
                );

                return [$name => $department];
            });

            $roles = $this->roles($workspace->id);

            $sarah = $this->member($workspace, $owner, 'SC-001', 'Engineering Manager', $departments['Engineering'], null, '2020-06-10');
            $sarah->roles()->sync([$roles['owner']->id]);

            $jamesUser = $this->user('James', 'Liu', 'manager@acme.test');
            $james = $this->member($workspace, $jamesUser, 'JL-002', 'Engineering Manager', $departments['Engineering'], $sarah, '2021-02-15');
            $james->roles()->sync([$roles['manager']->id]);

            $adminUser = $this->user('Olivia', 'Brooks', 'admin@acme.test');
            $adminMember = $this->member($workspace, $adminUser, 'OB-010', 'Workspace Administrator', $departments['People Operations'], $sarah, '2021-06-01');
            $adminMember->roles()->sync([$roles['admin']->id]);
            $hrUser = $this->user('Nadia', 'Rahman', 'hr@acme.test');
            $hrMember = $this->member($workspace, $hrUser, 'NR-011', 'HR Manager', $departments['People Operations'], $sarah, '2022-01-10');
            $hrMember->roles()->sync([$roles['hr']->id]);
            $leadUser = $this->user('Omar', 'Saleh', 'teamlead@acme.test');
            $leadMember = $this->member($workspace, $leadUser, 'OS-012', 'Engineering Team Lead', $departments['Engineering'], $james, '2022-04-18');
            $leadMember->roles()->sync([$roles['team-lead']->id]);
            $payrollUser = $this->user('Maya', 'Patel', 'payroll@acme.test');
            $payrollMember = $this->member($workspace, $payrollUser, 'MP-013', 'Payroll Manager', $departments['Finance'], $sarah, '2021-09-20');
            $payrollMember->roles()->sync([$roles['payroll-manager']->id]);

            $people = [
                ['Ahmed', 'Khan', 'employee@acme.test', 'AK-003', 'Frontend Developer', 'Engineering', $james, '2023-03-15'],
                ['Priya', 'Sharma', 'priya@acme.test', 'PS-004', 'Product Designer', 'Design', $sarah, '2022-08-01'],
                ['Marcus', 'Webb', 'marcus@acme.test', 'MW-005', 'Backend Engineer', 'Engineering', $james, '2021-11-20'],
                ['Jordan', 'Lee', 'jordan@acme.test', 'JD-006', 'QA Engineer', 'Engineering', $james, '2023-01-09'],
                ['Mei', 'Tanaka', 'mei@acme.test', 'MT-007', 'Data Analyst', 'Analytics', $sarah, '2022-02-14'],
                ['Liam', "O'Brien", 'liam@acme.test', 'LO-008', 'DevOps Engineer', 'Infrastructure', $james, '2022-05-30'],
                ['Fatima', 'Al-Hassan', 'fatima@acme.test', 'FA-009', 'UX Researcher', 'Design', $sarah, '2023-07-01'],
            ];

            $members = ['Sarah Chen' => $sarah, 'James Liu' => $james, 'Olivia Brooks' => $adminMember, 'Nadia Rahman' => $hrMember, 'Omar Saleh' => $leadMember, 'Maya Patel' => $payrollMember];

            foreach ($people as [$first, $last, $email, $code, $title, $department, $manager, $joined]) {
                $user = $this->user($first, $last, $email);
                $member = $this->member($workspace, $user, $code, $title, $departments[$department], $manager, $joined);
                $member->roles()->sync([$roles['employee']->id]);
                $members[$first.' '.$last] = $member;
            }

            $engineeringTeam = Team::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Product Engineering'],
                ['department_id' => $departments['Engineering']->id, 'lead_id' => $james->id, 'code' => 'ENG-PROD', 'description' => 'Core product engineering team.', 'status' => 'active']
            );
            $engineeringTeam->members()->sync([$james->id, $leadMember->id, $members['Ahmed Khan']->id, $members['Marcus Webb']->id, $members['Jordan Lee']->id]);

            $designTeam = Team::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Experience Design'],
                ['department_id' => $departments['Design']->id, 'lead_id' => $sarah->id, 'code' => 'UX', 'description' => 'Product design and research team.', 'status' => 'active']
            );
            $designTeam->members()->sync([$sarah->id, $members['Priya Sharma']->id, $members['Fatima Al-Hassan']->id]);

            $ahmedDevice = Device::updateOrCreate(
                ['workspace_id' => $workspace->id, 'installation_id' => 'demo-ahmed-xps15'],
                [
                    'uuid' => '11111111-1111-4111-8111-111111111111',
                    'member_id' => $members['Ahmed Khan']->id,
                    'name' => 'AHMED-XPS15',
                    'platform' => 'windows',
                    'os_name' => 'Windows 11',
                    'os_version' => '24H2',
                    'architecture' => 'x64',
                    'agent_version' => '0.1.0',
                    'status' => 'active',
                    'tracking_status' => 'active',
                    'is_idle' => false,
                    'offline_queue_size' => 0,
                    'metadata' => ['current_app' => 'Visual Studio Code', 'current_domain' => 'github.com', 'activity_percent' => 82],
                    'capabilities' => ['heartbeat', 'offline_sync', 'commands', 'app_tracking', 'domain_tracking'],
                    'last_ip' => '10.0.0.24',
                    'enrolled_at' => now()->subDays(22),
                    'last_heartbeat_at' => now()->subSeconds(18),
                    'last_seen_at' => now()->subSeconds(18),
                    'last_sync_at' => now()->subSeconds(45),
                    'revoked_at' => null,
                ]
            );
            $priyaDevice = Device::updateOrCreate(
                ['workspace_id' => $workspace->id, 'installation_id' => 'demo-priya-macbook'],
                [
                    'uuid' => '22222222-2222-4222-8222-222222222222',
                    'member_id' => $members['Priya Sharma']->id,
                    'name' => 'Priya MacBook Pro',
                    'platform' => 'macos',
                    'os_name' => 'macOS',
                    'os_version' => '15.5',
                    'architecture' => 'arm64',
                    'agent_version' => '0.0.9',
                    'status' => 'active',
                    'tracking_status' => 'active',
                    'is_idle' => false,
                    'offline_queue_size' => 0,
                    'metadata' => ['current_app' => 'Zoom Workplace', 'activity_percent' => 54],
                    'capabilities' => ['heartbeat', 'offline_sync', 'commands'],
                    'last_ip' => '10.0.0.31',
                    'enrolled_at' => now()->subDays(12),
                    'last_heartbeat_at' => now()->subSeconds(24),
                    'last_seen_at' => now()->subSeconds(24),
                    'last_sync_at' => now()->subMinutes(1),
                    'revoked_at' => null,
                ]
            );
            $marcusDevice = Device::updateOrCreate(
                ['workspace_id' => $workspace->id, 'installation_id' => 'demo-marcus-workstation'],
                [
                    'uuid' => '55555555-5555-4555-8555-555555555555',
                    'member_id' => $members['Marcus Webb']->id,
                    'name' => 'Marcus Workstation',
                    'platform' => 'windows', 'os_name' => 'Windows 11', 'os_version' => '24H2', 'architecture' => 'x64',
                    'agent_version' => '0.1.0', 'status' => 'active', 'tracking_status' => 'active', 'is_idle' => true,
                    'offline_queue_size' => 0, 'capabilities' => ['heartbeat','offline_sync','commands','app_tracking'],
                    'metadata' => ['current_app' => 'Visual Studio Code', 'activity_percent' => 18],
                    'last_ip' => '10.0.0.41', 'enrolled_at' => now()->subDays(9), 'last_heartbeat_at' => now()->subSeconds(31),
                    'last_seen_at' => now()->subSeconds(31), 'last_sync_at' => now()->subMinutes(2), 'revoked_at' => null,
                ]
            );
            AgentEvent::updateOrCreate(
                ['device_id' => $ahmedDevice->id, 'event_uuid' => '33333333-3333-4333-8333-333333333333'],
                ['workspace_id' => $workspace->id, 'member_id' => $members['Ahmed Khan']->id, 'event_type' => 'agent.started', 'occurred_at' => now()->subHours(4), 'payload' => ['reason' => 'login'], 'received_at' => now()->subHours(4)]
            );
            AgentSyncBatch::updateOrCreate(
                ['device_id' => $ahmedDevice->id, 'batch_uuid' => '44444444-4444-4444-8444-444444444444'],
                ['workspace_id' => $workspace->id, 'event_count' => 1, 'accepted_count' => 1, 'duplicate_count' => 0, 'client_created_at' => now()->subHours(4), 'received_at' => now()->subHours(4)]
            );

            $techCorp = Client::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'TechCorp Inc.'],
                ['company_name' => 'TechCorp Inc.', 'email' => 'accounts@techcorp.test', 'billing_email' => 'billing@techcorp.test', 'phone' => '+1 555 010 2000', 'billing_address' => '100 Market Street, San Francisco, CA', 'tax_id' => 'TC-2026-001', 'currency' => 'USD', 'billing_rate' => 140, 'status' => 'active']
            );

            $dataFlow = Client::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'DataFlow Ltd.'],
                ['company_name' => 'DataFlow Ltd.', 'email' => 'finance@dataflow.test', 'billing_email' => 'finance@dataflow.test', 'billing_address' => '42 Data Avenue, London', 'tax_id' => 'DF-7788', 'currency' => 'USD', 'billing_rate' => 125, 'status' => 'active']
            );

            $platform = $this->project($workspace, null, $owner, 'WorkIntel Platform', 'WIP', '2026-09-30', 400, false);
            $design = $this->project($workspace, null, $owner, 'Design System', 'DS', '2026-10-15', 250, false);
            $api = $this->project($workspace, $techCorp, $owner, 'API Platform', 'API', '2026-08-31', 500, true);
            $analytics = $this->project($workspace, $dataFlow, $owner, 'Analytics Pipeline', 'ANL', '2026-11-01', 160, true);
            $opsMigration = $this->project($workspace, null, $owner, 'Infrastructure Migration', 'OPS-MIG', '2026-08-09', 80, false);
            $opsMigration->update(['status' => 'completed', 'completed_at' => '2026-08-09 17:30:00']);
            $opsMigration->members()->sync([
                $members["Liam O'Brien"]->id => ['role' => 'DevOps Engineer', 'hourly_cost' => 90, 'billing_rate' => null],
            ]);

            $platform->members()->sync([
                $members['Ahmed Khan']->id => ['role' => 'Developer', 'hourly_cost' => 85, 'billing_rate' => 125],
                $members['Priya Sharma']->id => ['role' => 'Designer', 'hourly_cost' => 75, 'billing_rate' => 115],
                $sarah->id => ['role' => 'Manager', 'hourly_cost' => 120, 'billing_rate' => 160],
                $members['Jordan Lee']->id => ['role' => 'QA', 'hourly_cost' => 65, 'billing_rate' => 95],
            ]);

            $api->members()->sync([
                $members['Marcus Webb']->id => ['role' => 'Backend Engineer', 'hourly_cost' => 95, 'billing_rate' => 150],
                $james->id => ['role' => 'Manager', 'hourly_cost' => 125, 'billing_rate' => 170],
            ]);

            $analytics->members()->sync([
                $members['Mei Tanaka']->id => ['role' => 'Data Analyst', 'hourly_cost' => 80, 'billing_rate' => 125],
            ]);

            $tasks = [
                $this->task($workspace, $platform, $owner, 'Animation Timeline Editor', 'in_progress', 'high', 720, '2026-08-14', true, [$members['Ahmed Khan']]),
                $this->task($workspace, $platform, $owner, 'Motion Editor Fixes', 'review', 'medium', 360, '2026-08-12', true, [$members['Ahmed Khan']]),
                $this->task($workspace, $design, $owner, 'Component Library v2', 'in_progress', 'high', 900, '2026-08-20', false, [$members['Priya Sharma']]),
                $this->task($workspace, $api, $owner, 'Auth Service Refactor', 'in_progress', 'critical', 1200, '2026-08-16', true, [$members['Marcus Webb']]),
                $this->task($workspace, $platform, $owner, 'Integration Tests', 'todo', 'medium', 480, '2026-08-18', true, [$members['Jordan Lee']]),
                $this->task($workspace, $analytics, $owner, 'Dashboard Metrics ETL', 'in_progress', 'high', 960, '2026-08-21', true, [$members['Mei Tanaka']]),
            ];

            $api->update(['client_visible' => true]);
            $analytics->update(['client_visible' => true]);
            $tasks[3]->update(['client_visible' => true]);
            $tasks[5]->update(['client_visible' => true]);

            TaskDependency::updateOrCreate(
                ['task_id' => $tasks[4]->id, 'depends_on_task_id' => $tasks[1]->id],
                ['workspace_id' => $workspace->id, 'type' => 'finish_to_start']
            );
            TaskRecurrence::updateOrCreate(
                ['task_id' => $tasks[5]->id],
                ['workspace_id' => $workspace->id, 'frequency' => 'weekly', 'interval' => 1, 'starts_on' => '2026-08-17', 'ends_on' => '2026-12-31', 'next_run_at' => '2026-08-17 09:00:00', 'active' => true]
            );

            $this->timeEntry($workspace->id, $members['Ahmed Khan']->id, $platform->id, $tasks[0]->id, '2026-08-10 09:02:00', '2026-08-10 11:16:00', true);
            $this->timeEntry($workspace->id, $members['Ahmed Khan']->id, $platform->id, $tasks[1]->id, '2026-08-10 11:20:00', '2026-08-10 12:45:00', true);
            $this->timeEntry($workspace->id, $members['Priya Sharma']->id, $design->id, $tasks[2]->id, '2026-08-10 09:15:00', '2026-08-10 12:55:00', false);
            $this->timeEntry($workspace->id, $members['Marcus Webb']->id, $api->id, $tasks[3]->id, '2026-08-10 08:30:00', '2026-08-10 10:18:00', true);

            foreach ([
                [$sarah, 'monthly', ['monthly_salary' => 9000, 'premium_hourly_rate' => 52]],
                [$james, 'monthly', ['monthly_salary' => 7800, 'premium_hourly_rate' => 45]],
                [$members['Ahmed Khan'], 'monthly', ['monthly_salary' => 6500, 'premium_hourly_rate' => 38]],
                [$members['Priya Sharma'], 'monthly', ['monthly_salary' => 5800, 'premium_hourly_rate' => 34]],
                [$members['Marcus Webb'], 'hourly', ['hourly_rate' => 42]],
                [$members['Jordan Lee'], 'yearly', ['annual_salary' => 72000, 'premium_hourly_rate' => 35]],
                [$members['Mei Tanaka'], 'monthly', ['monthly_salary' => 6200, 'premium_hourly_rate' => 36]],
                [$members["Liam O'Brien"], 'project', ['project_rate' => 2400, 'premium_hourly_rate' => 45]],
                [$members['Fatima Al-Hassan'], 'daily', ['daily_rate' => 280, 'premium_hourly_rate' => 35]],
            ] as [$member, $payType, $rates]) {
                CompensationProfile::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'effective_from' => '2026-01-01'],
                    [
                        'pay_type' => $payType,
                        'currency' => $workspace->currency,
                        'hourly_rate' => $rates['hourly_rate'] ?? null,
                        'daily_rate' => $rates['daily_rate'] ?? null,
                        'monthly_salary' => $rates['monthly_salary'] ?? null,
                        'annual_salary' => $rates['annual_salary'] ?? null,
                        'project_rate' => $rates['project_rate'] ?? null,
                        'premium_hourly_rate' => $rates['premium_hourly_rate'] ?? null,
                        'standard_hours_per_day' => 8,
                        'standard_hours_per_week' => 40,
                        'overtime_multiplier' => 1.5,
                        'weekend_multiplier' => 1.5,
                        'holiday_multiplier' => 2,
                        'default_tax_percent' => 0,
                        'deduct_unpaid_leave' => true,
                        'proration_mode' => 'calendar_days',
                        'effective_to' => null,
                        'status' => 'active',
                    ]
                );
            }

            foreach (['2026-08-10', '2026-08-11'] as $date) {
                AttendanceRecord::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'member_id' => $members['Fatima Al-Hassan']->id, 'date' => $date],
                    ['clock_in_at' => $date.' 09:00:00', 'clock_out_at' => $date.' 18:00:00', 'break_seconds' => 3600, 'worked_seconds' => 28800, 'late_minutes' => 0, 'overtime_minutes' => 0, 'status' => 'present', 'source' => 'web']
                );
            }
            AttendanceRecord::updateOrCreate(
                ['workspace_id' => $workspace->id, 'member_id' => $members['Marcus Webb']->id, 'date' => '2026-08-10'],
                ['clock_in_at' => '2026-08-10 08:30:00', 'clock_out_at' => '2026-08-10 18:30:00', 'break_seconds' => 3600, 'worked_seconds' => 32400, 'late_minutes' => 0, 'overtime_minutes' => 60, 'status' => 'present', 'source' => 'web']
            );

            $payrollRun = PayrollRun::updateOrCreate(
                ['uuid' => '99999999-9999-4999-8999-999999999999'],
                ['workspace_id' => $workspace->id, 'name' => 'August 2026 Payroll', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'pay_date' => '2026-09-05', 'currency' => $workspace->currency, 'status' => 'draft', 'calculated_at' => null, 'submitted_at' => null, 'submitted_by' => null, 'approved_at' => null, 'approved_by' => null, 'paid_at' => null, 'paid_by' => null, 'locked_at' => null, 'locked_by' => null, 'note' => 'Demo payroll run. Tax values are configurable withholding, not jurisdiction tax advice.']
            );
            app(PayrollCalculator::class)->calculate($payrollRun);

            $sarahPayrollItem = $payrollRun->items()->where('member_id', $sarah->id)->first();
            if ($sarahPayrollItem) {
                PayrollAdjustment::updateOrCreate(
                    ['payroll_item_id' => $sarahPayrollItem->id, 'label' => 'Performance bonus'],
                    ['workspace_id' => $workspace->id, 'category' => 'bonus', 'direction' => 'earning', 'amount' => 500, 'source' => 'manual', 'created_by' => $owner->id]
                );
                PayrollAdjustment::updateOrCreate(
                    ['payroll_item_id' => $sarahPayrollItem->id, 'label' => 'Configured withholding'],
                    ['workspace_id' => $workspace->id, 'category' => 'tax', 'direction' => 'deduction', 'amount' => 300, 'source' => 'manual', 'created_by' => $owner->id]
                );
                app(PayrollCalculator::class)->recalculateItemTotals($sarahPayrollItem);
            }
            PayrollAction::updateOrCreate(
                ['payroll_run_id' => $payrollRun->id, 'action' => 'calculated'],
                ['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'from_status' => 'draft', 'to_status' => 'calculated', 'note' => 'Demo payroll calculated from seeded compensation and work data.', 'occurred_at' => now()]
            );

            ActivityTrackingSetting::updateOrCreate(
                ['workspace_id' => $workspace->id],
                ['application_tracking_enabled' => true, 'website_tracking_enabled' => true, 'capture_window_titles' => false, 'capture_page_titles' => false, 'store_full_urls' => false, 'minimum_session_seconds' => 5, 'idle_threshold_seconds' => 300]
            );

            ScreenshotSetting::updateOrCreate(
                ['workspace_id' => $workspace->id],
                ['enabled' => true, 'interval_minutes' => 10, 'randomize_minutes' => 2, 'capture_all_monitors' => true, 'blur_by_default' => false, 'quality' => 'medium', 'allow_employee_delete' => false, 'retention_days' => 90, 'max_upload_kb' => 4096]
            );

            $demoScreenshots = [
                ['77777777-0001-4000-8000-000000000001', $members['Ahmed Khan'], $ahmedDevice, $platform, $tasks[0], 'Visual Studio Code', 88, 1, now()->subMinutes(8), '#5B8DEF'],
                ['77777777-0002-4000-8000-000000000002', $members['Priya Sharma'], $priyaDevice, $design, $tasks[2], 'Figma', 92, 1, now()->subMinutes(11), '#8B5CF6'],
                ['77777777-0003-4000-8000-000000000003', $members['Ahmed Khan'], $ahmedDevice, $platform, $tasks[1], 'Google Chrome', 74, 2, now()->subMinutes(18), '#22C55E'],
            ];
            foreach ($demoScreenshots as [$uuid, $member, $device, $project, $task, $appName, $activity, $monitor, $capturedAt, $accent]) {
                $path = 'screenshots/'.$workspace->id.'/demo/'.$uuid.'.svg';
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720"><rect width="1280" height="720" fill="#111827"/><rect x="38" y="36" width="1204" height="648" rx="18" fill="#172033"/><rect x="38" y="36" width="1204" height="54" rx="18" fill="#202B40"/><circle cx="74" cy="63" r="7" fill="#EF4444"/><circle cx="98" cy="63" r="7" fill="#F59E0B"/><circle cx="122" cy="63" r="7" fill="#22C55E"/><rect x="80" y="136" width="760" height="18" rx="6" fill="'.$accent.'" opacity=".72"/><rect x="80" y="184" width="1040" height="12" rx="6" fill="#344258"/><rect x="80" y="219" width="900" height="12" rx="6" fill="#344258"/><rect x="80" y="254" width="970" height="12" rx="6" fill="#344258"/><rect x="80" y="326" width="430" height="250" rx="12" fill="#202B40"/><rect x="548" y="326" width="572" height="250" rx="12" fill="#202B40"/><text x="82" y="636" fill="#94A3B8" font-family="Arial" font-size="28">'.htmlspecialchars($appName, ENT_QUOTES).'</text></svg>';
                Storage::disk('local')->put($path, $svg);
                Screenshot::updateOrCreate(
                    ['uuid' => $uuid],
                    ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'device_id' => $device->id, 'project_id' => $project->id, 'task_id' => $task->id, 'disk' => 'local', 'path' => $path, 'mime_type' => 'image/svg+xml', 'size_bytes' => strlen($svg), 'width' => 1280, 'height' => 720, 'monitor_index' => $monitor, 'app_name' => $appName, 'activity_percent' => $activity, 'blurred' => false, 'flagged' => false, 'captured_at' => $capturedAt]
                );
            }

            $activityRules = [
                ['app', 'code.exe', 'productive', 'Development'],
                ['app', 'figma', 'productive', 'Design'],
                ['domain', 'github.com', 'productive', 'Development'],
                ['domain', 'figma.com', 'productive', 'Design'],
                ['domain', 'slack.com', 'productive', 'Communication'],
                ['domain', 'youtube.com', 'neutral', 'Media'],
                ['domain', 'facebook.com', 'unproductive', 'Social'],
            ];
            foreach ($activityRules as [$targetType, $target, $classification, $category]) {
                ProductivityRule::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'scope_type' => 'workspace', 'scope_id' => null, 'target_type' => $targetType, 'target' => $target],
                    ['classification' => $classification, 'category' => $category, 'active' => true, 'created_by' => $owner->id]
                );
            }
            TrackingExclusion::updateOrCreate(
                ['workspace_id' => $workspace->id, 'scope_type' => 'workspace', 'scope_id' => null, 'target_type' => 'domain', 'pattern' => 'bank.example'],
                ['reason' => 'Banking and financial privacy', 'active' => true, 'created_by' => $owner->id]
            );
            TrackingExclusion::updateOrCreate(
                ['workspace_id' => $workspace->id, 'scope_type' => 'workspace', 'scope_id' => null, 'target_type' => 'app', 'pattern' => '1password.exe'],
                ['reason' => 'Password manager', 'active' => true, 'created_by' => $owner->id]
            );

            $appSessions = [
                ['aaaaaaaa-0001-4000-8000-000000000001', $members['Ahmed Khan']->id, $ahmedDevice->id, $platform->id, $tasks[0]->id, 'code.exe', 'Visual Studio Code', 'Code.exe', '2026-08-10 09:02:00', '2026-08-10 10:12:00', 3900, 300],
                ['aaaaaaaa-0002-4000-8000-000000000002', $members['Ahmed Khan']->id, $ahmedDevice->id, $platform->id, $tasks[0]->id, 'chrome.exe', 'Google Chrome', 'chrome.exe', '2026-08-10 10:12:00', '2026-08-10 10:42:00', 1500, 300],
                ['aaaaaaaa-0003-4000-8000-000000000003', $members['Priya Sharma']->id, $priyaDevice->id, $design->id, $tasks[2]->id, 'figma', 'Figma', 'Figma', '2026-08-10 09:15:00', '2026-08-10 11:05:00', 6300, 300],
                ['aaaaaaaa-0004-4000-8000-000000000004', $members['Marcus Webb']->id, null, $api->id, $tasks[3]->id, 'code.exe', 'Visual Studio Code', 'Code.exe', '2026-08-10 08:30:00', '2026-08-10 10:00:00', 5000, 400],
            ];
            foreach ($appSessions as [$uuid, $memberId, $deviceId, $projectId, $taskId, $appKey, $appName, $processName, $started, $ended, $active, $idle]) {
                ApplicationSession::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'session_uuid' => $uuid],
                    ['member_id'=>$memberId,'device_id'=>$deviceId,'project_id'=>$projectId,'task_id'=>$taskId,'app_key'=>$appKey,'app_name'=>$appName,'process_name'=>$processName,'started_at'=>$started,'ended_at'=>$ended,'duration_seconds'=>Carbon::parse($started)->diffInSeconds(Carbon::parse($ended)),'active_seconds'=>$active,'idle_seconds'=>$idle,'source'=>'desktop_agent']
                );
            }

            $browserConnection = BrowserConnection::updateOrCreate(
                ['workspace_id' => $workspace->id, 'installation_id' => 'demo-ahmed-chrome'],
                ['uuid'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','member_id'=>$members['Ahmed Khan']->id,'device_id'=>$ahmedDevice->id,'browser_name'=>'Chrome','browser_version'=>'140','extension_version'=>'0.1.0','status'=>'active','enrolled_at'=>now()->subDays(4),'last_seen_at'=>now()->subMinutes(2),'last_sync_at'=>now()->subMinutes(3),'last_ip'=>'10.0.0.24','revoked_at'=>null]
            );
            $webSessions = [
                ['cccccccc-0001-4000-8000-000000000001', $members['Ahmed Khan']->id, $ahmedDevice->id, $browserConnection->id, $platform->id, $tasks[0]->id, 'github.com', 'Chrome', '2026-08-10 10:12:00', '2026-08-10 10:31:00', 1050, 90],
                ['cccccccc-0002-4000-8000-000000000002', $members['Ahmed Khan']->id, $ahmedDevice->id, $browserConnection->id, $platform->id, $tasks[0]->id, 'gsap.com', 'Chrome', '2026-08-10 10:31:00', '2026-08-10 10:42:00', 600, 60],
                ['cccccccc-0003-4000-8000-000000000003', $members['Priya Sharma']->id, $priyaDevice->id, null, $design->id, $tasks[2]->id, 'figma.com', 'Chrome', '2026-08-10 11:05:00', '2026-08-10 11:45:00', 2250, 150],
                ['cccccccc-0004-4000-8000-000000000004', $members['Marcus Webb']->id, null, null, $api->id, $tasks[3]->id, 'github.com', 'Chrome', '2026-08-10 10:00:00', '2026-08-10 10:18:00', 980, 100],
                ['cccccccc-0005-4000-8000-000000000005', $members['Ahmed Khan']->id, $ahmedDevice->id, $browserConnection->id, null, null, 'youtube.com', 'Chrome', '2026-08-10 12:10:00', '2026-08-10 12:24:00', 700, 140],
            ];
            foreach ($webSessions as [$uuid, $memberId, $deviceId, $browserId, $projectId, $taskId, $domain, $browser, $started, $ended, $active, $idle]) {
                WebsiteSession::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'session_uuid' => $uuid],
                    ['member_id'=>$memberId,'device_id'=>$deviceId,'browser_connection_id'=>$browserId,'project_id'=>$projectId,'task_id'=>$taskId,'domain'=>$domain,'browser_name'=>$browser,'started_at'=>$started,'ended_at'=>$ended,'duration_seconds'=>Carbon::parse($started)->diffInSeconds(Carbon::parse($ended)),'active_seconds'=>$active,'idle_seconds'=>$idle,'source'=>$browserId ? 'browser_extension' : 'desktop_agent']
                );
            }

            ProjectExpense::updateOrCreate(
                ['workspace_id' => $workspace->id, 'project_id' => $platform->id, 'name' => 'Browser testing service'],
                ['category' => 'software', 'amount' => 89, 'currency' => 'USD', 'incurred_on' => '2026-08-10', 'note' => 'Cross-browser regression testing.', 'created_by' => $owner->id]
            );

            TaskComment::updateOrCreate(
                ['task_id' => $tasks[0]->id, 'member_id' => $sarah->id, 'body' => 'Please keep the timeline controls keyboard accessible and test the collapsed panel state.']
            );
            $subtask = Task::updateOrCreate(
                ['workspace_id' => $workspace->id, 'project_id' => $platform->id, 'parent_id' => $tasks[0]->id, 'title' => 'Keyboard navigation pass'],
                ['status' => 'todo', 'priority' => 'medium', 'estimated_minutes' => 180, 'due_at' => '2026-08-13 18:00:00', 'billable' => true, 'created_by' => $owner->id]
            );
            $subtask->assignees()->sync([$members['Ahmed Khan']->id]);

            $dayShift = Shift::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Standard Day'],
                ['start_time' => '09:00', 'end_time' => '18:00', 'break_minutes' => 60, 'grace_minutes' => 10, 'location_type' => 'office', 'timezone' => $workspace->timezone, 'status' => 'active']
            );
            $remoteShift = Shift::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Remote Flex'],
                ['start_time' => '10:00', 'end_time' => '19:00', 'break_minutes' => 60, 'grace_minutes' => 15, 'location_type' => 'remote', 'timezone' => $workspace->timezone, 'status' => 'active']
            );

            if (Schema::hasTable('scheduling_settings')) {
                SchedulingSetting::updateOrCreate(['workspace_id' => $workspace->id], ['max_weekly_hours' => 48, 'overtime_warning_hours' => 40, 'minimum_rest_hours' => 11, 'daily_coverage_target' => 6, 'weekly_labor_budget' => 12000, 'currency' => $workspace->currency, 'allow_open_shift_claims' => true, 'allow_shift_swaps' => true]);
                MemberAvailability::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$members['Ahmed Khan']->id,'date'=>'2026-08-13'], ['status'=>'preferred','start_time'=>'09:00','end_time'=>'18:00','note'=>'Prefer office shift']);
                MemberAvailability::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$members['Priya Sharma']->id,'date'=>'2026-08-14'], ['status'=>'unavailable','start_time'=>null,'end_time'=>null,'note'=>'Personal appointment']);
                OpenShift::updateOrCreate(['workspace_id'=>$workspace->id,'shift_id'=>$dayShift->id,'date'=>'2026-08-13','note'=>'Product release coverage'], ['project_id'=>null,'slots'=>2,'claimed_slots'=>0,'work_mode'=>'office','status'=>'open','created_by'=>$owner->id]);
            }

            foreach ([$sarah, $james, $members['Ahmed Khan'], $members['Marcus Webb'], $members['Jordan Lee']] as $member) {
                ShiftAssignment::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'date' => '2026-08-11'],
                    ['shift_id' => $dayShift->id, 'work_mode' => 'office']
                );
            }
            foreach ([$members['Priya Sharma'], $members['Mei Tanaka'], $members['Fatima Al-Hassan']] as $member) {
                ShiftAssignment::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'date' => '2026-08-11'],
                    ['shift_id' => $remoteShift->id, 'work_mode' => 'remote']
                );
            }

            $ahmedAttendance = AttendanceRecord::updateOrCreate(
                ['workspace_id' => $workspace->id, 'member_id' => $members['Ahmed Khan']->id, 'date' => '2026-08-11'],
                ['shift_assignment_id' => ShiftAssignment::where('member_id', $members['Ahmed Khan']->id)->where('date', '2026-08-11')->value('id'), 'clock_in_at' => '2026-08-11 09:07:00', 'break_seconds' => 1800, 'status' => 'present', 'source' => 'web']
            );
            AttendanceBreak::updateOrCreate(
                ['attendance_record_id' => $ahmedAttendance->id, 'started_at' => '2026-08-11 12:30:00'],
                ['workspace_id' => $workspace->id, 'member_id' => $members['Ahmed Khan']->id, 'type' => 'lunch', 'paid' => false, 'ended_at' => '2026-08-11 13:00:00', 'duration_seconds' => 1800]
            );
            Holiday::updateOrCreate(
                ['workspace_id' => $workspace->id, 'date' => '2026-08-14', 'name' => 'Company Foundation Day'],
                ['type' => 'company', 'paid' => true, 'status' => 'active']
            );

            $annualLeave = LeaveType::updateOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'ANNUAL'],
                ['name' => 'Annual Leave', 'is_paid' => true, 'annual_allowance_days' => 20, 'status' => 'active']
            );
            $sickLeave = LeaveType::updateOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'SICK'],
                ['name' => 'Sick Leave', 'is_paid' => true, 'annual_allowance_days' => 10, 'status' => 'active']
            );
            $personalLeave = LeaveType::updateOrCreate(
                ['workspace_id' => $workspace->id, 'code' => 'PERSONAL'],
                ['name' => 'Personal Leave', 'is_paid' => false, 'annual_allowance_days' => 5, 'status' => 'active']
            );
            LeavePolicy::updateOrCreate(['leave_type_id' => $annualLeave->id], ['workspace_id' => $workspace->id, 'accrual_method' => 'annual', 'monthly_accrual_days' => 0, 'carryover_days' => 5, 'min_notice_days' => 3, 'max_consecutive_days' => 15, 'probation_months' => 3, 'allow_negative_balance' => false, 'requires_approval' => true, 'exclude_weekends' => true, 'exclude_holidays' => true]);
            LeavePolicy::updateOrCreate(['leave_type_id' => $sickLeave->id], ['workspace_id' => $workspace->id, 'accrual_method' => 'annual', 'monthly_accrual_days' => 0, 'carryover_days' => 0, 'min_notice_days' => 0, 'max_consecutive_days' => 10, 'probation_months' => 0, 'allow_negative_balance' => false, 'requires_approval' => true, 'exclude_weekends' => false, 'exclude_holidays' => false]);
            LeavePolicy::updateOrCreate(['leave_type_id' => $personalLeave->id], ['workspace_id' => $workspace->id, 'accrual_method' => 'monthly', 'monthly_accrual_days' => 0.5, 'carryover_days' => 0, 'min_notice_days' => 1, 'max_consecutive_days' => 3, 'probation_months' => 3, 'allow_negative_balance' => false, 'requires_approval' => true, 'exclude_weekends' => true, 'exclude_holidays' => true]);
            LeaveRequest::updateOrCreate(
                ['workspace_id' => $workspace->id, 'member_id' => $members['Ahmed Khan']->id, 'leave_type_id' => $annualLeave->id, 'start_date' => '2026-08-20'],
                ['end_date' => '2026-08-22', 'days' => 2, 'reason' => 'Family travel.', 'status' => 'pending']
            );
            LeaveRequest::updateOrCreate(
                ['workspace_id' => $workspace->id, 'member_id' => $members['Marcus Webb']->id, 'leave_type_id' => $sickLeave->id, 'start_date' => '2026-08-08'],
                ['end_date' => '2026-08-09', 'days' => 2, 'reason' => 'Medical leave.', 'status' => 'approved', 'reviewed_by' => $owner->id, 'reviewed_at' => '2026-08-07 16:00:00']
            );

            if (Schema::hasTable('approval_workflows')) {
                $approvalEngine = app(ApprovalEngine::class);
                $approvalEngine->ensureDefaultWorkflows($workspace, $owner->id);
                $pendingDemoLeave = LeaveRequest::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('member_id', $members['Ahmed Khan']->id)
                    ->where('status', 'pending')
                    ->first();
                if ($pendingDemoLeave) {
                    $approvalEngine->submitFor(
                        $workspace,
                        $members['Ahmed Khan'],
                        'leave.submitted',
                        'leave_request',
                        $pendingDemoLeave,
                        [
                            'department_id' => $members['Ahmed Khan']->department_id,
                            'leave_type_id' => $pendingDemoLeave->leave_type_id,
                            'days' => (float) $pendingDemoLeave->days,
                        ],
                        'Annual leave · Ahmed Khan',
                        'Demo pending leave request for the unified approval inbox.'
                    );
                }
            }


            $weeklyReport = SavedReport::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Weekly Team Summary'],
                [
                    'uuid' => (string) Str::uuid(), 'created_by' => $owner->id, 'description' => 'Tracked and billable time by employee.',
                    'dataset' => 'time_entries', 'is_shared' => true,
                    'configuration' => [
                        'dataset' => 'time_entries', 'date_preset' => 'this_week', 'date_from' => '2026-08-10', 'date_to' => '2026-08-16',
                        'dimensions' => ['employee'], 'metrics' => ['tracked_hours', 'billable_hours'], 'filters' => [],
                        'sort' => ['key' => 'tracked_hours', 'direction' => 'desc'], 'limit' => 5000,
                        'visualization' => ['type' => 'bar', 'x' => 'employee', 'y' => 'tracked_hours'],
                    ],
                ]
            );
            SavedReport::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Project Profitability'],
                [
                    'uuid' => (string) Str::uuid(), 'created_by' => $owner->id, 'description' => 'Tracked hours, revenue, expenses and profit by project.',
                    'dataset' => 'projects', 'is_shared' => true,
                    'configuration' => [
                        'dataset' => 'projects', 'date_preset' => 'custom', 'date_from' => '2026-07-01', 'date_to' => '2026-08-31',
                        'dimensions' => ['project', 'currency'], 'metrics' => ['tracked_hours', 'project_revenue', 'expenses', 'profit'], 'filters' => [],
                        'sort' => ['key' => 'profit', 'direction' => 'desc'], 'limit' => 5000,
                        'visualization' => ['type' => 'bar', 'x' => 'project', 'y' => 'profit'],
                    ],
                ]
            );
            SavedReport::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Attendance Overview'],
                [
                    'uuid' => (string) Str::uuid(), 'created_by' => $owner->id, 'description' => 'Worked hours, overtime and late minutes by employee.',
                    'dataset' => 'attendance', 'is_shared' => true,
                    'configuration' => [
                        'dataset' => 'attendance', 'date_preset' => 'this_month', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
                        'dimensions' => ['employee'], 'metrics' => ['worked_hours', 'overtime_hours', 'late_minutes'], 'filters' => [],
                        'sort' => ['key' => 'worked_hours', 'direction' => 'desc'], 'limit' => 5000,
                        'visualization' => ['type' => 'table', 'x' => 'employee', 'y' => 'worked_hours'],
                    ],
                ]
            );
            $scheduleService = app(ReportScheduleService::class);
            ReportSchedule::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => 'Monday Team Summary'],
                [
                    'uuid' => (string) Str::uuid(), 'saved_report_id' => $weeklyReport->id, 'created_by' => $owner->id,
                    'frequency' => 'weekly', 'time_of_day' => '08:00', 'day_of_week' => 1, 'day_of_month' => null,
                    'timezone' => $workspace->timezone ?: 'UTC', 'export_format' => 'pdf', 'active' => true,
                    'next_run_at' => $scheduleService->calculateNextRun('weekly', '08:00', $workspace->timezone ?: 'UTC', 1),
                ]
            );

            $subscriptionService = app(SubscriptionService::class);
            $demoSubscription = $subscriptionService->ensureDefault($workspace, 'free')->load('plan');
            if ($demoSubscription->plan->slug !== 'gold') {
                $demoSubscription = $subscriptionService->changePlan($workspace, 'gold', 'monthly', false);
                $openInvoice = $demoSubscription->invoices()->where('status', 'open')->latest()->first();
                if ($openInvoice) $subscriptionService->markInvoicePaid($openInvoice, 'DEMO-SEED-PAYMENT');
            }

            ClientPortalAccount::updateOrCreate(
                ['workspace_id' => $workspace->id, 'email' => 'client@techcorp.test'],
                ['client_id' => $techCorp->id, 'name' => 'Alex Morgan', 'password' => 'password', 'status' => 'active', 'activated_at' => now()->subDays(20)]
            );

            $clientInvoice = ClientInvoice::query()->where('workspace_id', $workspace->id)->where('client_id', $techCorp->id)->where('number', 'CLI-2026-00001')->first();
            if (! $clientInvoice) {
                $clientInvoice = app(ClientInvoiceService::class)->create($techCorp, [
                    'issue_date' => '2026-08-11', 'due_date' => '2026-08-25', 'period_start' => '2026-08-01', 'period_end' => '2026-08-11',
                    'tax_percent' => 5, 'discount_total' => 0, 'include_unbilled_time' => true, 'project_ids' => [$api->id],
                    'notes' => 'Approved engineering time for the API Platform project.', 'terms' => 'Payment due within 14 days.',
                ], $owner->id);
                $clientInvoice->update(['number' => 'CLI-2026-00001']);
                app(ClientInvoiceService::class)->send($clientInvoice);
            }

            if (! ClientReport::query()->where('workspace_id', $workspace->id)->where('client_id', $techCorp->id)->where('name', 'API Platform Progress — August 2026')->exists()) {
                app(ClientReportService::class)->generate($techCorp, [
                    'project_id' => $api->id, 'name' => 'API Platform Progress — August 2026', 'report_type' => 'project_progress',
                    'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'note' => 'Client-facing progress snapshot.', 'publish' => true,
                ], $owner->id);
            }

            if (Schema::hasTable('workspace_notifications')) {
                WorkspaceNotification::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'type' => 'report.scheduled_completed', 'title' => 'Weekly Team Summary ready'],
                    ['uuid' => (string) Str::uuid(), 'category' => 'reports', 'severity' => 'success', 'body' => 'The latest scheduled workforce summary is ready.', 'data' => ['seeded' => true], 'created_at' => now()->subMinutes(25)]
                );
                WorkspaceNotification::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'type' => 'device.health_warning', 'title' => 'Desktop agent needs attention'],
                    ['uuid' => (string) Str::uuid(), 'category' => 'agents', 'severity' => 'warning', 'body' => 'One seeded device has not synced recently.', 'data' => ['seeded' => true], 'created_at' => now()->subHours(2)]
                );
            }
            if (Schema::hasTable('security_events')) {
                SecurityEvent::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'event_type' => 'security.demo_review'],
                    ['uuid' => (string) Str::uuid(), 'severity' => 'info', 'ip_address' => '127.0.0.1', 'user_agent' => 'WorkIntel Seeder', 'metadata' => ['seeded' => true], 'created_at' => now()->subDay()]
                );
            }
        });
    }

    /** Handles the user operation for the current WorkIntel workflow. */ private function user(string $firstName, string $lastName, string $email, string $timezone = 'Asia/Dubai'): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => 'password',
                'timezone' => $timezone,
                'locale' => 'en',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }

    /** Handles the member operation for the current WorkIntel workflow. */ private function member(Workspace $workspace, User $user, string $code, string $title, Department $department, ?WorkspaceMember $manager, string $joined): WorkspaceMember
    {
        $jobTitle = JobTitle::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => $title],
            ['code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $title), 0, 8)), 'status' => 'active']
        );

        return WorkspaceMember::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            [
                'employee_code' => $code,
                'job_title' => $title,
                'job_title_id' => $jobTitle->id,
                'department_id' => $department->id,
                'manager_id' => $manager?->id,
                'employment_type' => 'full_time',
                'joining_date' => $joined,
                'status' => MemberStatus::Active,
                'timezone' => $user->timezone,
            ]
        );
    }

    /** Handles the roles operation for the current WorkIntel workflow. */ private function roles(int $workspaceId): array
    {
        $all = Permission::pluck('id');
        $definitions = [
            'owner' => ['Owner', null],
            'admin' => ['Admin', null],
            'hr' => ['HR', [
                'people.view_all', 'people.manage', 'organization.view', 'organization.manage',
                'attendance.view_own', 'attendance.view_team', 'attendance.manage', 'attendance.policy_manage', 'scheduling.view_own', 'scheduling.view_team', 'scheduling.manage', 'time.view_own', 'time.view_team', 'reports.view', 'reports.manage', 'devices.view', 'approvals.view_own', 'approvals.review', 'approvals.audit',
                'hris.view_own', 'hris.view_team', 'hris.view_all', 'hris.manage', 'performance.view_own', 'performance.view_team', 'performance.view_all', 'performance.manage', 'performance.reviews.manage', 'expenses.view_own', 'expenses.view_team', 'field.view_own', 'field.view_team', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team', 'intelligence.view_all',
            ]],
            'manager' => ['Manager', [
                'people.view_team', 'organization.view', 'projects.view_all', 'projects.manage', 'tasks.view_all', 'tasks.manage',
                'time.view_own', 'time.view_team', 'time.manage', 'attendance.view_own', 'attendance.view_team', 'attendance.manage', 'scheduling.view_own', 'scheduling.view_team', 'scheduling.manage',
                'activity.view_own', 'activity.view_team', 'screenshots.view_team', 'reports.view', 'reports.manage', 'approvals.view_own', 'approvals.review',
                'hris.view_own', 'hris.view_team', 'performance.view_own', 'performance.view_team', 'performance.manage', 'performance.reviews.manage', 'expenses.view_own', 'expenses.view_team', 'procurement.view', 'procurement.request', 'procurement.manage', 'job_costing.view', 'field.view_own', 'field.view_team', 'field.manage', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team',
            ]],
            'team-lead' => ['Team Lead', [
                'people.view_team', 'organization.view', 'projects.view_assigned', 'tasks.view_team', 'tasks.manage_team', 'time.view_own', 'time.view_team',
                'attendance.view_own', 'attendance.view_team', 'scheduling.view_own', 'scheduling.view_team', 'activity.view_own', 'activity.view_team', 'screenshots.view_team', 'reports.view', 'approvals.view_own', 'approvals.review',
                'hris.view_own', 'hris.view_team', 'performance.view_own', 'performance.view_team', 'expenses.view_own', 'expenses.view_team', 'procurement.view', 'procurement.request', 'field.view_own', 'field.view_team', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team',
            ]],
            'payroll-manager' => ['Payroll Manager', [
                'people.view_all', 'organization.view', 'time.view_all', 'attendance.view_own', 'attendance.view_team', 'scheduling.view_own', 'scheduling.view_team', 'payroll.view_own', 'payroll.view_all', 'payroll.manage', 'reports.view', 'reports.manage', 'approvals.view_own', 'approvals.review', 'approvals.audit',
                'performance.view_own', 'performance.view_all', 'performance.compensation.manage', 'expenses.view_own', 'expenses.manage', 'job_costing.view', 'payroll.compliance.view', 'payroll.compliance.manage', 'payroll.exports.manage', 'payroll.contractors.manage', 'intelligence.view_own', 'intelligence.view_all',
            ]],
            'employee' => ['Employee', [
                'projects.view_assigned', 'tasks.view_own', 'time.view_own', 'attendance.view_own', 'scheduling.view_own', 'activity.view_own', 'screenshots.view_own', 'payroll.view_own', 'approvals.view_own',
                'hris.view_own', 'performance.view_own', 'expenses.view_own', 'procurement.request', 'field.view_own', 'intelligence.view_own',
            ]],
            'client' => ['Client (Legacy)', []],
        ];

        $roles = [];
        foreach ($definitions as $slug => [$name, $permissions]) {
            $role = Role::updateOrCreate(
                ['workspace_id' => $workspaceId, 'slug' => $slug],
                ['name' => $name, 'is_system' => true]
            );
            $permissionIds = $permissions === null ? $all : Permission::whereIn('slug', $permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
            $roles[$slug] = $role;
        }

        return $roles;
    }

    /** Handles the project operation for the current WorkIntel workflow. */ private function project(Workspace $workspace, ?Client $client, User $creator, string $name, string $code, string $dueDate, int $budgetHours, bool $billable): Project
    {
        return Project::updateOrCreate(
            ['workspace_id' => $workspace->id, 'code' => $code],
            [
                'client_id' => $client?->id,
                'name' => $name,
                'description' => $name.' demo project.',
                'status' => 'active',
                'priority' => 'medium',
                'start_date' => '2026-07-01',
                'due_date' => $dueDate,
                'budget_type' => 'hours',
                'budget_amount' => $budgetHours,
                'estimated_minutes' => $budgetHours * 60,
                'billable' => $billable,
                'currency' => 'USD',
                'created_by' => $creator->id,
            ]
        );
    }

    /** Handles the task operation for the current WorkIntel workflow. */ private function task(Workspace $workspace, Project $project, User $creator, string $title, string $status, string $priority, int $estimate, string $dueDate, bool $billable, array $assignees): Task
    {
        $task = Task::updateOrCreate(
            ['workspace_id' => $workspace->id, 'project_id' => $project->id, 'title' => $title],
            [
                'status' => $status,
                'priority' => $priority,
                'estimated_minutes' => $estimate,
                'due_at' => $dueDate.' 18:00:00',
                'billable' => $billable,
                'created_by' => $creator->id,
            ]
        );

        $task->assignees()->sync(collect($assignees)->pluck('id'));
        return $task;
    }

    /** Handles the time entry operation for the current WorkIntel workflow. */ private function timeEntry(int $workspaceId, int $memberId, int $projectId, int $taskId, string $start, string $end, bool $billable): void
    {
        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);

        TimeEntry::updateOrCreate(
            ['workspace_id' => $workspaceId, 'member_id' => $memberId, 'started_at' => $startAt],
            [
                'project_id' => $projectId,
                'task_id' => $taskId,
                'date' => $startAt->toDateString(),
                'ended_at' => $endAt,
                'duration_seconds' => (int) $startAt->diffInSeconds($endAt),
                'billable' => $billable,
                'source' => 'web',
                'approval_status' => 'approved',
            ]
        );
    }
}
