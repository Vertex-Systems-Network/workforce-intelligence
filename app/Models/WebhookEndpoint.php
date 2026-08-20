<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
/** Provides webhook endpoint behavior within the WorkIntel application. */ class WebhookEndpoint extends Model
{
    protected $fillable=['uuid','workspace_id','created_by','name','url','secret_encrypted','secret_preview','events','status','max_attempts','last_success_at','last_failure_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['events'=>'array','last_success_at'=>'datetime','last_failure_at'=>'datetime']; }
    /** Handles the deliveries operation for the current WorkIntel workflow. */ public function deliveries(): HasMany { return $this->hasMany(WebhookDelivery::class); }
    /** Handles the accepts operation for the current WorkIntel workflow. */ public function accepts(string $event): bool { foreach($this->events??[] as $pattern) if(Str::is($pattern,$event)) return true; return false; }
}
