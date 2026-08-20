<?php

namespace App\Services\Documents;

use App\Models\DocumentComponent;
use App\Models\DocumentBatchJob;
use App\Models\DocumentBrandKit;
use App\Models\DocumentPageMaster;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateDraft;
use App\Models\GeneratedDocument;
use App\Models\MediaAsset;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides Document Studio V6 autosave, multi-page preflight and bounded batch-generation workflows. */
class DocumentStudioV6Service
{
    /** Initializes V6 with the existing governed template service so autosave and batch generation reuse one validation path. */
    public function __construct(private readonly DocumentTemplateService $templates) {}

    /** Stores one mutable V6 autosave without incrementing immutable template version history. */
    public function saveDraft(DocumentTemplate $template, WorkspaceMember $actor, array $data): DocumentTemplateDraft
    {
        abort_unless((int) $template->workspace_id === (int) $actor->workspace_id, 404);
        $schema = $this->templates->validatedSchema((array) ($data['content_schema'] ?? $template->content_schema ?? []));
        $settings = $this->templates->validatedSettings((array) ($data['settings'] ?? $template->settings ?? []));
        $metadata = $this->metadata($template, (array) ($data['metadata'] ?? []));
        return DB::transaction(function () use ($template, $actor, $schema, $settings, $metadata) {
            $draft = DocumentTemplateDraft::query()->where('document_template_id', $template->id)->lockForUpdate()->first();
            if ($draft) {
                $draft->update(['content_schema'=>$schema,'settings'=>$settings,'metadata'=>$metadata,'revision'=>$draft->revision + 1,'updated_by_member_id'=>$actor->id]);
                return $draft->fresh();
            }
            return DocumentTemplateDraft::create(['uuid'=>(string) Str::uuid(),'workspace_id'=>$template->workspace_id,'document_template_id'=>$template->id,'content_schema'=>$schema,'settings'=>$settings,'metadata'=>$metadata,'revision'=>1,'updated_by_member_id'=>$actor->id]);
        });
    }

    /** Returns one stable autosave payload for editor recovery. */
    public function draftPayload(DocumentTemplate $template): ?array
    {
        $draft = DocumentTemplateDraft::query()->where('document_template_id', $template->id)->first();
        if (! $draft) return null;
        return ['uuid'=>$draft->uuid,'revision'=>$draft->revision,'content_schema'=>$draft->content_schema,'settings'=>$draft->settings ?: [],'metadata'=>$draft->metadata ?: [],'updated_at'=>$draft->updated_at?->toISOString(),'updated_by_member_id'=>$draft->updated_by_member_id];
    }

    /** Discards only the mutable autosave and leaves immutable template versions unchanged. */
    public function discardDraft(DocumentTemplate $template, WorkspaceMember $actor): void
    {
        abort_unless((int) $template->workspace_id === (int) $actor->workspace_id, 404);
        DocumentTemplateDraft::query()->where('document_template_id', $template->id)->delete();
    }

