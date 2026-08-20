<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
/** Provides document template behavior within the WorkIntel application. */ class DocumentTemplate extends Model
{
    protected $fillable=['uuid','workspace_id','legal_entity_id','name','slug','document_type','language','status','is_default','paper_size','orientation','primary_color','secondary_color','font_family','content_schema','settings','current_version','created_by','updated_by'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['is_default'=>'boolean','content_schema'=>'array','settings'=>'array','current_version'=>'integer'];}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}
    /** Handles the legal entity operation for the current WorkIntel workflow. */ public function legalEntity():BelongsTo{return $this->belongsTo(LegalEntity::class);}
    /** Handles the versions operation for the current WorkIntel workflow. */ public function versions():HasMany{return $this->hasMany(DocumentTemplateVersion::class);}
    /** Handles the generated documents operation for the current WorkIntel workflow. */ public function generatedDocuments():HasMany{return $this->hasMany(GeneratedDocument::class);}

    /** Returns collaboration comments attached to this template or one of its blocks. */ public function comments():HasMany{return $this->hasMany(DocumentComment::class,'document_template_id');}
    /** Returns the latest mutable V6 autosave without changing immutable template history. */ public function draft():HasOne{return $this->hasOne(DocumentTemplateDraft::class,'document_template_id');}
}
