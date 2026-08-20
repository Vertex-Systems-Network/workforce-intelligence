<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\GeneratedDocument;
use App\Models\LegalEntity;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTemplateCatalog;
use App\Services\Documents\DocumentCodeRenderer;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Documents\DocumentTemplateService;
use App\Services\Documents\DocumentStudioV6Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use App\Support\LocaleCatalog;

/** Provides document studio controller behavior within the WorkIntel application. */ class DocumentStudioController extends Controller
{
    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request, DocumentAccessService $access): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');
        $templates=DocumentTemplate::with('legalEntity:id,name')->where('workspace_id',$workspace->id)->orderBy('document_type')->orderByDesc('is_default')->orderBy('name')->get();
        $generated=GeneratedDocument::with('template:id,name')->withCount(['shareLinks','signatureRequests'])->where('workspace_id',$workspace->id)->whereIn('document_type',$access->visibleTypes($actor))->latest('generated_at')->limit(100)->get();
        return response()->json([
            'templates'=>$templates,'generated'=>$generated,
            'catalog'=>['types'=>DocumentTemplateCatalog::TYPES,'blocks'=>DocumentTemplateCatalog::BLOCKS,'variables'=>collect(array_keys(DocumentTemplateCatalog::TYPES))->mapWithKeys(fn($type)=>[$type=>DocumentTemplateCatalog::variables($type)])->all(),'locales'=>LocaleCatalog::options()],
            'legal_entities'=>LegalEntity::where('workspace_id',$workspace->id)->where('status','active')->orderBy('name')->get(['id','name']),
            'permissions'=>['manage'=>$actor->hasPermission('documents.manage'),'templates_manage'=>$actor->hasPermission('documents.templates_manage'),'generate'=>$actor->hasPermission('documents.generate'),'share'=>$actor->hasPermission('documents.share'),'sign'=>$actor->hasPermission('documents.sign'),'approve'=>$actor->hasPermission('documents.approve'),'components_manage'=>$actor->hasPermission('documents.components_manage')],
            'rendering'=>['pdf_driver'=>config('documents.pdf_driver','auto'),'chromium_available'=>app(DocumentPdfRenderer::class)->browserBinary()!==null,'code_adapter_available'=>app(DocumentCodeRenderer::class)->available()],
        ]);
    }

    /** Returns template details, immutable versions and the latest mutable V6 autosave. */ public function show(Request $request,DocumentTemplate $template,DocumentStudioV6Service $v6):JsonResponse
    {
        $this->ensure($request,$template);return response()->json(['data'=>$template->load(['legalEntity:id,name','versions'=>fn($q)=>$q->latest('version')->limit(50)]),'draft'=>$v6->draftPayload($template)]);
    }

    /** Creates and persists the requested resource. */ public function store(Request $request,DocumentTemplateService $service):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$data=$this->templateData($request,true);$template=$service->create($workspace,$actor,$data);return response()->json(['data'=>$template,'message'=>'Template created.'],201);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$data=$this->templateData($request,false);$data['change_note']=$request->validate(['change_note'=>'nullable|string|max:500'])['change_note']??null;return response()->json(['data'=>$service->update($template,$request->attributes->get('workspaceMember'),$data),'message'=>'Template saved and versioned.']);
    }

    /** Handles the clone template operation for the current WorkIntel workflow. */ public function cloneTemplate(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$name=$request->validate(['name'=>'required|string|max:160'])['name'];return response()->json(['data'=>$service->clone($template,$request->attributes->get('workspaceMember'),$name),'message'=>'Template cloned.'],201);
    }

    /** Handles the language variant operation for the current WorkIntel workflow. */ public function languageVariant(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$data=$request->validate(['language'=>['required',Rule::in(LocaleCatalog::SUPPORTED)],'name'=>'nullable|string|max:160']);
        return response()->json(['data'=>$service->cloneLanguageVariant($template,$request->attributes->get('workspaceMember'),$data['language'],$data['name']??null),'message'=>'Language variant created.'],201);
    }

    /** Handles the set default operation for the current WorkIntel workflow. */ public function setDefault(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);return response()->json(['data'=>$service->setDefault($template),'message'=>'Default template updated.']);
    }

    /** Handles the archive operation for the current WorkIntel workflow. */ public function archive(Request $request,DocumentTemplate $template):JsonResponse
    {
        $this->ensure($request,$template);$template->update(['status'=>'archived','is_default'=>false,'updated_by'=>$request->user()->id]);return response()->json(['message'=>'Template archived. Existing generated documents are preserved.']);
    }

    /** Handles the restore operation for the current WorkIntel workflow. */ public function restore(Request $request,DocumentTemplate $template,DocumentTemplateVersion $version,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);return response()->json(['data'=>$service->restoreVersion($template,$version,$request->attributes->get('workspaceMember')),'message'=>'Historical version restored as a new version.']);
    }

    /** Handles the preview operation for the current WorkIntel workflow. */ public function preview(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$data=$request->validate(['source_id'=>'nullable|integer|min:1']);return response()->json($service->preview($template,$request->attributes->get('workspaceMember'),$data['source_id']??null));
    }

    /** Handles the preview pdf operation for the current WorkIntel workflow. */ public function previewPdf(Request $request,DocumentTemplate $template,DocumentTemplateService $service,DocumentPdfRenderer $renderer):Response
    {
        $this->ensure($request,$template);$preview=$service->preview($template,$request->attributes->get('workspaceMember'),$request->integer('source_id')?:null);$render=$renderer->render($template,$preview['context']);return response($render['bytes'],200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="preview-'.$template->slug.'.pdf"','Cache-Control'=>'no-store','X-WorkIntel-Document-Renderer'=>$render['driver']]);
    }

    /** Renders unsaved V4 designer state so preview never requires creating a version first. */ public function livePreview(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$data=$this->templateData($request,false);$source=$request->validate(['source_id'=>'nullable|integer|min:1'])['source_id']??null;return response()->json($service->previewDraft($template,$request->attributes->get('workspaceMember'),$data,$source));
    }

    /** Handles the generate operation for the current WorkIntel workflow. */ public function generate(Request $request,DocumentTemplate $template,DocumentTemplateService $service):JsonResponse
    {
        $this->ensure($request,$template);$data=$request->validate(['source_id'=>'nullable|integer|min:1','source_type'=>'nullable|string|max:80']);$row=$service->generate($template,$request->attributes->get('workspaceMember'),$data['source_id']??null,$data['source_type']??null);return response()->json(['data'=>$row,'message'=>'PDF generated.'],201);
    }

    /** Handles the download operation for the current WorkIntel workflow. */ public function download(Request $request,GeneratedDocument $document,DocumentAccessService $access):Response
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($document->workspace_id===$workspace->id,404);abort_unless($access->canViewGenerated($actor,$document),403,'You do not have access to this generated document type.');abort_unless(Storage::disk($document->disk)->exists($document->path),404,'Generated file is missing from storage.');return response()->file(Storage::disk($document->disk)->path($document->path),['Content-Type'=>$document->mime_type,'Content-Disposition'=>'attachment; filename="'.addslashes($document->filename).'"','Cache-Control'=>'private, no-store']);
    }

    /** Handles the template data operation for the current WorkIntel workflow. */ private function templateData(Request $request,bool $creating):array
    {
        $rules=[
            'name'=>[$creating?'required':'sometimes','string','max:160'],'document_type'=>[$creating?'required':'sometimes',Rule::in(array_keys(DocumentTemplateCatalog::TYPES))],
            'legal_entity_id'=>'nullable|integer','language'=>['sometimes','string',Rule::in(LocaleCatalog::SUPPORTED)],'status'=>['sometimes',Rule::in(['active','archived'])],'paper_size'=>['sometimes',Rule::in(['A4','Letter'])],'orientation'=>['sometimes',Rule::in(['portrait','landscape'])],
            'primary_color'=>['sometimes','regex:/^#[0-9A-Fa-f]{6}$/'],'secondary_color'=>['sometimes','regex:/^#[0-9A-Fa-f]{6}$/'],'font_family'=>['sometimes',Rule::in(['Arial','Helvetica','Georgia','Times New Roman','Courier New','Noto Sans','Noto Sans Arabic'])],'content_schema'=>[$creating?'nullable':'sometimes','array','max:100'],'content_schema.*'=>'array','settings'=>'nullable|array',
        ];$data=$request->validate($rules);if(isset($data['legal_entity_id'])){ $workspace=$request->attributes->get('workspace');abort_unless(LegalEntity::where('workspace_id',$workspace->id)->whereKey($data['legal_entity_id'])->exists(),422,'Legal entity does not belong to this workspace.');}return $data;
    }
    /** Handles the ensure operation for the current WorkIntel workflow. */ private function ensure(Request $request,DocumentTemplate $template):void{abort_unless($template->workspace_id===$request->attributes->get('workspace')->id,404);}
}
