<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\DocumentTemplateDraft;
use App\Models\GeneratedDocument;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Automation\AutomationEngine;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\LocaleCatalog;

/** Provides document template service behavior within the WorkIntel application. */ class DocumentTemplateService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly DocumentTemplateRenderer $renderer, private readonly DocumentPdfRenderer $pdfRenderer, private readonly DocumentContextService $contexts) {}

    /** Handles the default template operation for the current WorkIntel workflow. */ public function defaultTemplate(Workspace $workspace,string $type,string $language='en',?int $legalEntityId=null):?DocumentTemplate
    {
        return DocumentTemplate::query()->where('workspace_id',$workspace->id)->where('document_type',$type)->where('status','active')
            ->where(function($q)use($language){$q->where('language',$language)->orWhere('language','en');})
            ->where(function($q) use ($legalEntityId) {
                if ($legalEntityId) $q->where('legal_entity_id',$legalEntityId)->orWhereNull('legal_entity_id');
                else $q->whereNull('legal_entity_id');
            })
            ->when($legalEntityId, fn($q) => $q->orderByRaw('CASE WHEN legal_entity_id = ? THEN 0 ELSE 1 END',[$legalEntityId]))
            ->orderByRaw('CASE WHEN language = ? THEN 0 ELSE 1 END',[$language])
            ->orderByDesc('is_default')->orderBy('id')->first();
    }

    /** Creates and persists the requested resource. */ public function create(Workspace $workspace,WorkspaceMember $actor,array $data):DocumentTemplate
    {
        $type=$data['document_type'];if(!isset(DocumentTemplateCatalog::TYPES[$type]))throw ValidationException::withMessages(['document_type'=>['Unsupported document type.']]);$data['language']=LocaleCatalog::normalize($data['language']??$workspace->preferences?->default_language??'en');
        $schema=$this->validateSchema($data['content_schema']??DocumentTemplateCatalog::defaultSchema($type));$settings=$this->validateSettings($data['settings']??[]);
        $slug=$this->uniqueSlug($workspace,Str::slug($data['name'])?:$type);
        return DB::transaction(function()use($workspace,$actor,$data,$type,$slug,$schema,$settings){
            $template=DocumentTemplate::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'legal_entity_id'=>$data['legal_entity_id']??null,'name'=>$data['name'],'slug'=>$slug,'document_type'=>$type,'language'=>$data['language']??'en','status'=>'active','is_default'=>false,'paper_size'=>$data['paper_size']??'A4','orientation'=>$data['orientation']??'portrait','primary_color'=>$data['primary_color']??'#111827','secondary_color'=>$data['secondary_color']??'#6B7280','font_family'=>$this->fontFamily($data['font_family']??'Arial'),'content_schema'=>$schema,'settings'=>$settings,'current_version'=>1,'created_by'=>$actor->user_id,'updated_by'=>$actor->user_id]);
            $this->snapshot($template,$actor,'Initial version');return $template;
        });
    }

    /** Updates update data for the requested resource. */ public function update(DocumentTemplate $template,WorkspaceMember $actor,array $data):DocumentTemplate
    {
        if (array_key_exists('content_schema',$data)) $data['content_schema']=$this->validateSchema($data['content_schema']);
        if (array_key_exists('settings',$data)) $data['settings']=$this->validateSettings($data['settings']??[]);
        if (array_key_exists('font_family',$data)) $data['font_family']=$this->fontFamily((string)$data['font_family']);
        if (array_key_exists('language',$data)) $data['language']=LocaleCatalog::normalize($data['language']);
        return DB::transaction(function()use($template,$actor,$data){
            $before=json_encode([$template->content_schema,$template->settings,$template->name,$template->language,$template->legal_entity_id,$template->paper_size,$template->orientation,$template->primary_color,$template->secondary_color,$template->font_family]);
            $template->fill(array_intersect_key($data,array_flip(['name','legal_entity_id','language','status','paper_size','orientation','primary_color','secondary_color','font_family','content_schema','settings'])));$template->updated_by=$actor->user_id;$after=json_encode([$template->content_schema,$template->settings,$template->name,$template->language,$template->legal_entity_id,$template->paper_size,$template->orientation,$template->primary_color,$template->secondary_color,$template->font_family]);
            if($before!==$after){$template->current_version=(int)$template->current_version+1;$template->save();$this->snapshot($template,$actor,$data['change_note']??'Template updated');}else{$template->save();}
            DocumentTemplateDraft::query()->where('document_template_id',$template->id)->delete();
            return $template->fresh(['versions'=>fn($q)=>$q->latest('version')->limit(20)]);
        });
    }

    public function clone(DocumentTemplate $template,WorkspaceMember $actor,string $name):DocumentTemplate
    {
        return $this->create($template->workspace,$actor,['name'=>$name,'document_type'=>$template->document_type,'legal_entity_id'=>$template->legal_entity_id,'language'=>$template->language,'paper_size'=>$template->paper_size,'orientation'=>$template->orientation,'primary_color'=>$template->primary_color,'secondary_color'=>$template->secondary_color,'font_family'=>$template->font_family,'content_schema'=>$template->content_schema,'settings'=>$template->settings]);
    }

    /** Handles the clone language variant operation for the current WorkIntel workflow. */ public function cloneLanguageVariant(DocumentTemplate $template,WorkspaceMember $actor,string $language,?string $name=null):DocumentTemplate
    {
        $language=LocaleCatalog::normalize($language);abort_if($language===$template->language,422,'Choose a different language for the variant.');
        $duplicate=DocumentTemplate::where('workspace_id',$template->workspace_id)->where('document_type',$template->document_type)->where('language',$language)
            ->where(function($q)use($template){$template->legal_entity_id?$q->where('legal_entity_id',$template->legal_entity_id):$q->whereNull('legal_entity_id');})
            ->get(['id','settings'])->first(fn($candidate)=>(int)data_get($candidate->settings,'translation_source_template_id')===(int)$template->id);
        abort_if($duplicate,422,'A language variant for this template and language already exists.');
        $name=$name?:$template->name.' — '.LocaleCatalog::LABELS[$language];
        return $this->create($template->workspace,$actor,['name'=>$name,'document_type'=>$template->document_type,'legal_entity_id'=>$template->legal_entity_id,'language'=>$language,'paper_size'=>$template->paper_size,'orientation'=>$template->orientation,'primary_color'=>$template->primary_color,'secondary_color'=>$template->secondary_color,'font_family'=>$template->font_family,'content_schema'=>$template->content_schema,'settings'=>array_merge($template->settings??[],['translation_source_template_id'=>$template->id])]);
    }

    /** Handles the set default operation for the current WorkIntel workflow. */ public function setDefault(DocumentTemplate $template):DocumentTemplate
    {
        DB::transaction(function()use($template){DocumentTemplate::where('workspace_id',$template->workspace_id)->where('document_type',$template->document_type)->where('language',$template->language)->where(function($q)use($template){$template->legal_entity_id?$q->where('legal_entity_id',$template->legal_entity_id):$q->whereNull('legal_entity_id');})->update(['is_default'=>false]);$template->update(['is_default'=>true,'status'=>'active']);});return $template->fresh();
    }

    /** Handles the restore version operation for the current WorkIntel workflow. */ public function restoreVersion(DocumentTemplate $template,DocumentTemplateVersion $version,WorkspaceMember $actor):DocumentTemplate
    {
        abort_unless($version->document_template_id===$template->id,404);return $this->update($template,$actor,['content_schema'=>$version->content_schema,'settings'=>$version->settings,'change_note'=>'Restored version '.$version->version]);
    }

    /** Handles the preview operation for the current WorkIntel workflow. */ public function preview(DocumentTemplate $template,WorkspaceMember $actor,?int $sourceId=null):array
    {
        $context=$this->contextFor($template,$actor,$sourceId);
        return ['html'=>$this->renderer->renderHtml($template,$context),'context'=>$context];
    }

    /** Renders unsaved designer state after applying the same validation rules used by persisted templates. */
    public function previewDraft(DocumentTemplate $template, WorkspaceMember $actor, array $data, ?int $sourceId = null): array
    {
        $draft = $template->replicate();
        if (array_key_exists('content_schema', $data)) $draft->content_schema = $this->validateSchema($data['content_schema']);
        if (array_key_exists('settings', $data)) $draft->settings = $this->validateSettings($data['settings'] ?? []);
        foreach (['paper_size','orientation','primary_color','secondary_color','font_family','language'] as $field) {
            if (array_key_exists($field, $data)) $draft->{$field} = $field === 'font_family' ? $this->fontFamily((string) $data[$field]) : $data[$field];
        }
        $context = $this->contextFor($template, $actor, $sourceId);
        return ['html' => $this->renderer->renderHtml($draft, $context), 'context' => $context];
    }

    /** Handles the generate operation for the current WorkIntel workflow. */ public function generate(DocumentTemplate $template,WorkspaceMember $actor,?int $sourceId=null,?string $sourceType=null):GeneratedDocument
    {
        abort_unless($template->status==='active',422,'Archived templates cannot generate documents.');
        $context=$this->contextFor($template,$actor,$sourceId);
        $render=$this->pdfRenderer->render($template,$context);$pdf=$render['bytes'];$uuid=(string)Str::uuid();$filename=(Str::slug($template->name)?:'document').'-'.now()->format('Ymd-His').'.pdf';$path='private/generated-documents/'.$template->workspace_id.'/'.$uuid.'/'.$filename;$disk='local';Storage::disk($disk)->put($path,$pdf);
        $document = GeneratedDocument::create(['uuid'=>$uuid,'workspace_id'=>$template->workspace_id,'document_template_id'=>$template->id,'document_type'=>$template->document_type,'source_type'=>$sourceType?:($sourceId?$template->document_type:null),'source_id'=>$sourceId,'language'=>$template->language,'status'=>'completed','workflow_status'=>'generated','render_driver'=>$render['driver'],'render_metadata'=>['unicode_capable'=>(bool)$render['unicode_capable'],'studio_version'=>(int)data_get($template->settings,'studio_version',6),'workflow_policy'=>['review_required'=>(bool)data_get($template->settings,'workflow.review_required',false),'approval_required'=>(bool)data_get($template->settings,'workflow.approval_required',false),'signature_required'=>(bool)data_get($template->settings,'workflow.signature_required',false),'signer_role'=>trim((string)data_get($template->settings,'workflow.signer_role',''))]],'render_context_encrypted'=>Crypt::encryptString(json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'{}'),'disk'=>$disk,'path'=>$path,'filename'=>$filename,'mime_type'=>'application/pdf','size_bytes'=>strlen($pdf),'sha256'=>hash('sha256',$pdf),'variables_snapshot'=>$this->redactedSnapshot($context),'generated_by'=>$actor->user_id,'generated_at'=>now()]);
        try {
            app(AutomationEngine::class)->emit($template->workspace, 'documents.generated', [
                'document_id' => $document->id,
                'document_uuid' => $document->uuid,
                'document_type' => $document->document_type,
                'template_id' => $template->id,
                'source_type' => $document->source_type,
                'source_id' => $document->source_id,
                'filename' => $document->filename,
            ], 'documents', 'document-generated:'.$document->uuid);
        } catch (\Throwable $e) {
            // Document generation must not fail because an optional automation
            // or third-party connector is unavailable.
            report($e);
        }
        return $document;
    }

    /** Builds render default output for the current workflow. */ public function renderDefault(Workspace $workspace,WorkspaceMember $actor,string $type,int $sourceId):?string
    {
        $language=$actor->user?->preferredLocale()?:($workspace->preferences?->default_language?:'en');$template=$this->defaultTemplate($workspace,$type,$language);if(!$template)return null;$context=$this->contexts->forSource($workspace,$actor,$type,$sourceId);return $this->pdfRenderer->render($template,$context)['bytes'];
    }

    /** Exposes schema validation to reusable-component and collaboration services without duplicating rules. */
    public function validatedSchema(array $schema): array
    {
        return $this->validateSchema($schema);
    }

    /** Validates nested V4 block schemas, unique IDs and safe block-specific configuration. */
    private function validateSchema(array $schema): array
    {
        $pageCount = count(array_filter($schema, fn ($block) => is_array($block) && ($block['type'] ?? null) === 'page'));
        if ($pageCount > 0 && $pageCount !== count($schema)) throw ValidationException::withMessages(['content_schema'=>['V6 page containers cannot be mixed with legacy root blocks.']]);
        if ($pageCount > 50) throw ValidationException::withMessages(['content_schema'=>['A V6 document can contain at most 50 authored pages.']]);
        $ids = [];
        $count = 0;
        $this->validateBlocks($schema, 'content_schema', $ids, $count, 0);
        if ($count > 300) throw ValidationException::withMessages(['content_schema'=>['A template can contain at most 300 blocks including nested blocks.']]);
        return array_values($schema);
    }

    /** Recursively validates one block collection while enforcing depth, size and source-path limits. */
    private function validateBlocks(array $blocks, string $path, array &$ids, int &$count, int $depth): void
    {
        if ($depth > 8) throw ValidationException::withMessages([$path=>['Document block nesting cannot exceed eight levels.']]);
        if (count($blocks) > 120) throw ValidationException::withMessages([$path=>['This block collection is too large.']]);
        foreach ($blocks as $index => $block) {
            $current = $path.'.'.$index;
            if (! is_array($block)) throw ValidationException::withMessages([$current=>['Each document block must be an object.']]);
            $count++;
            $id=(string)($block['id']??'');$type=(string)($block['type']??'');
            if ($id==='' || strlen($id)>100 || ! preg_match('/^[A-Za-z0-9_.:-]+$/',$id)) throw ValidationException::withMessages([$current.'.id'=>['Use a unique block ID containing letters, numbers, dot, colon, dash or underscore.']]);
            if (isset($ids[$id])) throw ValidationException::withMessages([$current.'.id'=>['Block IDs must be unique across the whole template.']]);
            $ids[$id]=true;
            if (! isset(DocumentTemplateCatalog::BLOCKS[$type])) throw ValidationException::withMessages([$current.'.type'=>['Unsupported document block type.']]);
            if ($type==='page') {
                if ($depth!==0) throw ValidationException::withMessages([$current=>['Page containers are allowed only at the document root.']]);
                if(isset($block['page_master_id'])&&((int)$block['page_master_id']<1||(string)(int)$block['page_master_id']!==(string)$block['page_master_id']))throw ValidationException::withMessages([$current.'.page_master_id'=>['Use a valid page master ID.']]);
                $overrideBytes=strlen(json_encode(array_intersect_key($block,array_flip(['page_settings','header_settings','footer_settings','watermark_settings'])))?:'');
                if($overrideBytes>30000)throw ValidationException::withMessages([$current=>['Page-specific settings are too large.']]);
                if(is_array($block['page_settings']??null))foreach(['margin_top','margin_right','margin_bottom','margin_left'] as $margin)if(isset($block['page_settings'][$margin])&&((float)$block['page_settings'][$margin]<5||(float)$block['page_settings'][$margin]>45))throw ValidationException::withMessages([$current.'.page_settings.'.$margin=>['Page margins must be between 5 and 45 mm.']]);
                $children=is_array($block['children']??null)?$block['children']:[];
                $this->validateBlocks($children,$current.'.children',$ids,$count,$depth+1);
                continue;
            }
            foreach (['text','html','label','value','prefix','suffix','caption','expression'] as $field) if (isset($block[$field]) && (! is_string($block[$field]) || strlen($block[$field])>30000)) throw ValidationException::withMessages([$current.'.'.$field=>['Block text is too large.']]);
            foreach (['source'] as $field) if (isset($block[$field]) && $block[$field]!=='' && ! preg_match('/^[A-Za-z0-9_.-]+$/',(string)$block[$field])) throw ValidationException::withMessages([$current.'.'.$field=>['Use a valid variable path.']]);
            if ($type==='table') {
                $columns=$block['columns']??[];if(!is_array($columns)||count($columns)>20)throw ValidationException::withMessages([$current.'.columns'=>['Tables can contain at most 20 columns.']]);
                foreach($columns as $columnIndex=>$column){
                    if(!is_array($column)||strlen((string)($column['key']??''))>120||strlen((string)($column['label']??''))>160) throw ValidationException::withMessages([$current.'.columns.'.$columnIndex=>['Invalid table column.']]);
                    $format=(string)($column['format']??'text');if(!in_array($format,['text','number','currency','date','percent'],true))throw ValidationException::withMessages([$current.'.columns.'.$columnIndex.'.format'=>['Unsupported table column format.']]);
                    if(isset($column['width'])&&((float)$column['width']<5||(float)$column['width']>100))throw ValidationException::withMessages([$current.'.columns.'.$columnIndex.'.width'=>['Table column width must be between 5 and 100 percent.']]);
                }
            }
            if (in_array($type,['key_value','totals'],true)) {
                $items=$block['items']??[];if(!is_array($items)||count($items)>80)throw ValidationException::withMessages([$current.'.items'=>['This block can contain at most 80 rows.']]);
            }
            if ($type==='conditional') {
                $condition=is_array($block['condition']??null)?$block['condition']:[];
                $operator=(string)($condition['operator']??'truthy');
                if(!in_array($operator,['eq','neq','gt','gte','lt','lte','contains','empty','not_empty','truthy','falsy'],true)) throw ValidationException::withMessages([$current.'.condition.operator'=>['Unsupported condition operator.']]);
                $this->validateBlocks(is_array($block['children']??null)?$block['children']:[],$current.'.children',$ids,$count,$depth+1);
            }
            if ($type==='repeat') $this->validateBlocks(is_array($block['children']??null)?$block['children']:[],$current.'.children',$ids,$count,$depth+1);
            if ($type==='columns') {
                $columns=is_array($block['columns']??null)?$block['columns']:[];
                if(count($columns)<2||count($columns)>4) throw ValidationException::withMessages([$current.'.columns'=>['Layout columns require between two and four columns.']]);
                foreach($columns as $columnIndex=>$column){if(!is_array($column))throw ValidationException::withMessages([$current.'.columns.'.$columnIndex=>['Invalid layout column.']]);$this->validateBlocks(is_array($column['children']??null)?$column['children']:[],$current.'.columns.'.$columnIndex.'.children',$ids,$count,$depth+1);}
            }
            if ($type==='formula' && isset($block['expression'])) app(DocumentExpressionEngine::class)->formula((string)$block['expression'], DocumentTemplateCatalog::sample('invoice'));
            if ($type==='image' && isset($block['media_asset_id']) && (!is_int($block['media_asset_id'])&&!ctype_digit((string)$block['media_asset_id']))) throw ValidationException::withMessages([$current.'.media_asset_id'=>['Media asset ID must be numeric.']]);
        }
    }

    /** Exposes V6 settings validation to autosave and preflight services without creating a version. */
    public function validatedSettings(array $settings): array
    {
        return $this->validateSettings($settings);
    }

    /** Validates and normalizes paged designer settings with bounded margins and safe authored text. */
    private function validateSettings(array $settings): array
    {
        if (strlen(json_encode($settings) ?: '') > 200000) throw ValidationException::withMessages(['settings'=>['Document settings are too large.']]);
        $page=is_array($settings['page']??null)?$settings['page']:[];
        foreach(['margin_top','margin_right','margin_bottom','margin_left'] as $key) $page[$key]=min(45,max(5,(float)($page[$key]??($key==='margin_bottom'?20:18))));
        $page['background']=is_string($page['background']??null)&&preg_match('/^#[0-9A-Fa-f]{6}$/',$page['background'])?$page['background']:'#FFFFFF';
        $settings['page']=$page;
        foreach(['header','footer'] as $region){$value=is_array($settings[$region]??null)?$settings[$region]:[];$settings[$region]=['enabled'=>(bool)($value['enabled']??false),'text'=>$this->limitText((string)($value['text']??''),5000),'divider'=>(bool)($value['divider']??true)];}
        $watermark=is_array($settings['watermark']??null)?$settings['watermark']:[];$settings['watermark']=['enabled'=>(bool)($watermark['enabled']??false),'text'=>$this->limitText((string)($watermark['text']??'DRAFT'),120),'opacity'=>min(.25,max(.02,(float)($watermark['opacity']??.08)))];
        $settings['studio_version']=6;
        return $settings;
    }

    /** Returns a safe font family supported by preview and browser PDF rendering. */
    private function fontFamily(string $font): string
    {
        return in_array($font,['Arial','Helvetica','Georgia','Times New Roman','Courier New','Noto Sans','Noto Sans Arabic'],true)?$font:'Arial';
    }

    /** Truncates authored configuration text with or without mbstring. */
    private function limitText(string $value, int $limit): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    /** Returns a source-backed or deterministic sample render context. */
    private function contextFor(DocumentTemplate $template, WorkspaceMember $actor, ?int $sourceId): array
    {
        if ($sourceId) return $this->contexts->forSource($template->workspace,$actor,$template->document_type,$sourceId);
        return array_replace_recursive($this->contexts->workspaceContext($template->workspace),DocumentTemplateCatalog::sample($template->document_type));
    }

    /** Handles the snapshot operation for the current WorkIntel workflow. */ private function snapshot(DocumentTemplate $template,WorkspaceMember $actor,string $note):void{DocumentTemplateVersion::create(['document_template_id'=>$template->id,'version'=>$template->current_version,'content_schema'=>$template->content_schema,'settings'=>$template->settings,'change_note'=>$note,'created_by'=>$actor->user_id,'created_at'=>now()]);}
    /** Handles the unique slug operation for the current WorkIntel workflow. */ private function uniqueSlug(Workspace $workspace,string $base):string{$slug=$base;$i=2;while(DocumentTemplate::where('workspace_id',$workspace->id)->where('slug',$slug)->exists())$slug=$base.'-'.$i++;return $slug;}
    /** Handles the redacted snapshot operation for the current WorkIntel workflow. */ private function redactedSnapshot(array $context):array{$copy=$context;if(isset($copy['pay']))$copy['pay']=['currency'=>$copy['pay']['currency']??null,'redacted'=>true];return $copy;}
}
