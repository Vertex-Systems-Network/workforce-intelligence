<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents one internal or external electronic-signature request. */
class DocumentSignatureRequest extends Model
{
    protected $hidden = ['token_hash', 'signature_data', 'request_ip_hash', 'signature_ip_hash'];
    protected $fillable = ['uuid', 'workspace_id', 'generated_document_id', 'signer_member_id', 'signer_name', 'signer_email', 'role_label', 'token_hash', 'status', 'signature_method', 'typed_name', 'signature_data', 'request_ip_hash', 'signature_ip_hash', 'consent_version', 'expires_at', 'signed_at', 'declined_at', 'created_by_member_id'];

    /** Defines signature lifecycle date casts. */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'signed_at' => 'datetime', 'declined_at' => 'datetime'];
    }

    /** Returns the generated document awaiting the signature. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    /** Returns the workspace member when the signer is internal. */
    public function signerMember(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'signer_member_id');
    }
}