    /** Runs server-side PDF preflight against persisted or unsaved V6 designer state. */
    public function preflight(DocumentTemplate $template, WorkspaceMember $actor, array $data = []): array
    {
        abort_unless((int) $template->workspace_id === (int) $actor->workspace_id, 404);
        $schema = $this->templates->validatedSchema((array) ($data['content_schema'] ?? $template->content_schema ?? []));
        $settings = $this->templates->validatedSettings((array) ($data['settings'] ?? $template->settings ?? []));
        $issues = [];
        $add = static function (string $severity, string $code, string $message, ?string $blockId = null, ?string $pageId = null) use (&$issues): void {
            $issues[] = compact('severity','code','message','blockId','pageId');
        };
        $pages = $this->pages($schema);
        if (! $pages) $add('error','document.empty','Add at least one page before generating a document.');
        if (count($pages) > 50) $add('error','document.page_limit','A document can contain at most 50 authored pages.');
        foreach ($pages as $pageIndex => $page) {
            $pageId = (string) ($page['id'] ?? 'page-'.($pageIndex + 1));
            $children = (array) ($page['children'] ?? []);
            if (! $children) $add('warning','page.empty','This page has no content.',$pageId,$pageId);
            $overrideMasterId=(int)($page['page_master_id']??0);
            if($overrideMasterId>0&&!DocumentPageMaster::query()->where('workspace_id',$template->workspace_id)->whereKey($overrideMasterId)->exists())$add('error','page.page_master_missing','This page references an unavailable page master.',null,$pageId);
            if(is_array($page['page_settings']??null)){$normalized=$this->templates->validatedSettings(['page'=>$page['page_settings']]);foreach(['margin_top','margin_right','margin_bottom','margin_left'] as $margin)if((float)($page['page_settings'][$margin]??$normalized['page'][$margin])!==(float)$normalized['page'][$margin])$add('warning','page.margin_normalized','A page-specific margin will be normalized to the supported 5–45 mm range.',null,$pageId);}
            $this->inspectBlocks($children, $template, $add, $pageId);
        }
        if (! (bool) data_get($settings, 'header.enabled') && ! (bool) data_get($settings, 'footer.enabled')) $add('warning','document.regions','Header and footer are both disabled; verify page identity and pagination are intentional.');
        $brandKitId=(int)($settings['brand_kit_id']??0);
        if($brandKitId>0){$brandKit=DocumentBrandKit::query()->where('workspace_id',$template->workspace_id)->find($brandKitId);if(!$brandKit)$add('error','brand_kit.missing','Selected brand kit is unavailable in this workspace.');elseif($brandKit->logo_media_asset_id&&!MediaAsset::query()->where('workspace_id',$template->workspace_id)->whereKey($brandKit->logo_media_asset_id)->whereNull('deleted_at')->exists())$add('error','brand_kit.logo_missing','The linked brand logo is unavailable in Media Library.');}
        $pageMasterId=(int)($settings['page_master_id']??0);
        if($pageMasterId>0&&!DocumentPageMaster::query()->where('workspace_id',$template->workspace_id)->whereKey($pageMasterId)->exists())$add('error','page_master.missing','Selected page master is unavailable in this workspace.');
        if((bool)data_get($settings,'workflow.signature_required')&&!$this->containsBlockType($schema,'signature'))$add('error','workflow.signature_block','Signature is required by workflow defaults, but the template has no signature block.');
        if((bool)data_get($settings,'workflow.approval_required')&&!(bool)data_get($settings,'workflow.review_required'))$add('warning','workflow.approval_without_review','Approval is required while review is disabled; verify this is intentional.');
        $rendering = ['page_count'=>count($pages),'block_count'=>$this->blockCount($schema),'has_header'=>(bool)data_get($settings,'header.enabled'),'has_footer'=>(bool)data_get($settings,'footer.enabled')];
        return ['issues'=>$issues,'errors'=>count(array_filter($issues,fn($row)=>$row['severity']==='error')),'warnings'=>count(array_filter($issues,fn($row)=>$row['severity']==='warning')),'stats'=>$rendering];
    }

    /** Generates the selected template for a bounded list of source records with per-record failure isolation. */
    public function batchGenerate(DocumentTemplate $template, WorkspaceMember $actor, array $sourceIds, ?string $sourceType = null): array
    {
        abort_unless((int) $template->workspace_id === (int) $actor->workspace_id, 404);
        $ids = array_values(array_unique(array_map('intval', $sourceIds)));
        if (! $ids || count($ids) > 50 || min($ids) < 1) throw ValidationException::withMessages(['source_ids'=>['Choose between 1 and 50 valid source IDs.']]);
        $generated=[];$failed=[];
        foreach ($ids as $sourceId) {
            try {
                $document = $this->templates->generate($template, $actor, $sourceId, $sourceType);
                $generated[] = ['source_id'=>$sourceId,'document_id'=>$document->id,'uuid'=>$document->uuid,'filename'=>$document->filename];
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['source_id'=>$sourceId,'message'=>'Generation failed for this source.'];
            }
        }
        return ['generated'=>$generated,'failed'=>$failed,'requested'=>count($ids)];
    }

