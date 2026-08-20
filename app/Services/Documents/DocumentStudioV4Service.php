<?php

namespace App\Services\Documents;

use App\Models\DocumentComment;
use App\Models\DocumentComponent;
use App\Models\DocumentReviewEvent;
use App\Models\DocumentShareLink;
use App\Models\DocumentSignatureRequest;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\GeneratedDocument;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Automation\AutomationEngine;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Coordinates Document Studio V4 components, review, public sharing, comments and e-signatures. */
class DocumentStudioV4Service
{
    /** Initializes the workflow service with the PDF renderer used for signed-document finalization. */
    public function __construct(private readonly DocumentPdfRenderer $pdfRenderer) {}

    /** Returns reusable components owned by the workspace. */
    public function components(Workspace $workspace): array
    {
        return DocumentComponent::query()->where('workspace_id', $workspace->id)->orderBy('category')->orderBy('name')->get()->all();
    }

    /** Creates a reusable component after validating its nested document schema. */
    public function createComponent(Workspace $workspace, WorkspaceMember $actor, array $data): DocumentComponent
    {
        $schema = app(DocumentTemplateService::class)->validatedSchema($data['content_schema'] ?? []);
        return DocumentComponent::create([
            'workspace_id' => $workspace->id,
            'name' => trim($data['name']),
            'category' => $data['category'] ?? 'content',
            'content_schema' => $schema,
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'version' => 1,
            'created_by' => $actor->user_id,
            'updated_by' => $actor->user_id,
        ]);
    }

    /** Updates one reusable component while preserving workspace isolation. */
    public function updateComponent(DocumentComponent $component, WorkspaceMember $actor, array $data): DocumentComponent
    {
        abort_unless((int) $component->workspace_id === (int) $actor->workspace_id, 404);
        if (array_key_exists('content_schema', $data)) $data['content_schema'] = app(DocumentTemplateService::class)->validatedSchema($data['content_schema']);
        $component->fill(array_intersect_key($data, array_flip(['name', 'category', 'content_schema', 'settings'])));
        $component->updated_by = $actor->user_id;
        $component->version = max(1, (int) $component->version + 1);
        $component->save();
        return $component->fresh();
    }

    /** Deletes a reusable component only when no active template references it. */
    public function deleteComponent(DocumentComponent $component, WorkspaceMember $actor): void
    {
        abort_unless((int) $component->workspace_id === (int) $actor->workspace_id, 404);
        $needle = '"component_id":'.$component->id;
        $used = DocumentTemplate::query()->where('workspace_id', $component->workspace_id)->where('content_schema', 'like', '%'.$needle.'%')->exists();
        abort_if($used, 422, 'This reusable component is still referenced by a document template.');
        $component->delete();
    }

    /** Compares two immutable template versions by block ID and configuration hash. */
    public function compareVersions(DocumentTemplate $template, DocumentTemplateVersion $left, DocumentTemplateVersion $right): array
    {
        abort_unless($left->document_template_id === $template->id && $right->document_template_id === $template->id, 404);
        $leftBlocks = $this->flattenBlocks($left->content_schema ?? []);
        $rightBlocks = $this->flattenBlocks($right->content_schema ?? []);
        $added = array_values(array_diff(array_keys($rightBlocks), array_keys($leftBlocks)));
        $removed = array_values(array_diff(array_keys($leftBlocks), array_keys($rightBlocks)));
        $changed = [];
        foreach (array_intersect(array_keys($leftBlocks), array_keys($rightBlocks)) as $id) {
            if (hash('sha256', json_encode($leftBlocks[$id]) ?: '') !== hash('sha256', json_encode($rightBlocks[$id]) ?: '')) $changed[] = $id;
        }
        return [
            'left' => ['version' => $left->version, 'change_note' => $left->change_note, 'created_at' => $left->created_at],
            'right' => ['version' => $right->version, 'change_note' => $right->change_note, 'created_at' => $right->created_at],
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /** Creates a revocable hash-only public share link and returns the one-time raw URL token. */
    public function createShare(GeneratedDocument $document, WorkspaceMember $actor, array $data): array
    {
        $this->assertDocumentWorkspace($document, $actor);
        $token = Str::random(64);
        $link = DocumentShareLink::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $document->workspace_id,
            'generated_document_id' => $document->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => $data['access_mode'] ?? 'view',
            'max_views' => $data['max_views'] ?? null,
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
            'created_by_member_id' => $actor->id,
        ]);
        $this->event($document, $actor, 'shared', null, ['share_uuid' => $link->uuid, 'access_mode' => $link->access_mode]);
        $this->emit($document, 'documents.shared', ['share_uuid' => $link->uuid]);
        return ['link' => $link, 'token' => $token, 'url' => '/api/v1/public/documents/share/'.$token];
    }

