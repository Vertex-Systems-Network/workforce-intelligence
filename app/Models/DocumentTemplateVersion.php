<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides document template version behavior within the WorkIntel application. */ class DocumentTemplateVersion extends Model
{
    public $timestamps=false;
    protected $fillable=['document_template_id','version','content_schema','settings','change_note','created_by','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['content_schema'=>'array','settings'=>'array','created_at'=>'datetime'];}
    /** Handles the template operation for the current WorkIntel workflow. */ public function template():BelongsTo{return $this->belongsTo(DocumentTemplate::class,'document_template_id');}
}
