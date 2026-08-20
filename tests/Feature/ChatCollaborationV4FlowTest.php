<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises the M10 collaboration inbox, context panel and authorized internal-resource links. */
class ChatCollaborationV4FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds one complete WorkIntel workspace before collaboration flows. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies unread mentions, followed-thread replies and unread direct messages appear in one inbox. */
    public function test_collaboration_inbox_aggregates_attention_items(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        $headers=$this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $direct=$this->postJson('/api/v1/chat/conversations',['type'=>'direct','member_ids'=>[$employeeMember->id]],$headers)->assertSuccessful()->json('data');
        $root=$this->postJson("/api/v1/chat/conversations/{$direct['id']}/messages",['body'=>'Follow this thread'], $headers)->assertCreated()->json('data');
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/chat/messages/{$root['id']}/thread/follow",['following'=>true],$headers)->assertOk();
        $this->putJson("/api/v1/chat/conversations/{$direct['id']}/read",['message_id'=>$root['id']],$headers)->assertOk();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/chat/conversations/{$direct['id']}/messages",['body'=>"Please review @[member:{$employeeMember->id}]"],$headers)->assertCreated();
        $this->postJson("/api/v1/chat/conversations/{$direct['id']}/messages",['body'=>'Thread reply','parent_id'=>$root['id']],$headers)->assertCreated();
        Sanctum::actingAs($employee);
        $inbox=$this->getJson('/api/v1/chat/inbox',$headers)->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(1,$inbox['counts']['mentions']);
        $this->assertGreaterThanOrEqual(1,$inbox['counts']['threads']);
        $this->assertGreaterThanOrEqual(1,$inbox['counts']['direct']);
    }

    /** Verifies pinned messages, private bookmark notes and typed internal resources remain workspace scoped. */
    public function test_conversation_context_and_internal_resources_are_authorized(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [, $employeeMember]=$this->userAndMember('employee@acme.test');
        $headers=$this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel=$this->postJson('/api/v1/chat/conversations',['type'=>'channel','name'=>'Context room','member_ids'=>[$employeeMember->id]],$headers)->assertCreated()->json('data');
        $message=$this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages",['body'=>'Important context'], $headers)->assertCreated()->json('data');
        $this->postJson("/api/v1/chat/messages/{$message['id']}/pin",[], $headers)->assertOk();
        $this->postJson("/api/v1/chat/messages/{$message['id']}/save",[], $headers)->assertOk();
        $this->putJson("/api/v1/chat/messages/{$message['id']}/save-note",['note'=>'Decision reference'], $headers)->assertOk()->assertJsonPath('data.note','Decision reference');
        $context=$this->getJson("/api/v1/chat/conversations/{$channel['id']}/context",$headers)->assertOk()->json('data');
        $this->assertSame($message['id'],$context['pinned'][0]['id']);
        $this->assertSame('Decision reference',$context['bookmarks'][0]['note']);

        $options=$this->getJson('/api/v1/chat/options',$headers)->assertOk()->json('data');
        $project=$options['projects'][0];
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/resources",['kind'=>'project','label'=>$project['name'],'resource_id'=>$project['id']],$headers)->assertCreated()->assertJsonPath('data.resource_type','project');
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/resources",['kind'=>'project','label'=>'Outside','resource_id'=>99999999],$headers)->assertStatus(422);
        if (!empty($options['documents'])) {
            $document=$options['documents'][0];
            $this->assertTrue(GeneratedDocument::where('workspace_id',$ownerMember->workspace_id)->whereKey($document['id'])->exists());
            $this->postJson("/api/v1/chat/conversations/{$channel['id']}/resources",['kind'=>'document','label'=>$document['filename'],'resource_id'=>$document['id']],$headers)->assertCreated()->assertJsonPath('data.resource_type','generated_document');
        }
    }

    /** Resolves one seeded user and active workspace member. */
    private function userAndMember(string $email): array
    {
        $user=User::where('email',$email)->firstOrFail();
        $member=$user->memberships()->where('status','active')->firstOrFail();
        return [$user,$member];
    }

    /** Builds the workspace header required by resolved-workspace middleware. */
    private function headers(int $workspaceId): array { return ['X-Workspace-Id'=>(string)$workspaceId]; }


    /** Verifies private inbox triage and the dedicated chat notification matrix persist for one member. */
    public function test_collaboration_inbox_triage_and_notification_matrix_are_private_and_persistent(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        $headers=$this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $direct=$this->postJson('/api/v1/chat/conversations',['type'=>'direct','member_ids'=>[$employeeMember->id]],$headers)->assertSuccessful()->json('data');
        $this->postJson("/api/v1/chat/conversations/{$direct['id']}/messages",['body'=>"Attention @[member:{$employeeMember->id}]"],$headers)->assertCreated();
        Sanctum::actingAs($employee);
        $inbox=$this->getJson('/api/v1/chat/inbox',$headers)->assertOk()->json('data');
        $key=$inbox['mentions'][0]['activity_key'];
        $this->postJson('/api/v1/chat/inbox/triage',['action'=>'done','activity_key'=>$key],$headers)->assertOk()->assertJsonPath('data.counts.mentions',0);
        $this->assertDatabaseHas('chat_activity_states',['member_id'=>$employeeMember->id,'activity_key'=>$key,'status'=>'done']);
        $prefs=$this->getJson('/api/v1/chat/notification-preferences',$headers)->assertOk()->json('data');
        $this->assertCount(4,$prefs);
        foreach($prefs as &$pref){if($pref['category']==='chat_direct')$pref['email']=true;}
        $this->putJson('/api/v1/chat/notification-preferences',['preferences'=>$prefs],$headers)->assertOk();
        $this->assertDatabaseHas('notification_preferences',['workspace_id'=>$employeeMember->workspace_id,'user_id'=>$employee->id,'category'=>'chat_direct','email'=>1]);
    }

    /** Verifies context bulk cleanup and permission-safe internal resource cards. */
    public function test_context_bulk_cleanup_and_rich_resource_cards_are_authorized(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [, $employeeMember]=$this->userAndMember('employee@acme.test');
        $headers=$this->headers($ownerMember->workspace_id);Sanctum::actingAs($owner);
        $channel=$this->postJson('/api/v1/chat/conversations',['type'=>'channel','name'=>'Closure room','member_ids'=>[$employeeMember->id]],$headers)->assertCreated()->json('data');
        $message=$this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages",['body'=>'Pinned closure context'],$headers)->assertCreated()->json('data');
        $this->postJson("/api/v1/chat/messages/{$message['id']}/pin",[],$headers)->assertOk();
        $this->postJson("/api/v1/chat/messages/{$message['id']}/save",[],$headers)->assertOk();
        $context=$this->getJson("/api/v1/chat/conversations/{$channel['id']}/context?limit=5",$headers)->assertOk()->json('data');
        $this->assertArrayHasKey('meta',$context);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/context/bulk",['action'=>'delete_bookmarks','ids'=>[$context['bookmarks'][0]['id']]],$headers)->assertOk()->assertJsonCount(0,'data.bookmarks');
        $options=$this->getJson('/api/v1/chat/options',$headers)->assertOk()->json('data');$project=$options['projects'][0];
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/resources",['kind'=>'project','label'=>$project['name'],'resource_id'=>$project['id']],$headers)->assertCreated();
        $resources=$this->getJson("/api/v1/chat/conversations/{$channel['id']}/resources",$headers)->assertOk()->json('data');
        $this->assertSame('project',$resources[0]['entity']['type']);$this->assertTrue($resources[0]['available']);
    }
}