    /** Revokes one public share link without deleting its audit record. */
    public function revokeShare(DocumentShareLink $link, WorkspaceMember $actor): DocumentShareLink
    {
        abort_unless((int) $link->workspace_id === (int) $actor->workspace_id, 404);
        if (! $link->revoked_at) $link->update(['revoked_at' => now()]);
        $this->event($link->document, $actor, 'share_revoked', null, ['share_uuid' => $link->uuid]);
        return $link->fresh();
    }

    /** Resolves and consumes a public share token while enforcing expiry and view limits. */
    public function consumeShare(string $token): DocumentShareLink
    {
        return DB::transaction(function () use ($token) {
            $link = DocumentShareLink::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_if($link->revoked_at, 410, 'This document link has been revoked.');
            abort_if($link->expires_at && $link->expires_at->isPast(), 410, 'This document link has expired.');
            abort_if($link->max_views !== null && $link->view_count >= $link->max_views, 410, 'This document link has reached its view limit.');
            $link->increment('view_count');
            $link->forceFill(['last_viewed_at' => now()])->save();
            return $link->fresh(['document']);
        });
    }

    /** Creates one internal or external signature request and returns the one-time raw signing token. */
    public function createSignatureRequest(GeneratedDocument $document, WorkspaceMember $actor, array $data, ?string $requestIp = null): array
    {
        $this->assertDocumentWorkspace($document, $actor);
        abort_if($document->locked_at, 422, 'Locked documents cannot receive new signature requests.');
        $policy=$this->workflowPolicy($document);
        if($policy['review_required'])abort_unless(DocumentReviewEvent::query()->where('generated_document_id',$document->id)->where('event','review_requested')->exists(),422,'Complete the required review before requesting signatures.');
        if($policy['approval_required'])abort_unless((bool)$document->approved_at,422,'Approve this document before requesting signatures.');
        if(trim((string)($data['role_label']??''))===''&&$policy['signer_role']!=='')$data['role_label']=$policy['signer_role'];
        $memberId = isset($data['signer_member_id']) ? (int) $data['signer_member_id'] : null;
        if ($memberId) {
            $member = WorkspaceMember::with('user')->where('workspace_id', $document->workspace_id)->where('status', 'active')->findOrFail($memberId);
            $data['signer_name'] = trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? ''));
            $data['signer_email'] = $member->user?->email;
        }
        $token = Str::random(72);
        $request = DocumentSignatureRequest::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $document->workspace_id,
            'generated_document_id' => $document->id,
            'signer_member_id' => $memberId,
            'signer_name' => trim((string) ($data['signer_name'] ?? '')),
            'signer_email' => $data['signer_email'] ?? null,
            'role_label' => $data['role_label'] ?? null,
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'request_ip_hash' => $requestIp ? $this->ipHash($requestIp) : null,
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : now()->addDays(14),
            'created_by_member_id' => $actor->id,
        ]);
        $this->event($document, $actor, 'signature_requested', null, ['signature_uuid' => $request->uuid, 'signer_name' => $request->signer_name, 'role_label' => $request->role_label]);
        return ['request' => $request, 'token' => $token, 'url' => '/document-sign/'.$token];
    }

    /** Resolves a pending signing token without mutating its status. */
    public function resolveSignature(string $token): DocumentSignatureRequest
    {
        $request = DocumentSignatureRequest::with(['document.template'])->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if(in_array($request->status, ['declined', 'expired'], true), 410, 'This signature request is no longer active.');
        if ($request->expires_at && $request->expires_at->isPast() && $request->status === 'pending') {
            $request->update(['status' => 'expired']);
            abort(410, 'This signature request has expired.');
        }
        return $request;
    }

    /** Captures typed or drawn signature consent, locks final documents and regenerates a signed PDF snapshot. */
    public function sign(DocumentSignatureRequest $request, array $data, ?string $ip = null): DocumentSignatureRequest
    {
        abort_unless($request->status === 'pending', 422, 'This signature request has already been completed.');
        abort_if($request->expires_at && $request->expires_at->isPast(), 410, 'This signature request has expired.');
        $method = $data['signature_method'] ?? 'typed';
        $typed = trim((string) ($data['typed_name'] ?? ''));
        $signatureData = $data['signature_data'] ?? null;
        if ($method === 'typed' && $typed === '') throw ValidationException::withMessages(['typed_name' => ['Type your full legal name to sign.']]);
        if ($method === 'drawn') {
            if (! is_string($signatureData) || ! preg_match('#^data:image/(png|jpeg|webp);base64,#i', $signatureData) || strlen($signatureData) > 2_000_000) {
                throw ValidationException::withMessages(['signature_data' => ['Provide a valid PNG, JPEG or WebP signature image under 2 MB.']]);
            }
        } else {
            $signatureData = null;
        }
        abort_unless((bool) ($data['consent'] ?? false), 422, 'Signing consent is required.');

        DB::transaction(function () use ($request, $method, $typed, $signatureData, $ip) {
            $request->update([
                'status' => 'signed',
                'signature_method' => $method,
                'typed_name' => $typed !== '' ? $typed : $request->signer_name,
                'signature_data' => $signatureData,
                'signature_ip_hash' => $ip ? $this->ipHash($ip) : null,
                'signed_at' => now(),
            ]);
            $document = $request->document()->lockForUpdate()->firstOrFail();
            $pending = DocumentSignatureRequest::where('generated_document_id', $document->id)->where('status', 'pending')->exists();
            if (! $pending) { $policy=$this->workflowPolicy($document);$reviewDone=!$policy['review_required']||DocumentReviewEvent::query()->where('generated_document_id',$document->id)->where('event','review_requested')->exists();$approvalDone=!$policy['approval_required']||(bool)$document->approved_at;$document->update(['workflow_status' => 'signed', 'signed_at' => now(), 'locked_at' => $reviewDone&&$approvalDone ? now() : null]); }
            $this->event($document, null, 'signed', null, ['signature_uuid' => $request->uuid, 'signer_name' => $request->signer_name, 'role_label' => $request->role_label]);
        });

        if (! DocumentSignatureRequest::where('generated_document_id', $request->generated_document_id)->where('status', 'pending')->exists()) {
            $this->regenerateFinal($request->document);
            $this->emit($request->document, 'documents.signed', ['signature_request_id' => $request->id]);
        }
        return $request->fresh();
    }

    /** Declines a pending signature request while keeping a permanent workflow event. */
    public function decline(DocumentSignatureRequest $request): DocumentSignatureRequest
    {
        abort_unless($request->status === 'pending', 422, 'This signature request has already been completed.');
        $request->update(['status' => 'declined', 'declined_at' => now()]);
        $this->event($request->document, null, 'signature_declined', null, ['signature_uuid' => $request->uuid, 'signer_name' => $request->signer_name]);
        return $request->fresh();
    }

    /** Moves a generated document into review and records an immutable event. */
    public function requestReview(GeneratedDocument $document, WorkspaceMember $actor, ?string $note = null): GeneratedDocument
    {
        $this->assertDocumentWorkspace($document, $actor);
        abort_if($document->locked_at, 422, 'Locked documents cannot re-enter review.');
        $document->update(['workflow_status' => 'in_review']);
        $this->event($document, $actor, 'review_requested', $note);
        $this->emit($document, 'documents.review_requested');
        return $document->fresh();
    }

    /** Approves a reviewed document and records the actor and approval timestamp. */
    public function approve(GeneratedDocument $document, WorkspaceMember $actor, ?string $note = null): GeneratedDocument
    {
        $this->assertDocumentWorkspace($document, $actor);
        abort_if($document->locked_at, 422, 'Locked documents cannot be changed.');
        $policy=$this->workflowPolicy($document);
        if($policy['review_required'])abort_unless($document->workflow_status==='in_review',422,'This document must complete review before approval.');
        else abort_unless(in_array($document->workflow_status, ['generated', 'in_review', 'rejected'], true), 422, 'This document is not in an approvable state.');
        $document->update(['workflow_status' => 'approved', 'approved_at' => now()]);
        $this->event($document, $actor, 'approved', $note);
        $this->emit($document, 'documents.approved');
        return $document->fresh();
    }

    /** Rejects an unlocked document review with an explanatory note. */
    public function reject(GeneratedDocument $document, WorkspaceMember $actor, string $note): GeneratedDocument
    {
        $this->assertDocumentWorkspace($document, $actor);
        abort_if($document->locked_at, 422, 'Locked documents cannot be changed.');
        $document->update(['workflow_status' => 'rejected', 'approved_at' => null]);
        $this->event($document, $actor, 'rejected', $note);
        return $document->fresh();
    }

    /** Locks an approved or signed generated document against workflow mutation. */
    public function lock(GeneratedDocument $document, WorkspaceMember $actor): GeneratedDocument
    {
        $this->assertDocumentWorkspace($document, $actor);
        $policy=$this->workflowPolicy($document);
        if($policy['approval_required'])abort_unless((bool)$document->approved_at,422,'Approval is required before final lock.');
        if($policy['signature_required'])abort_unless((bool)$document->signed_at&&$document->workflow_status==='signed',422,'All required signatures must be completed before final lock.');
        if($policy['review_required'])abort_unless(DocumentReviewEvent::query()->where('generated_document_id',$document->id)->where('event','review_requested')->exists(),422,'Review is required before final lock.');
        abort_unless(in_array($document->workflow_status, ['approved', 'signed'], true), 422, 'Approve or sign the document before locking it.');
        if (! $document->locked_at) $document->update(['locked_at' => now()]);
        $this->event($document, $actor, 'locked');
        return $document->fresh();
    }

    /** Creates a block-scoped or document-scoped collaboration comment. */
    public function comment(WorkspaceMember $actor, array $data): DocumentComment
    {
        $templateId = $data['document_template_id'] ?? null;
        $documentId = $data['generated_document_id'] ?? null;
        abort_if(($templateId ? 1 : 0) + ($documentId ? 1 : 0) !== 1, 422, 'Choose exactly one document template or generated document.');
        if ($templateId) abort_unless(DocumentTemplate::where('workspace_id', $actor->workspace_id)->whereKey($templateId)->exists(), 404);
        if ($documentId) abort_unless(GeneratedDocument::where('workspace_id', $actor->workspace_id)->whereKey($documentId)->exists(), 404);
        return DocumentComment::create([
            'workspace_id' => $actor->workspace_id,
            'document_template_id' => $templateId,
            'generated_document_id' => $documentId,
            'block_id' => $data['block_id'] ?? null,
            'author_member_id' => $actor->id,
            'body' => trim($data['body']),
        ])->load('author.user:id,first_name,last_name');
    }

    /** Resolves or reopens a collaboration comment for authorized workspace members. */
    public function resolveComment(DocumentComment $comment, WorkspaceMember $actor, bool $resolved): DocumentComment
    {
        abort_unless((int) $comment->workspace_id === (int) $actor->workspace_id, 404);
        $comment->update(['resolved_at' => $resolved ? now() : null, 'resolved_by_member_id' => $resolved ? $actor->id : null]);
        return $comment->fresh(['author.user:id,first_name,last_name']);
    }

    /** Regenerates a finalized PDF from encrypted render context and captured signatures. */
    public function regenerateFinal(GeneratedDocument $document): void
    {
        $template = $document->template;
        if (! $template || ! $document->render_context_encrypted) return;
        try {
            $decoded = json_decode(Crypt::decryptString($document->render_context_encrypted), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) return;
            $decoded['document'] = array_merge(is_array($decoded['document'] ?? null) ? $decoded['document'] : [], [
                'workflow_status' => $document->workflow_status,
                'approved_at' => $document->approved_at?->toIso8601String(),
                'signed_at' => $document->signed_at?->toIso8601String(),
            ]);
            $decoded['signatures'] = DocumentSignatureRequest::where('generated_document_id', $document->id)->where('status', 'signed')->orderBy('id')->get()->map(fn (DocumentSignatureRequest $request) => [
                'signer_name' => $request->signer_name,
                'role_label' => $request->role_label,
                'typed_name' => $request->typed_name,
                'signature_data' => $request->signature_data,
                'signed_at' => $request->signed_at?->toIso8601String(),
            ])->all();
            $render = $this->pdfRenderer->render($template, $decoded);
            Storage::disk($document->disk)->put($document->path, $render['bytes']);
            $document->update([
                'size_bytes' => strlen($render['bytes']),
                'sha256' => hash('sha256', $render['bytes']),
                'render_driver' => $render['driver'],
                'render_metadata' => array_merge($document->render_metadata ?? [], ['unicode_capable' => (bool) $render['unicode_capable'], 'finalized' => true]),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** Flattens nested block trees into an ID-indexed comparison map. */
    private function flattenBlocks(array $blocks): array
    {
        $map = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $id = (string) ($block['id'] ?? '');
            if ($id !== '') $map[$id] = $block;
            foreach (['children'] as $key) if (is_array($block[$key] ?? null)) $map += $this->flattenBlocks($block[$key]);
            if (($block['type'] ?? '') === 'columns') foreach (is_array($block['columns'] ?? null) ? $block['columns'] : [] as $column) if (is_array($column) && is_array($column['children'] ?? null)) $map += $this->flattenBlocks($column['children']);
        }
        return $map;
    }

    /** Returns the immutable workflow policy snapshot captured when the generated document was created. */
    private function workflowPolicy(GeneratedDocument $document): array
    {
        $metadata=is_array($document->render_metadata)?$document->render_metadata:[];$snapshot=is_array($metadata['workflow_policy']??null)?$metadata['workflow_policy']:[];
        if(!$snapshot&&$document->template){$candidate=data_get($document->template->settings,'workflow');$snapshot=is_array($candidate)?$candidate:[];}
        return ['review_required'=>(bool)($snapshot['review_required']??false),'approval_required'=>(bool)($snapshot['approval_required']??false),'signature_required'=>(bool)($snapshot['signature_required']??false),'signer_role'=>trim((string)($snapshot['signer_role']??''))];
    }

    /** Verifies generated-document workspace isolation. */
    private function assertDocumentWorkspace(GeneratedDocument $document, WorkspaceMember $actor): void
    {
        abort_unless((int) $document->workspace_id === (int) $actor->workspace_id, 404);
    }

    /** Records one immutable generated-document workflow event. */
    private function event(GeneratedDocument $document, ?WorkspaceMember $actor, string $event, ?string $note = null, array $metadata = []): void
    {
        DocumentReviewEvent::create([
            'workspace_id' => $document->workspace_id,
            'generated_document_id' => $document->id,
            'actor_member_id' => $actor?->id,
            'event' => $event,
            'note' => $note,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /** Emits a Document Studio automation event without breaking the core workflow if connectors fail. */
    private function emit(GeneratedDocument $document, string $event, array $extra = []): void
    {
        try {
            app(AutomationEngine::class)->emit($document->workspace, $event, array_merge([
                'document_id' => $document->id,
                'document_uuid' => $document->uuid,
                'document_type' => $document->document_type,
                'workflow_status' => $document->workflow_status,
            ], $extra), 'documents', $event.':'.$document->uuid.':'.Str::uuid());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** Derives an irreversible IP audit hash using the application key without storing raw addresses. */
    private function ipHash(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
