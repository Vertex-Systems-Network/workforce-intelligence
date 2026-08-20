<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Services\Modules\WorkspaceModuleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p10 chat collaboration flow test behavior within the WorkIntel application. */ class ChatCollaborationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test employee can direct message reply react pin and mark read operation for the current WorkIntel workflow. */ public function test_employee_can_direct_message_reply_react_pin_and_mark_read(): void
    {
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        [, $managerMember]=$this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee); $h=$this->headers($employeeMember->workspace_id);

        $options=$this->getJson('/api/v1/chat/options',$h)->assertOk()->json('data');
        $this->assertTrue(collect($options['people'])->contains('id',$managerMember->id));

        $conversation=$this->postJson('/api/v1/chat/conversations',[
            'type'=>'direct','member_ids'=>[$managerMember->id],
        ],$h)->assertCreated()->json('data');

        $first=$this->postJson('/api/v1/chat/conversations/'.$conversation['id'].'/messages',[
            'body'=>'Hello manager',
        ],$h)->assertCreated()->json('data');
        $reply=$this->postJson('/api/v1/chat/conversations/'.$conversation['id'].'/messages',[
            'body'=>'Replying here','parent_id'=>$first['id'],
        ],$h)->assertCreated()->assertJsonPath('data.parent.id',$first['id'])->json('data');

        $this->postJson('/api/v1/chat/messages/'.$reply['id'].'/reaction',['emoji'=>'👍'],$h)->assertOk()->assertJsonPath('active',true);
        $this->postJson('/api/v1/chat/messages/'.$reply['id'].'/pin',[],$h)->assertOk()->assertJsonPath('pinned',true);
        $this->putJson('/api/v1/chat/conversations/'.$conversation['id'].'/read',['message_id'=>$reply['id']],$h)->assertOk();
        $this->getJson('/api/v1/chat/messages/'.$first['id'].'/thread',$h)->assertOk()->assertJsonFragment(['pinned'=>true]);
    }

    /** Handles the test mentions notify members and non members cannot read conversation operation for the current WorkIntel workflow. */ public function test_mentions_notify_members_and_non_members_cannot_read_conversation(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        [$manager,$managerMember]=$this->userAndMember('manager@acme.test');
        [$hr,$hrMember]=$this->userAndMember('hr@acme.test');
        $h=$this->headers($ownerMember->workspace_id);

        Sanctum::actingAs($employee);
        $conversation=$this->postJson('/api/v1/chat/conversations',['type'=>'direct','member_ids'=>[$managerMember->id]],$h)->assertCreated()->json('data');
        $message=$this->postJson('/api/v1/chat/conversations/'.$conversation['id'].'/messages',[
            'body'=>'Please review @[member:'.$managerMember->id.']',
        ],$h)->assertCreated()->json('data');
        $this->assertContains($managerMember->id,$message['mentions']);
        $this->assertTrue(WorkspaceNotification::where('workspace_id',$ownerMember->workspace_id)->where('user_id',$manager->id)->where('type','chat.mention')->exists());

        Sanctum::actingAs($hr);
        $this->getJson('/api/v1/chat/conversations/'.$conversation['id'].'/messages',$this->headers($hrMember->workspace_id))->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assertSame($ownerMember->workspace_id,$hrMember->workspace_id);
    }

    /** Handles the test chat attachments are private and member scoped operation for the current WorkIntel workflow. */ public function test_chat_attachments_are_private_and_member_scoped(): void
    {
        Storage::fake('local');
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        [$manager,$managerMember]=$this->userAndMember('manager@acme.test');
        [$hr,$hrMember]=$this->userAndMember('hr@acme.test');
        $h=$this->headers($employeeMember->workspace_id);
        Sanctum::actingAs($employee);
        $conversation=$this->postJson('/api/v1/chat/conversations',['type'=>'direct','member_ids'=>[$managerMember->id]],$h)->assertCreated()->json('data');
        $response=$this->post('/api/v1/chat/conversations/'.$conversation['id'].'/messages',[
            'body'=>'Private file','attachments'=>[UploadedFile::fake()->create('brief.txt',2,'text/plain')],
        ],$h)->assertCreated();
        $attachment=$response->json('data.attachments.0');
        $row=ChatMessage::findOrFail($response->json('data.id'))->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($row->path);
        $this->assertSame(64,strlen($row->checksum_sha256));

        Sanctum::actingAs($manager);
        $this->get($attachment['url'],$this->headers($managerMember->workspace_id))->assertOk();
        Sanctum::actingAs($hr);
        $this->get($attachment['url'],$this->headers($hrMember->workspace_id))->assertForbidden();
    }

    /** Handles the test project and task threads require visible targets operation for the current WorkIntel workflow. */ public function test_project_and_task_threads_require_visible_targets(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test'); Sanctum::actingAs($owner); $h=$this->headers($member->workspace_id);
        $options=$this->getJson('/api/v1/chat/options',$h)->assertOk()->json('data');
        $this->assertNotEmpty($options['projects']); $this->assertNotEmpty($options['tasks']);
        $this->postJson('/api/v1/chat/conversations',['type'=>'project','name'=>'Missing target'],$h)->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $project=$options['projects'][0];
        $this->postJson('/api/v1/chat/conversations',['type'=>'project','name'=>'Project room','project_id'=>$project['id']],$h)->assertCreated()->assertJsonPath('data.project.id',$project['id']);
        $task=$options['tasks'][0];
        $this->postJson('/api/v1/chat/conversations',['type'=>'task','name'=>'Task room','task_id'=>$task['id']],$h)->assertCreated()->assertJsonPath('data.task.id',$task['id']);
    }

    /** Handles the test disabling chat module blocks chat api without deleting messages operation for the current WorkIntel workflow. */ public function test_disabling_chat_module_blocks_chat_api_without_deleting_messages(): void
    {
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test'); [, $managerMember]=$this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee); $h=$this->headers($employeeMember->workspace_id);
        $conversation=$this->postJson('/api/v1/chat/conversations',['type'=>'direct','member_ids'=>[$managerMember->id]],$h)->assertCreated()->json('data');
        $this->postJson('/api/v1/chat/conversations/'.$conversation['id'].'/messages',['body'=>'Preserve me'],$h)->assertCreated();
        $before=ChatMessage::where('workspace_id',$employeeMember->workspace_id)->count();

        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        app(WorkspaceModuleService::class)->update($ownerMember->workspace,'chat',['is_enabled'=>false],$ownerMember);
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/chat/conversations',$h)->assertStatus(423)->assertJsonPath('code','WORKSPACE_MODULE_DISABLED');
        $this->assertSame($before,ChatMessage::where('workspace_id',$employeeMember->workspace_id)->count());
    }


    /** Verifies that conversation creation never offers the current or inactive member. */
    public function test_creation_options_exclude_self_and_inactive_members(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [$manager, $managerMember] = $this->userAndMember('manager@acme.test');
        [, $hrMember] = $this->userAndMember('hr@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);

        $options = $this->getJson('/api/v1/chat/options', $headers)->assertOk()->json('data');
        $this->assertSame($employeeMember->id, $options['current_member_id']);
        $this->assertFalse(collect($options['people'])->contains('id', $employeeMember->id));
        $this->assertTrue(collect($options['people'])->contains('id', $managerMember->id));

        $managerMember->update(['status' => 'suspended']);
        $options = $this->getJson('/api/v1/chat/options', $headers)->assertOk()->json('data');
        $this->assertFalse(collect($options['people'])->contains('id', $managerMember->id));

        $managerMember->update(['status' => 'active']);
        $manager->update(['status' => 'suspended']);
        $options = $this->getJson('/api/v1/chat/options', $headers)->assertOk()->json('data');
        $this->assertFalse(collect($options['people'])->contains('id', $managerMember->id));

        $hrMember->update(['status' => 'archived']);
        $options = $this->getJson('/api/v1/chat/options', $headers)->assertOk()->json('data');
        $this->assertFalse(collect($options['people'])->contains('id', $hrMember->id));
    }

    /** Verifies that self-DMs are rejected and a canonical DM pair is reused. */
    public function test_self_dm_is_rejected_and_duplicate_dm_reuses_existing_conversation(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'member_ids' => [$employeeMember->id],
        ], $headers)->assertStatus(422)->assertJsonPath('code', 'SELF_CONVERSATION_NOT_ALLOWED');

        $first = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'member_ids' => [$managerMember->id],
        ], $headers)->assertCreated()->json('data');

        $second = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'member_ids' => [$managerMember->id],
        ], $headers)->assertOk()->assertJsonPath('created', false)->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, \App\Models\ChatConversation::where('workspace_id', $employeeMember->workspace_id)->where('type', 'direct')->where('direct_key', $first['direct_key'])->count());
    }

    /** Verifies that presence responses expose only members sharing a conversation with the viewer. */
    public function test_presence_is_limited_to_members_in_viewers_conversations(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        [, $hrMember] = $this->userAndMember('hr@acme.test');
        $headers = $this->headers($employeeMember->workspace_id);

        Sanctum::actingAs($employee);
        $conversation = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'member_ids' => [$managerMember->id],
        ], $headers)->assertCreated()->json('data');
        $this->postJson('/api/v1/chat/presence', ['conversation_id' => $conversation['id']], $headers)->assertOk();

        \App\Models\ChatPresence::updateOrCreate(
            ['member_id' => $hrMember->id],
            ['workspace_id' => $hrMember->workspace_id, 'conversation_id' => null, 'is_typing' => false, 'last_seen_at' => now()],
        );

        $response = $this->getJson('/api/v1/chat/conversations', $headers)->assertOk();
        $this->assertSame($employeeMember->id, $response->json('viewer_member_id'));
        $presenceIds = collect($response->json('presence'))->pluck('member_id');
        $this->assertFalse($presenceIds->contains($hrMember->id));
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user=User::where('email',$email)->firstOrFail();
        $member=$user->memberships()->with('workspace')->where('status','active')->firstOrFail();
        return[$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array{return['X-Workspace-Id'=>(string)$workspaceId];}
}
