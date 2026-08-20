<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentStudioV4Service;
use App\Support\LocaleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Handles token-scoped public document viewing and e-signature interactions without workspace login. */
class PublicDocumentController extends Controller
{
    /** Streams a shared generated document while consuming its governed view count. */
    public function share(string $token, DocumentStudioV4Service $service): Response
    {
        $link = $service->consumeShare($token);
        $document = $link->document;
        abort_unless($document && Storage::disk($document->disk)->exists($document->path), 404, 'Shared document file is unavailable.');
        $disposition = $link->access_mode === 'download' ? 'attachment' : 'inline';
        return response()->file(Storage::disk($document->disk)->path($document->path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($document->filename).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Returns safe signing-request metadata and a token-scoped document preview URL. */
    public function signature(string $token, DocumentStudioV4Service $service): JsonResponse
    {
        $request = $service->resolveSignature($token);
        return response()->json(['data' => [
            'request_uuid' => $request->uuid,
            'document_uuid' => $request->document?->uuid,
            'filename' => $request->document?->filename,
            'document_type' => $request->document?->document_type,
            'signer_name' => $request->signer_name,
            'signer_email' => $request->signer_email,
            'role_label' => $request->role_label,
            'status' => $request->status,
            'expires_at' => $request->expires_at?->toIso8601String(),
            'language' => LocaleCatalog::normalize($request->document?->language),
            'direction' => LocaleCatalog::direction($request->document?->language),
            'file_url' => '/api/v1/public/documents/sign/'.$token.'/file',
        ]]);
    }

    /** Streams the document associated with an active signing request. */
    public function signatureFile(string $token, DocumentStudioV4Service $service): Response
    {
        $request = $service->resolveSignature($token);
        $document = $request->document;
        abort_unless($document && Storage::disk($document->disk)->exists($document->path), 404, 'Signature document file is unavailable.');
        return response()->file(Storage::disk($document->disk)->path($document->path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->filename).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Captures typed or drawn signing consent using the hash-only request token. */
    public function sign(Request $request, string $token, DocumentStudioV4Service $service): JsonResponse
    {
        $signatureRequest = $service->resolveSignature($token);
        $data = $request->validate([
            'signature_method' => ['required', Rule::in(['typed', 'drawn'])],
            'typed_name' => 'nullable|string|max:160',
            'signature_data' => 'nullable|string|max:2000000',
            'consent' => 'required|accepted',
        ]);
        $row = $service->sign($signatureRequest, $data, $request->ip());
        return response()->json(['data' => ['status' => $row->status, 'signed_at' => $row->signed_at?->toIso8601String()], 'message' => 'Document signed successfully.']);
    }

    /** Declines an active signature request without exposing workspace data. */
    public function decline(string $token, DocumentStudioV4Service $service): JsonResponse
    {
        $row = $service->decline($service->resolveSignature($token));
        return response()->json(['data' => ['status' => $row->status], 'message' => 'Signature request declined.']);
    }
}
