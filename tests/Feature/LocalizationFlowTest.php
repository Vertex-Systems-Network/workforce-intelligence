<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\WorkspacePreference;
use App\Services\Documents\DocumentTemplateService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p7 localization flow test behavior within the WorkIntel application. */ class LocalizationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test public locale endpoint normalizes language and reports rtl operation for the current WorkIntel workflow. */ public function test_public_locale_endpoint_normalizes_language_and_reports_rtl(): void
    {
        $this->withHeader('X-Locale','ur-PK')->getJson('/api/v1/localization')
            ->assertOk()->assertHeader('Content-Language','ur')
            ->assertJsonPath('locale','ur')->assertJsonPath('direction','rtl')
            ->assertJsonPath('fallback','en');
    }

    /** Handles the test user can follow workspace language or set personal override operation for the current WorkIntel workflow. */ public function test_user_can_follow_workspace_language_or_set_personal_override(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');
        WorkspacePreference::where('workspace_id',$member->workspace_id)->update(['default_language'=>'tr']);
        $owner->update(['locale'=>'en','use_workspace_locale'=>true]);
        Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);

        $this->getJson('/api/v1/workspace',$headers)->assertOk()->assertHeader('Content-Language','tr');
        $this->putJson('/api/v1/auth/locale',['locale'=>'ur','use_workspace_locale'=>false])
            ->assertOk()->assertJsonPath('data.locale','ur')->assertJsonPath('data.use_workspace_locale',false);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertHeader('Content-Language','ur')
            ->assertJsonPath('user.use_workspace_locale',false);
        $this->assertSame('ur',$owner->fresh()->preferredLocale());
    }

    /** Handles the test document language variant is selected before english fallback operation for the current WorkIntel workflow. */ public function test_document_language_variant_is_selected_before_english_fallback(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $english=DocumentTemplate::where('workspace_id',$member->workspace_id)->where('document_type','invoice')->where('language','en')->firstOrFail();
        app(DocumentTemplateService::class)->setDefault($english);

        $variant=$this->postJson('/api/v1/documents/templates/'.$english->id.'/language-variant',['language'=>'ar'],$headers)
            ->assertCreated()->assertJsonPath('data.language','ar')->json('data');
        $this->postJson('/api/v1/documents/templates/'.$variant['id'].'/preview',[],$headers)
            ->assertOk()->assertJsonStructure(['html','context']);

        $selected=app(DocumentTemplateService::class)->defaultTemplate($member->workspace,'invoice','ar');
        $this->assertSame((int)$variant['id'],$selected?->id);
        $html=app(DocumentTemplateService::class)->preview($selected,$member)['html'];
        $this->assertStringContainsString('dir="rtl"',$html);
    }

    /** Handles the test duplicate language variant for same source is rejected operation for the current WorkIntel workflow. */ public function test_duplicate_language_variant_for_same_source_is_rejected(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $english=DocumentTemplate::where('workspace_id',$member->workspace_id)->where('document_type','quote')->where('language','en')->firstOrFail();
        $this->postJson('/api/v1/documents/templates/'.$english->id.'/language-variant',['language'=>'ru'],$headers)->assertCreated();
        $this->postJson('/api/v1/documents/templates/'.$english->id.'/language-variant',['language'=>'ru'],$headers)->assertStatus(422);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user=User::where('email',$email)->firstOrFail();
        $member=$user->memberships()->with('workspace.preferences')->where('status','active')->firstOrFail();
        return [$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array{return ['X-Workspace-Id'=>(string)$workspaceId];}
}
