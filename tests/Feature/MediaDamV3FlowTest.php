<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaCollection;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Verifies the M7 DAM collection, favorite and metadata-version journeys against real workspace authorization. */
class MediaDamV3FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seed a deterministic workspace and isolate private media storage. */
    protected function setUp(): void { parent::setUp();Storage::fake('local');$this->seed(DatabaseSeeder::class); }

    /** Ensure collections, member favorites, filtering and metadata versions work end to end. */
    public function test_owner_can_collect_favorite_filter_and_version_media(): void
    {
        $owner=User::where('email','owner@acme.test')->firstOrFail();
        $member=WorkspaceMember::where('user_id',$owner->id)->where('status','active')->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $collection=$this->postJson('/api/v1/media/collections',['name'=>'Brand Launch','description'=>'Reusable launch assets'],$headers)->assertCreated()->json('data');
        $asset=$this->post('/api/v1/media',['files'=>[UploadedFile::fake()->createWithContent('brief.txt','dam-v3-content')]],$headers)->assertCreated()->json('data.0.asset');
        $this->postJson('/api/v1/media/'.$asset['id'].'/favorite',[],$headers)->assertOk();
        $this->putJson('/api/v1/media/'.$asset['id'],['name'=>'Launch Brief','caption'=>'Approved campaign brief','tags'=>['brand','launch'],'collection_ids'=>[$collection['id']]],$headers)->assertOk()->assertJsonPath('data.name','Launch Brief');
        $this->getJson('/api/v1/media?favorite=1',$headers)->assertOk()->assertJsonFragment(['id'=>$asset['id'],'is_favorite'=>true]);
        $this->getJson('/api/v1/media?collection_id='.$collection['id'],$headers)->assertOk()->assertJsonFragment(['id'=>$asset['id'],'name'=>'Launch Brief']);
        $this->getJson('/api/v1/media/'.$asset['id'].'/versions',$headers)->assertOk()->assertJsonCount(2,'data');
        $this->deleteJson('/api/v1/media/'.$asset['id'].'/favorite',[],$headers)->assertOk();
        $this->deleteJson('/api/v1/media/collections/'.$collection['id'],[],$headers)->assertOk();
        $this->assertNotNull(MediaAsset::find($asset['id']));$this->assertNull(MediaCollection::find($collection['id']));
    }
    /** Ensure binary replacement/restore and resumable assembly preserve a stable asset identity and immutable history. */
    public function test_owner_can_replace_restore_and_resume_binary_media(): void
    {
        $owner=User::where('email','owner@acme.test')->firstOrFail();
        $member=WorkspaceMember::where('user_id',$owner->id)->where('status','active')->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $original='original-binary';$replacement='replacement-binary';
        $asset=$this->post('/api/v1/media',['files'=>[UploadedFile::fake()->createWithContent('asset.txt',$original)]],$headers)->assertCreated()->json('data.0.asset');
        $firstVersion=$this->getJson('/api/v1/media/'.$asset['id'].'/versions',$headers)->assertOk()->json('data.0');
        $this->assertTrue($firstVersion['binary_available']);$this->assertSame('ready',$firstVersion['binary_status']);
        $this->post('/api/v1/media/'.$asset['id'].'/replace',['file'=>UploadedFile::fake()->createWithContent('replacement.txt',$replacement)],$headers)->assertOk();
        $this->assertSame(hash('sha256',$replacement),MediaAsset::findOrFail($asset['id'])->checksum_sha256);
        $this->postJson('/api/v1/media/'.$asset['id'].'/versions/'.$firstVersion['id'].'/restore',[],$headers)->assertOk();
        $restored=MediaAsset::findOrFail($asset['id']);$this->assertSame($asset['id'],$restored->id);$this->assertSame(hash('sha256',$original),$restored->checksum_sha256);
        $this->assertGreaterThanOrEqual(3,$restored->versions()->count());

        $chunkBody='chunked-data';
        $session=$this->postJson('/api/v1/media/uploads',['original_name'=>'resume.txt','mime_type'=>'text/plain','size_bytes'=>strlen($chunkBody),'chunk_size_bytes'=>524288],$headers)->assertCreated()->json('data');
        $this->post('/api/v1/media/uploads/'.$session['uuid'].'/chunks/0',['chunk'=>UploadedFile::fake()->createWithContent('0.part',$chunkBody),'checksum_sha256'=>hash('sha256',$chunkBody)],$headers)->assertOk()->assertJsonPath('data.received_chunks.0',0);
        $completed=$this->postJson('/api/v1/media/uploads/'.$session['uuid'].'/complete',[],$headers)->assertCreated()->json('data.0.asset');
        $this->assertSame(hash('sha256',$chunkBody),MediaAsset::findOrFail($completed['id'])->checksum_sha256);
    }

    /** Ensure rights metadata, restricted collection sharing and bounded bulk actions remain workspace scoped. */
    public function test_owner_can_govern_rights_share_collection_and_bulk_favorite(): void
    {
        $owner=User::where('email','owner@acme.test')->firstOrFail();$employee=User::where('email','employee@acme.test')->firstOrFail();
        $member=WorkspaceMember::where('user_id',$owner->id)->where('status','active')->firstOrFail();$employeeMember=WorkspaceMember::where('user_id',$employee->id)->where('workspace_id',$member->workspace_id)->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $collection=$this->postJson('/api/v1/media/collections',['name'=>'Restricted Brand','visibility'=>'restricted','member_ids'=>[$employeeMember->id]],$headers)->assertCreated()->json('data');
        $this->assertSame([$employeeMember->id],$collection['shared_member_ids']);
        $asset=$this->post('/api/v1/media',['files'=>[UploadedFile::fake()->createWithContent('rights.txt','rights-data')]],$headers)->assertCreated()->json('data.0.asset');
        $this->putJson('/api/v1/media/'.$asset['id'],['copyright_owner'=>'Acme Corp','license_type'=>'Owned','license_reference'=>'ACME-2026','rights_review_at'=>today()->addDays(10)->toDateString(),'collection_ids'=>[$collection['id']]],$headers)->assertOk()->assertJsonPath('data.rights_status','clear');
        $this->postJson('/api/v1/media/bulk',['action'=>'favorite','asset_ids'=>[$asset['id']]],$headers)->assertOk()->assertJsonFragment(['processed'=>[$asset['id']]]);
        $this->getJson('/api/v1/media?favorite=1',$headers)->assertOk()->assertJsonFragment(['id'=>$asset['id'],'is_favorite'=>true]);
    }

}
