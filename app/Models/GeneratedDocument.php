<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides generated document behavior within the WorkIntel application. */ class GeneratedDocument extends Model
{
    protected $hidden=['disk','path','sha256','variables_snapshot','render_context_encrypted'];
    protected $fillable=['uuid','workspace_id','document_template_id','document_type','source_type','source_id','language','status','workflow_status','render_driver','render_metadata','render_context_encrypted','disk','path','filename','mime_type','size_bytes','sha256','variables_snapshot','generated_by','generated_at','approved_at','signed_at','locked_at'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['variables_snapshot'=>'array','render_metadata'=>'array','generated_at'=>'datetime','approved_at'=>'datetime','signed_at'=>'datetime','locked_at'=>'datetime'];}
    /** Handles the template operation for the current WorkIntel workflow. */ public function template():BelongsTo{return $this->belongsTo(DocumentTemplate::class,'document_template_id');}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}

    /** Returns revocable public share links for the generated document. */ public function shareLinks():\Illuminate\Database\Eloquent\Relations\HasMany{return $this->hasMany(DocumentShareLink::class,'generated_document_id');}
    /** Returns electronic-signature requests for the generated document. */ public function signatureRequests():\Illuminate\Database\Eloquent\Relations\HasMany{return $this->hasMany(DocumentSignatureRequest::class,'generated_document_id');}
    /** Returns immutable review and approval workflow events. */ public function reviewEvents():\Illuminate\Database\Eloquent\Relations\HasMany{return $this->hasMany(DocumentReviewEvent::class,'generated_document_id');}
    /** Returns collaboration comments attached to this generated document. */ public function comments():\Illuminate\Database\Eloquent\Relations\HasMany{return $this->hasMany(DocumentComment::class,'generated_document_id');}
}
