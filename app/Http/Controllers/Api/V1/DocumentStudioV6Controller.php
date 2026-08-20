<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentBatchJob;
use App\Models\DocumentPageMaster;
use App\Models\DocumentBrandKit;
use App\Services\Documents\DocumentStudioV6Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposes Document Studio V6 mutable drafts, server preflight and bounded batch generation. */
class DocumentStudioV6Controller extends Controller
{
    /** Autosaves current designer state without incrementing immutable template versions. */
    public function autosave(Request $request, DocumentTemplate $template, DocumentStudioV6Service $service): JsonResponse
    {
        $this->ensure($request, $template);
        $data=$request->validate(['content_schema'=>'required|array|max:100','settings'=>'nullable|array','metadata'=>'nullable|array']);
        return response()->json(['data'=>$service->saveDraft($template,$request->attributes->get('workspaceMember'),$data),'message'=>'Document draft autosaved.']);
    }

    /** Discards the mutable autosave while preserving every immutable template version. */
    public function discardDraft(Request $request, DocumentTemplate $template, DocumentStudioV6Service $service): JsonResponse
    {
        $this->ensure($request, $template);
        $service->discardDraft($template,$request->attributes->get('workspaceMember'));
        return response()->json(['message'=>'Autosave draft discarded.']);
    }

    /** Runs server-side PDF/document preflight against persisted or unsaved editor state. */
    public function preflight(Request $request, DocumentTemplate $template, DocumentStudioV6Service $service): JsonResponse
    {
        $this->ensure($request, $template);
        $data=$request->validate(['content_schema'=>'nullable|array|max:100','settings'=>'nullable|array']);
        return response()->json(['data'=>$service->preflight($template,$request->attributes->get('workspaceMember'),$data)]);
    }

    /** Generates one governed PDF per requested source record with per-record failure isolation. */
    public function batchGenerate(Request $request, DocumentTemplate $template, DocumentStudioV6Service $service): JsonResponse
    {
        $this->ensure($request, $template);
        $data=$request->validate(['source_ids'=>'required|array|min:1|max:50','source_ids.*'=>'integer|min:1','source_type'=>'nullable|string|max:80']);
        return response()->json(['data'=>$service->batchGenerate($template,$request->attributes->get('workspaceMember'),$data['source_ids'],$data['source_type']??null),'message'=>'Batch generation completed.']);
    }

    /** Returns advanced V6 brand kits, page masters and recent queued batches. */
    public function resources(Request $request, DocumentStudioV6Service $service): JsonResponse
    {
        return response()->json(['data'=>$service->resources($request->attributes->get('workspaceMember'))]);
    }

    /** Creates one reusable Document Studio brand kit. */
    public function createBrandKit(Request $request, DocumentStudioV6Service $service): JsonResponse
    {
        $data=$request->validate(['name'=>'required|string|max:120','primary_color'=>'nullable|string|max:7','secondary_color'=>'nullable|string|max:7','accent_color'=>'nullable|string|max:7','font_family'=>'nullable|string|max:60','heading_font_family'=>'nullable|string|max:60','logo_media_asset_id'=>'nullable|integer|min:1','settings'=>'nullable|array','is_default'=>'nullable|boolean']);
        return response()->json(['data'=>$service->createBrandKit($request->attributes->get('workspaceMember'),$data),'message'=>'Brand kit created.'],201);
    }

    /** Updates one workspace-owned brand kit. */
    public function updateBrandKit(Request $request, DocumentBrandKit $brandKit, DocumentStudioV6Service $service): JsonResponse
    {
        $data=$request->validate(['name'=>'sometimes|string|max:120','primary_color'=>'nullable|string|max:7','secondary_color'=>'nullable|string|max:7','accent_color'=>'nullable|string|max:7','font_family'=>'nullable|string|max:60','heading_font_family'=>'nullable|string|max:60','logo_media_asset_id'=>'nullable|integer|min:1','settings'=>'nullable|array','is_default'=>'nullable|boolean']);
        return response()->json(['data'=>$service->updateBrandKit($brandKit,$request->attributes->get('workspaceMember'),$data),'message'=>'Brand kit updated.']);
    }

    /** Deletes one unused workspace-owned brand kit. */
    public function deleteBrandKit(Request $request, DocumentBrandKit $brandKit, DocumentStudioV6Service $service): JsonResponse
    {
        $service->deleteBrandKit($brandKit,$request->attributes->get('workspaceMember'));return response()->json(['message'=>'Brand kit deleted.']);
    }

    /** Creates one reusable page master from page-region configuration. */
    public function createPageMaster(Request $request, DocumentStudioV6Service $service): JsonResponse
    {
        $data=$request->validate(['name'=>'required|string|max:120','page_settings'=>'required|array','header_settings'=>'nullable|array','footer_settings'=>'nullable|array','watermark_settings'=>'nullable|array','is_default'=>'nullable|boolean']);
        return response()->json(['data'=>$service->createPageMaster($request->attributes->get('workspaceMember'),$data),'message'=>'Page master created.'],201);
    }

    /** Updates one reusable page master. */
    public function updatePageMaster(Request $request, DocumentPageMaster $pageMaster, DocumentStudioV6Service $service): JsonResponse
    {
        $data=$request->validate(['name'=>'sometimes|string|max:120','page_settings'=>'nullable|array','header_settings'=>'nullable|array','footer_settings'=>'nullable|array','watermark_settings'=>'nullable|array','is_default'=>'nullable|boolean']);
        return response()->json(['data'=>$service->updatePageMaster($pageMaster,$request->attributes->get('workspaceMember'),$data),'message'=>'Page master updated.']);
    }

    /** Deletes one unused page master. */
    public function deletePageMaster(Request $request, DocumentPageMaster $pageMaster, DocumentStudioV6Service $service): JsonResponse
    {
        $service->deletePageMaster($pageMaster,$request->attributes->get('workspaceMember'));return response()->json(['message'=>'Page master deleted.']);
    }

    /** Queues a persistent large batch with bounded background processing. */
    public function queueBatch(Request $request, DocumentTemplate $template, DocumentStudioV6Service $service): JsonResponse
    {
        $this->ensure($request,$template);$data=$request->validate(['source_ids'=>'required|array|min:1|max:500','source_ids.*'=>'integer|min:1','source_type'=>'nullable|string|max:80','client_request_id'=>'nullable|string|min:8|max:80|regex:/^[A-Za-z0-9_.:-]+$/']);
        return response()->json(['data'=>$service->queueBatchGenerate($template,$request->attributes->get('workspaceMember'),$data['source_ids'],$data['source_type']??null,$data['client_request_id']??null),'message'=>'Document batch queued.'],202);
    }

    /** Returns recent persistent document batch jobs for this workspace. */
    public function batchJobs(Request $request, DocumentStudioV6Service $service): JsonResponse
    {
        return response()->json(['data'=>$service->batchJobs($request->attributes->get('workspaceMember'))]);
    }

    /** Enforces workspace isolation before any V6 template operation. */
    private function ensure(Request $request, DocumentTemplate $template): void
    {
        abort_unless((int)$template->workspace_id===(int)$request->attributes->get('workspace')->id,404);
    }
}
