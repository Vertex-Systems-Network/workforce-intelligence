<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentComment;
use App\Models\DocumentComponent;
use App\Models\DocumentShareLink;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentStudioV4Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

/** Exposes Document Studio V4 reusable components, workflow, sharing and collaboration APIs. */
class DocumentStudioV4Controller extends Controller
{

    /** Issue a short-lived signed bearer URL for downloading an authorized generated document. */
    public function temporaryDownloadUrl(Request $request, GeneratedDocument $document, DocumentAccessService $access): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$document->workspace_id===(int)$workspace->id,404);abort_unless($access->canViewGenerated($actor,$document),403);
        $expiresAt=now()->addMinutes(max(1,min(30,(int)config('workintel_security.signed_urls.document_minutes',5))));
        return response()->json(['url'=>URL::temporarySignedRoute('documents.generated.signed-download',$expiresAt,['document'=>$document->id]),'expires_at'=>$expiresAt->toIso8601String()]);
    }

    /** Download one generated document only when Laravel validates its expiring URL signature. */
    public function signedDownload(GeneratedDocument $document): Response
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path),404,'Generated file is missing from storage.');
        return response()->file(Storage::disk($document->disk)->path($document->path),['Content-Type'=>$document->mime_type,'Content-Disposition'=>'attachment; filename="'.addslashes($document->filename).'"','Cache-Control'=>'private, no-store']);
    }

    /** Lists reusable components for the current workspace. */
    public function components(Request $request, DocumentStudioV4Service $service): JsonResponse
    {
        return response()->json(['data' => $service->components($request->attributes->get('workspace'))]);
    }

    /** Creates one reusable document component. */
    public function storeComponent(Request $request, DocumentStudioV4Service $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'category' => 'nullable|string|max:60',
            'content_schema' => 'required|array|max:120',
            'settings' => 'nullable|array',
        ]);
        $component = $service->createComponent($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $data);
        return response()->json(['data' => $component, 'message' => 'Reusable component created.'], 201);
    }

    /** Updates one workspace-scoped reusable component. */
    public function updateComponent(Request $request, DocumentComponent $component, DocumentStudioV4Service $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'category' => 'sometimes|string|max:60',
            'content_schema' => 'sometimes|array|max:120',
            'settings' => 'nullable|array',
        ]);
        return response()->json(['data' => $service->updateComponent($component, $request->attributes->get('workspaceMember'), $data), 'message' => 'Reusable component updated.']);
    }

    /** Deletes an unused reusable component. */
    public function destroyComponent(Request $request, DocumentComponent $component, DocumentStudioV4Service $service): JsonResponse
    {
        $service->deleteComponent($component, $request->attributes->get('workspaceMember'));
        return response()->json(['message' => 'Reusable component deleted.']);
    }

    /** Compares two immutable template versions and returns block-level changes. */
    public function compareVersions(Request $request, DocumentTemplate $template, DocumentTemplateVersion $left, DocumentTemplateVersion $right, DocumentStudioV4Service $service): JsonResponse
    {
        $this->ensureTemplate($request, $template);
        return response()->json(['data' => $service->compareVersions($template, $left, $right)]);
    }

    /** Returns generated-document workflow, signature, share, review and comment details. */
    public function generated(Request $request, GeneratedDocument $document, DocumentAccessService $access): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');
        abort_unless((int) $document->workspace_id === (int) $workspace->id, 404);
        abort_unless($access->canViewGenerated($actor, $document), 403);
        return response()->json(['data' => $document->load([
            'template:id,name',
            'shareLinks' => fn ($query) => $query->latest('id'),
            'signatureRequests' => fn ($query) => $query->latest('id'),
            'reviewEvents' => fn ($query) => $query->latest('id')->limit(100),
            'comments.author.user:id,first_name,last_name',
        ])]);
    }

    /** Creates an expiring revocable public document link. */
    public function share(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        $data = $request->validate([
            'access_mode' => ['required', Rule::in(['view', 'download'])],
            'expires_in_days' => 'nullable|integer|min:1|max:365',
            'max_views' => 'nullable|integer|min:1|max:100000',
        ]);
        $result = $service->createShare($document, $request->attributes->get('workspaceMember'), $data);
        return response()->json(['data' => ['link' => $result['link'], 'url' => $result['url']], 'message' => 'Secure document link created.'], 201);
    }

    /** Revokes a previously created document share link. */
    public function revokeShare(Request $request, DocumentShareLink $link, DocumentStudioV4Service $service): JsonResponse
    {
        return response()->json(['data' => $service->revokeShare($link, $request->attributes->get('workspaceMember')), 'message' => 'Document link revoked.']);
    }

    /** Creates an internal or external signing request. */
    public function signatureRequest(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        $data = $request->validate([
            'signer_member_id' => 'nullable|integer|min:1',
            'signer_name' => 'required_without:signer_member_id|nullable|string|max:160',
            'signer_email' => 'nullable|email|max:255',
            'role_label' => 'nullable|string|max:120',
            'expires_in_days' => 'nullable|integer|min:1|max:90',
        ]);
        $result = $service->createSignatureRequest($document, $request->attributes->get('workspaceMember'), $data, $request->ip());
        return response()->json(['data' => ['request' => $result['request'], 'url' => $result['url']], 'message' => 'Signature request created.'], 201);
    }

    /** Moves a generated document into review. */
    public function requestReview(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        $note = $request->validate(['note' => 'nullable|string|max:5000'])['note'] ?? null;
        return response()->json(['data' => $service->requestReview($document, $request->attributes->get('workspaceMember'), $note), 'message' => 'Review requested.']);
    }

    /** Approves a generated document. */
    public function approve(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        $note = $request->validate(['note' => 'nullable|string|max:5000'])['note'] ?? null;
        return response()->json(['data' => $service->approve($document, $request->attributes->get('workspaceMember'), $note), 'message' => 'Document approved.']);
    }

    /** Rejects a generated document with a required explanation. */
    public function reject(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        $note = $request->validate(['note' => 'required|string|max:5000'])['note'];
        return response()->json(['data' => $service->reject($document, $request->attributes->get('workspaceMember'), $note), 'message' => 'Document rejected.']);
    }

    /** Locks an approved or fully signed document. */
    public function lock(Request $request, GeneratedDocument $document, DocumentStudioV4Service $service): JsonResponse
    {
        return response()->json(['data' => $service->lock($document, $request->attributes->get('workspaceMember')), 'message' => 'Document locked.']);
    }

    /** Lists comments for one template. */
    public function templateComments(Request $request, DocumentTemplate $template): JsonResponse
    {
        $this->ensureTemplate($request, $template);
        return response()->json(['data' => DocumentComment::with('author.user:id,first_name,last_name')->where('document_template_id', $template->id)->orderBy('resolved_at')->latest('id')->get()]);
    }

    /** Creates a collaboration comment on a template block or generated document. */
    public function comment(Request $request, DocumentStudioV4Service $service): JsonResponse
    {
        $data = $request->validate([
            'document_template_id' => 'nullable|integer|min:1',
            'generated_document_id' => 'nullable|integer|min:1',
            'block_id' => 'nullable|string|max:100',
            'body' => 'required|string|max:10000',
        ]);
        return response()->json(['data' => $service->comment($request->attributes->get('workspaceMember'), $data), 'message' => 'Comment added.'], 201);
    }

    /** Resolves or reopens a document collaboration comment. */
    public function resolveComment(Request $request, DocumentComment $comment, DocumentStudioV4Service $service): JsonResponse
    {
        $resolved = (bool) $request->validate(['resolved' => 'required|boolean'])['resolved'];
        return response()->json(['data' => $service->resolveComment($comment, $request->attributes->get('workspaceMember'), $resolved), 'message' => $resolved ? 'Comment resolved.' : 'Comment reopened.']);
    }

    /** Enforces workspace-scoped template route model binding. */
    private function ensureTemplate(Request $request, DocumentTemplate $template): void
    {
        abort_unless((int) $template->workspace_id === (int) $request->attributes->get('workspace')->id, 404);
    }
}