    /** Returns workspace brand kits, reusable page masters and recent persistent batch jobs. */
    public function resources(WorkspaceMember $actor): array
    {
        return [
            'brand_kits'=>DocumentBrandKit::query()->where('workspace_id',$actor->workspace_id)->orderByDesc('is_default')->orderBy('name')->get(),
            'page_masters'=>DocumentPageMaster::query()->where('workspace_id',$actor->workspace_id)->orderByDesc('is_default')->orderBy('name')->get(),
            'batch_jobs'=>DocumentBatchJob::query()->with('template:id,name')->where('workspace_id',$actor->workspace_id)->latest('id')->limit(50)->get(),
        ];
    }

    /** Creates one reusable workspace brand kit from bounded visual-token input. */
    public function createBrandKit(WorkspaceMember $actor, array $data): DocumentBrandKit
    {
        if (! empty($data['is_default'])) DocumentBrandKit::query()->where('workspace_id',$actor->workspace_id)->update(['is_default'=>false]);
        return DocumentBrandKit::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$actor->workspace_id,'name'=>trim($data['name']),
            'primary_color'=>$this->color($data['primary_color']??'#111827','#111827'),'secondary_color'=>$this->color($data['secondary_color']??'#6B7280','#6B7280'),'accent_color'=>$this->color($data['accent_color']??'#2563EB','#2563EB'),
            'font_family'=>$this->font($data['font_family']??'Arial'),'heading_font_family'=>$this->font($data['heading_font_family']??($data['font_family']??'Arial')),
            'logo_media_asset_id'=>$this->logoAssetId($actor,(int)($data['logo_media_asset_id']??0)),'settings'=>is_array($data['settings']??null)?$data['settings']:[],
            'is_default'=>(bool)($data['is_default']??false),'created_by_member_id'=>$actor->id,'updated_by_member_id'=>$actor->id,
        ]);
    }

    /** Updates a workspace brand kit without allowing cross-workspace media references. */
    public function updateBrandKit(DocumentBrandKit $kit, WorkspaceMember $actor, array $data): DocumentBrandKit
    {
        abort_unless((int)$kit->workspace_id===(int)$actor->workspace_id,404);
        if (! empty($data['is_default'])) DocumentBrandKit::query()->where('workspace_id',$actor->workspace_id)->whereKeyNot($kit->id)->update(['is_default'=>false]);
        $next=[];
        if(array_key_exists('name',$data))$next['name']=trim($data['name']);
        foreach(['primary_color'=>'#111827','secondary_color'=>'#6B7280','accent_color'=>'#2563EB'] as $key=>$fallback)if(array_key_exists($key,$data))$next[$key]=$this->color($data[$key],$fallback);
        foreach(['font_family','heading_font_family'] as $key)if(array_key_exists($key,$data))$next[$key]=$this->font($data[$key]);
        if(array_key_exists('logo_media_asset_id',$data))$next['logo_media_asset_id']=$this->logoAssetId($actor,(int)($data['logo_media_asset_id']??0));
        if(array_key_exists('settings',$data))$next['settings']=is_array($data['settings'])?$data['settings']:[];
        if(array_key_exists('is_default',$data))$next['is_default']=(bool)$data['is_default'];
        $next['updated_by_member_id']=$actor->id;$kit->update($next);return $kit->fresh();
    }

    /** Deletes one non-referenced brand kit after workspace isolation checks. */
    public function deleteBrandKit(DocumentBrandKit $kit, WorkspaceMember $actor): void
    {
        abort_unless((int)$kit->workspace_id===(int)$actor->workspace_id,404);
        abort_if($this->settingsReferenceExists($actor->workspace_id,'brand_kit_id',$kit->id),422,'This brand kit is still referenced by a document template or autosaved draft.');$kit->delete();
    }

    /** Creates one reusable page master from normalized page, header, footer and watermark settings. */
    public function createPageMaster(WorkspaceMember $actor, array $data): DocumentPageMaster
    {
        if (! empty($data['is_default'])) DocumentPageMaster::query()->where('workspace_id',$actor->workspace_id)->update(['is_default'=>false]);
        $normalized=$this->templates->validatedSettings(['page'=>$data['page_settings']??[],'header'=>$data['header_settings']??[],'footer'=>$data['footer_settings']??[],'watermark'=>$data['watermark_settings']??[]]);
        return DocumentPageMaster::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$actor->workspace_id,'name'=>trim($data['name']),'page_settings'=>$normalized['page'],'header_settings'=>$normalized['header'],'footer_settings'=>$normalized['footer'],'watermark_settings'=>$normalized['watermark'],'is_default'=>(bool)($data['is_default']??false),'created_by_member_id'=>$actor->id,'updated_by_member_id'=>$actor->id]);
    }

    /** Updates one page master while preserving normalized print-safe settings. */
    public function updatePageMaster(DocumentPageMaster $master, WorkspaceMember $actor, array $data): DocumentPageMaster
    {
        abort_unless((int)$master->workspace_id===(int)$actor->workspace_id,404);
        if (! empty($data['is_default'])) DocumentPageMaster::query()->where('workspace_id',$actor->workspace_id)->whereKeyNot($master->id)->update(['is_default'=>false]);
        $normalized=$this->templates->validatedSettings(['page'=>$data['page_settings']??$master->page_settings,'header'=>$data['header_settings']??$master->header_settings,'footer'=>$data['footer_settings']??$master->footer_settings,'watermark'=>$data['watermark_settings']??$master->watermark_settings]);
        $master->update(['name'=>trim((string)($data['name']??$master->name)),'page_settings'=>$normalized['page'],'header_settings'=>$normalized['header'],'footer_settings'=>$normalized['footer'],'watermark_settings'=>$normalized['watermark'],'is_default'=>(bool)($data['is_default']??$master->is_default),'updated_by_member_id'=>$actor->id]);return $master->fresh();
    }

    /** Deletes one page master only when active templates no longer reference it. */
    public function deletePageMaster(DocumentPageMaster $master, WorkspaceMember $actor): void
    {
        abort_unless((int)$master->workspace_id===(int)$actor->workspace_id,404);
        abort_if($this->pageMasterReferenceExists($actor->workspace_id,$master->id),422,'This page master is still referenced by a document template, logical page or autosaved draft.');$master->delete();
    }

    /** Queues a persistent large batch with optional client idempotency for safe HTTP retries. */
    public function queueBatchGenerate(DocumentTemplate $template, WorkspaceMember $actor, array $sourceIds, ?string $sourceType=null, ?string $clientRequestId=null): DocumentBatchJob
    {
        abort_unless((int)$template->workspace_id===(int)$actor->workspace_id,404);
        $ids=array_values(array_unique(array_map('intval',$sourceIds)));
        if(!$ids||count($ids)>500||min($ids)<1)throw ValidationException::withMessages(['source_ids'=>['Choose between 1 and 500 valid source IDs.']]);
        $clientRequestId=$clientRequestId!==null?trim($clientRequestId):null;
        $payload=['uuid'=>(string)Str::uuid(),'document_template_id'=>$template->id,'source_type'=>$sourceType,'source_ids'=>$ids,'status'=>'queued','requested_count'=>count($ids),'processed_count'=>0,'generated_count'=>0,'failed_count'=>0,'attempt_count'=>0,'results'=>[],'requested_by_member_id'=>$actor->id];
        if($clientRequestId){
            $job=DocumentBatchJob::query()->firstOrCreate(['workspace_id'=>$actor->workspace_id,'client_request_id'=>$clientRequestId],$payload);
            $same=(int)$job->document_template_id===(int)$template->id
                && (string)($job->source_type??'')===(string)($sourceType??'')
                && array_values((array)$job->source_ids)===$ids;
            if(!$same)throw ValidationException::withMessages(['client_request_id'=>['This request ID was already used for a different document batch.']]);
            return $job;
        }
        return DocumentBatchJob::create(['workspace_id'=>$template->workspace_id,'client_request_id'=>null,...$payload]);
    }

    /** Processes bounded persistent batch work with durable heartbeats and stale-running recovery. */
    public function processQueuedBatches(int $jobLimit=5, int $sourceLimit=25): array
    {
        $recovered=$this->recoverStaleBatches();
        $summary=['jobs'=>0,'sources'=>0,'generated'=>0,'failed'=>0,'recovered'=>$recovered];
        $jobs=DocumentBatchJob::query()->whereIn('status',['queued','running'])->orderBy('id')->limit(max(1,min(20,$jobLimit)))->get();
        foreach($jobs as $job){
            $touched=false;
            for($attempt=0;$attempt<max(1,min(100,$sourceLimit));$attempt++){
                $result=DB::transaction(function()use($job){
                    $locked=DocumentBatchJob::query()->lockForUpdate()->find($job->id);
                    if(!$locked||!in_array($locked->status,['queued','running'],true))return ['state'=>'done'];
                    $template=DocumentTemplate::query()->find($locked->document_template_id);
                    $actor=WorkspaceMember::query()->find($locked->requested_by_member_id);
                    if(!$template||!$actor){$locked->update(['status'=>'failed','last_error'=>'Template or requesting member is unavailable.','heartbeat_at'=>now(),'completed_at'=>now()]);return ['state'=>'invalid'];}
                    $ids=array_values((array)$locked->source_ids);$offset=(int)$locked->processed_count;
                    if($offset>=count($ids)){$status=$locked->failed_count>0?($locked->generated_count>0?'partial':'failed'):'completed';$locked->update(['status'=>$status,'heartbeat_at'=>now(),'completed_at'=>now()]);return ['state'=>'done'];}
                    $sourceId=(int)$ids[$offset];
                    $locked->update(['status'=>'running','started_at'=>$locked->started_at?:now(),'heartbeat_at'=>now(),'attempt_count'=>(int)$locked->attempt_count+1,'last_error'=>null]);
                    $results=(array)($locked->results??[]);$generated=(int)$locked->generated_count;$failed=(int)$locked->failed_count;$state='generated';$lastError=null;
                    try{$document=$this->templates->generate($template,$actor,$sourceId,$locked->source_type);$results[]=['source_id'=>$sourceId,'status'=>'generated','document_id'=>$document->id,'uuid'=>$document->uuid,'filename'=>$document->filename];$generated++;}
                    catch(\Throwable $exception){report($exception);$lastError='Generation failed for source '.$sourceId.'.';$results[]=['source_id'=>$sourceId,'status'=>'failed','message'=>$lastError];$failed++;$state='failed';}
                    $processed=$offset+1;$done=$processed>=count($ids);$status=$done?($failed>0?($generated>0?'partial':'failed'):'completed'):'running';
                    $locked->update(['processed_count'=>$processed,'generated_count'=>$generated,'failed_count'=>$failed,'results'=>array_slice($results,-1000),'status'=>$status,'heartbeat_at'=>now(),'last_error'=>$lastError,'completed_at'=>$done?now():null]);
                    return ['state'=>$state,'done'=>$done];
                });
                if(($result['state']??'done')==='done'||($result['state']??'')==='invalid')break;
                $touched=true;$summary['sources']++;$summary[$result['state']]++;
                if(!empty($result['done']))break;
            }
            if($touched)$summary['jobs']++;
        }
        return $summary;
    }

    /** Requeues stale running jobs without rewinding their already committed source cursor. */
    public function recoverStaleBatches(int $minutes=10): int
    {
        $cutoff=now()->subMinutes(max(2,min(120,$minutes)));
        return DocumentBatchJob::query()->where('status','running')->where(function($query)use($cutoff){$query->whereNull('heartbeat_at')->where('started_at','<',$cutoff)->orWhere('heartbeat_at','<',$cutoff);})->update(['status'=>'queued','last_error'=>'Recovered after an interrupted batch worker.','heartbeat_at'=>now()]);
    }

    /** Returns recent persistent batch jobs for the current workspace. */
    public function batchJobs(WorkspaceMember $actor): array
    {
        return DocumentBatchJob::query()->with('template:id,name')->where('workspace_id',$actor->workspace_id)->latest('id')->limit(100)->get()->all();
    }

    /** Validates a hex color token and falls back to a safe default. */
    private function color(mixed $value,string $fallback): string { $value=(string)$value;return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)?strtoupper($value):$fallback; }
    /** Returns only renderer-supported font families for persisted brand kits. */
    private function font(mixed $value): string { $value=(string)$value;return in_array($value,['Arial','Helvetica','Georgia','Times New Roman','Courier New','Noto Sans','Noto Sans Arabic'],true)?$value:'Arial'; }
    /** Resolves an optional workspace-owned active image asset for brand-kit logos. */
    private function logoAssetId(WorkspaceMember $actor,int $assetId): ?int { if($assetId<1)return null;$asset=MediaAsset::query()->where('workspace_id',$actor->workspace_id)->whereKey($assetId)->whereNull('deleted_at')->first();abort_unless($asset&&$asset->category()==='image',422,'Choose an active workspace image asset for the brand logo.');return $asset->id; }

    /** Returns whether persisted template or mutable draft settings reference one linked resource ID. */
    private function settingsReferenceExists(int $workspaceId, string $key, int $id): bool
    {
        foreach(DocumentTemplate::query()->where('workspace_id',$workspaceId)->get(['settings']) as $template)if((int)data_get($template->settings,$key)===$id)return true;
        foreach(DocumentTemplateDraft::query()->where('workspace_id',$workspaceId)->get(['settings']) as $draft)if((int)data_get($draft->settings,$key)===$id)return true;
        return false;
    }

    /** Returns whether a page master is linked globally or by any logical page in saved/draft schemas. */
    private function pageMasterReferenceExists(int $workspaceId, int $id): bool
    {
        if($this->settingsReferenceExists($workspaceId,'page_master_id',$id))return true;
        foreach(DocumentTemplate::query()->where('workspace_id',$workspaceId)->get(['content_schema']) as $template)if($this->schemaReferencesPageMaster((array)$template->content_schema,$id))return true;
        foreach(DocumentTemplateDraft::query()->where('workspace_id',$workspaceId)->get(['content_schema']) as $draft)if($this->schemaReferencesPageMaster((array)$draft->content_schema,$id))return true;
        return false;
    }

    /** Scans only logical-page metadata for page-master references without string-matching JSON storage. */
    private function schemaReferencesPageMaster(array $schema, int $id): bool
    {
        foreach($schema as $block)if(is_array($block)&&($block['type']??null)==='page'&&(int)($block['page_master_id']??0)===$id)return true;
        return false;
    }

    /** Normalizes editable metadata kept with an autosave but not persisted until explicit Save. */
    private function metadata(DocumentTemplate $template, array $metadata): array
    {
        return ['name'=>Str::limit(trim((string)($metadata['name']??$template->name)),160,''),'language'=>(string)($metadata['language']??$template->language),'paper_size'=>(string)($metadata['paper_size']??$template->paper_size),'orientation'=>(string)($metadata['orientation']??$template->orientation),'primary_color'=>(string)($metadata['primary_color']??$template->primary_color),'secondary_color'=>(string)($metadata['secondary_color']??$template->secondary_color),'font_family'=>(string)($metadata['font_family']??$template->font_family)];
    }

    /** Returns authored pages, wrapping legacy flat schemas as one logical page for compatibility. */
    private function pages(array $schema): array
    {
        $pages = array_values(array_filter($schema, fn ($block) => is_array($block) && ($block['type'] ?? null) === 'page'));
        return $pages ?: [['id'=>'legacy-page-1','type'=>'page','label'=>'Page 1','children'=>$schema]];
    }

    /** Recursively inspects V6 block references and accessibility-sensitive configuration. */
    private function inspectBlocks(array $blocks, DocumentTemplate $template, callable $add, string $pageId): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $id=(string)($block['id']??'');$type=(string)($block['type']??'');
            if ($type==='image' || $type==='logo') {
                $assetId=(int)($block['media_asset_id']??0);
                if ($assetId<1) $add('error','media.missing','Choose a Media Library asset.',$id,$pageId);
                elseif (! MediaAsset::query()->where('workspace_id',$template->workspace_id)->whereKey($assetId)->whereNull('deleted_at')->exists()) $add('error','media.not_found','Referenced media is unavailable in this workspace.',$id,$pageId);
                if ($type==='image' && trim((string)($block['alt']??''))==='') $add('warning','media.alt','Add alternative text for this document image.',$id,$pageId);
            }
            if ($type==='reusable') {
                $componentId=(int)($block['component_id']??0);
                if ($componentId<1 || ! DocumentComponent::query()->where('workspace_id',$template->workspace_id)->whereKey($componentId)->exists()) $add('error','component.missing','Choose an available reusable component.',$id,$pageId);
            }
            if (in_array($type,['table','repeat'],true) && trim((string)($block['source']??''))==='') $add('error','data.source','Choose a data source for this repeating content.',$id,$pageId);
            if($type==='table'){$columns=is_array($block['columns']??null)?$block['columns']:[];$widthTotal=array_sum(array_map(fn($column)=>is_array($column)?max(0,(float)($column['width']??0)):0,$columns));if($widthTotal>100.01)$add('warning','table.width_total','Configured table column widths exceed 100%; browser/PDF layout will rebalance them.',$id,$pageId);if(!$columns)$add('warning','table.columns','Add at least one table column before generation.',$id,$pageId);}
            if ($type==='formula' && trim((string)($block['expression']??''))==='') $add('error','formula.empty','Formula expression cannot be empty.',$id,$pageId);
            if (is_array($block['children']??null)) $this->inspectBlocks($block['children'],$template,$add,$pageId);
            if ($type==='columns') foreach ((array)($block['columns']??[]) as $column) if (is_array($column) && is_array($column['children']??null)) $this->inspectBlocks($column['children'],$template,$add,$pageId);
        }
    }

    /** Returns true when any nested authored block matches the requested type. */
    private function containsBlockType(array $blocks, string $type): bool
    {
        foreach($blocks as $block){if(!is_array($block))continue;if(($block['type']??null)===$type)return true;if(is_array($block['children']??null)&&$this->containsBlockType($block['children'],$type))return true;if(($block['type']??null)==='columns')foreach((array)($block['columns']??[]) as $column)if(is_array($column)&&is_array($column['children']??null)&&$this->containsBlockType($column['children'],$type))return true;}
        return false;
    }

    /** Counts all structural and content blocks for preflight reporting. */
    private function blockCount(array $blocks): int
    {
        $count=0;
        foreach($blocks as $block){if(!is_array($block))continue;$count++;if(is_array($block['children']??null))$count+=$this->blockCount($block['children']);if(($block['type']??'')==='columns')foreach((array)($block['columns']??[]) as $column)if(is_array($column)&&is_array($column['children']??null))$count+=$this->blockCount($column['children']);}
        return $count;
    }
}
