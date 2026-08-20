<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\ClientPortalAccount;
use App\Models\ClientPortalInvite;
use App\Models\ClientPortalToken;
use App\Models\ClientReport;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\ClientPortal\ClientInvoicePdfService;
use App\Services\ClientPortal\ClientReportPdfService;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Provides client portal controller behavior within the WorkIntel application. */ class ClientPortalController extends Controller
{
    /** Handles the activate operation for the current WorkIntel workflow. */ public function activate(Request $request): JsonResponse
    {
        $data=$request->validate(['workspace_slug'=>['required','string','max:180'],'token'=>['required','string','max:200'],'password'=>['required','string','min:8','max:200']]);
        $workspace=Workspace::query()->where('slug',$data['workspace_slug'])->where('status','active')->firstOrFail();
        app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace, 'clients');
        app(EntitlementService::class)->assertFeature($workspace, 'feature.client_portal');
        $invite=ClientPortalInvite::query()->where('workspace_id',$workspace->id)->where('token_hash',hash('sha256',$data['token']))->first();
        if(!$invite||$invite->accepted_at||$invite->expires_at->isPast()) throw ValidationException::withMessages(['token'=>['This activation link is invalid, expired, or already used.']]);

        $existing=ClientPortalAccount::query()->where('workspace_id',$invite->workspace_id)->where('email',strtolower($invite->email))->first();
        if($existing && $existing->client_id!==$invite->client_id) throw ValidationException::withMessages(['token'=>['This email is already assigned to another client in this workspace.']]);

        [$account,$plainToken]=DB::transaction(function()use($invite,$data){
            $account=ClientPortalAccount::query()->updateOrCreate(
                ['workspace_id'=>$invite->workspace_id,'email'=>strtolower($invite->email)],
                ['client_id'=>$invite->client_id,'name'=>$invite->name,'password'=>$data['password'],'status'=>'active','activated_at'=>now()]
            );
            $invite->update(['accepted_at'=>now()]);
            $account->tokens()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            $plain='wicp_'.Str::random(64);
            ClientPortalToken::create(['client_portal_account_id'=>$account->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addDays((int)config('workintel.client_portal.token_days',30)),'created_at'=>now()]);
            return [$account,$plain];
        });
        return response()->json(['token'=>$plainToken,'portal'=>$this->accountPayload($account->load(['workspace','client']))],201);
    }

    /** Handles the login operation for the current WorkIntel workflow. */ public function login(Request $request): JsonResponse
    {
        $data=$request->validate(['workspace_slug'=>['required','string'],'email'=>['required','email'],'password'=>['required','string']]);
        $workspace=Workspace::query()->where('slug',$data['workspace_slug'])->where('status','active')->firstOrFail();
        app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace, 'clients');
        app(EntitlementService::class)->assertFeature($workspace, 'feature.client_portal');
        $account=ClientPortalAccount::query()->with(['workspace','client'])->where('workspace_id',$workspace->id)->where('email',strtolower($data['email']))->first();
        if(!$account||$account->status!=='active'||!Hash::check($data['password'],$account->password)) throw ValidationException::withMessages(['email'=>['The provided portal credentials are incorrect.']]);
        $plain='wicp_'.Str::random(64);
        ClientPortalToken::create(['client_portal_account_id'=>$account->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addDays((int)config('workintel.client_portal.token_days',30)),'created_at'=>now()]);
        $account->update(['last_login_at'=>now()]);
        return response()->json(['token'=>$plain,'portal'=>$this->accountPayload($account)]);
    }

    /** Handles the logout operation for the current WorkIntel workflow. */ public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('clientPortalToken')?->update(['revoked_at'=>now()]);
        return response()->json(['message'=>'Signed out.']);
    }

    /** Handles the me operation for the current WorkIntel workflow. */ public function me(Request $request): JsonResponse
    {
        return response()->json(['portal'=>$this->accountPayload($request->attributes->get('clientPortalAccount'))]);
    }

    /** Handles the dashboard operation for the current WorkIntel workflow. */ public function dashboard(Request $request): JsonResponse
    {
        $client=$request->attributes->get('client');
        $projects=$client->projects()->where('status','!=','archived')->where('client_visible', true)->withCount(['tasks'=>fn($q)=>$q->where('client_visible',true),'tasks as completed_tasks_count'=>fn($q)=>$q->where('client_visible',true)->whereNotNull('completed_at')])->orderByDesc('updated_at')->get();
        $invoices=$client->invoices()->whereIn('status',['sent','partial','paid','overdue'])->latest('issue_date')->get();
        return response()->json([
            'client'=>$client,
            'stats'=>[
                'active_projects'=>$projects->where('status','active')->count(),
                'reports'=>$client->reports()->whereNotNull('published_at')->count(),
                'outstanding'=>(float)$invoices->whereIn('status',['sent','partial','overdue'])->sum('amount_due'),
                'paid'=>(float)$invoices->where('status','paid')->sum('amount_paid'),
                'currency'=>$client->currency,
            ],
            'projects'=>$projects->take(6)->map(fn($p)=>$this->projectPayload($p))->values(),
            'invoices'=>$invoices->take(6)->map(fn($i)=>$this->invoicePayload($i))->values(),
            'reports'=>$client->reports()->whereNotNull('published_at')->latest('published_at')->take(6)->get()->map(fn($r)=>$this->reportPayload($r))->values(),
        ]);
    }

    /** Handles the projects operation for the current WorkIntel workflow. */ public function projects(Request $request): JsonResponse
    {
        $projects=$request->attributes->get('client')->projects()->where('status','!=','archived')->where('client_visible', true)->withCount(['tasks'=>fn($q)=>$q->where('client_visible',true),'tasks as completed_tasks_count'=>fn($q)=>$q->where('client_visible',true)->whereNotNull('completed_at')])->orderByDesc('updated_at')->get();
        return response()->json(['data'=>$projects->map(fn($p)=>$this->projectPayload($p))->values()]);
    }

    /** Handles the show project operation for the current WorkIntel workflow. */ public function showProject(Request $request, Project $project): JsonResponse
    {
        $client=$request->attributes->get('client'); abort_unless($project->client_id===$client->id && $project->client_visible,404);
        $project->load(['tasks'=>fn($q)=>$q->where('client_visible', true)->select(['id','project_id','title','status','priority','due_at','completed_at'])->orderBy('due_at')]);
        $tracked=(float)$project->timeEntries()->where('approval_status','approved')->sum('duration_seconds')/3600;
        $billable=(float)$project->timeEntries()->where('approval_status','approved')->where('billable',true)->sum('duration_seconds')/3600;
        return response()->json(['data'=>[...$this->projectPayload($project),'description'=>$project->description,'tracked_hours'=>round($tracked,2),'billable_hours'=>round($billable,2),'tasks'=>$project->tasks]]);
    }

    /** Handles the invoices operation for the current WorkIntel workflow. */ public function invoices(Request $request): JsonResponse
    {
        $rows=$request->attributes->get('client')->invoices()->whereIn('status',['sent','partial','paid','overdue'])->with('lines.project')->latest('issue_date')->get();
        return response()->json(['data'=>$rows->map(fn($i)=>$this->invoicePayload($i))->values()]);
    }

    /** Handles the show invoice operation for the current WorkIntel workflow. */ public function showInvoice(Request $request, ClientInvoice $clientInvoice): JsonResponse
    {
        abort_unless($clientInvoice->client_id===$request->attributes->get('client')->id,404); abort_if($clientInvoice->status==='draft',404);
        return response()->json(['data'=>$this->invoicePayload($clientInvoice->load(['lines.project','payments']))]);
    }

    /** Handles the invoice pdf operation for the current WorkIntel workflow. */ public function invoicePdf(Request $request, ClientInvoice $clientInvoice, ClientInvoicePdfService $pdf): Response
    {
        abort_unless($clientInvoice->client_id===$request->attributes->get('client')->id,404); abort_if($clientInvoice->status==='draft',404);
        return response($pdf->render($clientInvoice),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$clientInvoice->number.'.pdf"']);
    }

    /** Handles the reports operation for the current WorkIntel workflow. */ public function reports(Request $request): JsonResponse
    {
        $rows=$request->attributes->get('client')->reports()->whereNotNull('published_at')->with('project:id,name')->latest('published_at')->get();
        return response()->json(['data'=>$rows->map(fn($r)=>$this->reportPayload($r))->values()]);
    }

    /** Handles the show report operation for the current WorkIntel workflow. */ public function showReport(Request $request, ClientReport $clientReport): JsonResponse
    {
        abort_unless($clientReport->client_id===$request->attributes->get('client')->id&&$clientReport->published_at,404);
        return response()->json(['data'=>$this->reportPayload($clientReport->load('project:id,name'))]);
    }


    /** Handles the report pdf operation for the current WorkIntel workflow. */ public function reportPdf(Request $request, ClientReport $clientReport, ClientReportPdfService $pdf): Response
    {
        abort_unless($clientReport->client_id===$request->attributes->get('client')->id&&$clientReport->published_at,404);
        return response($pdf->render($clientReport),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.Str::slug($clientReport->name).'.pdf"']);
    }

    /** Handles the account payload operation for the current WorkIntel workflow. */ private function accountPayload(ClientPortalAccount $account): array
    {
        $account->loadMissing(['workspace','client']);
        return ['id'=>$account->id,'name'=>$account->name,'email'=>$account->email,'workspace'=>['id'=>$account->workspace_id,'name'=>$account->workspace->name,'slug'=>$account->workspace->slug],'client'=>['id'=>$account->client_id,'name'=>$account->client->name,'company_name'=>$account->client->company_name,'currency'=>$account->client->currency]];
    }
    /** Handles the project payload operation for the current WorkIntel workflow. */ private function projectPayload(Project $p): array { $total=(int)($p->tasks_count??$p->tasks()->where('client_visible',true)->count());$done=(int)($p->completed_tasks_count??$p->tasks()->where('client_visible',true)->whereNotNull('completed_at')->count());return ['id'=>$p->id,'name'=>$p->name,'code'=>$p->code,'status'=>$p->status,'priority'=>$p->priority,'start_date'=>optional($p->start_date)->toDateString(),'due_date'=>optional($p->due_date)->toDateString(),'tasks_total'=>$total,'tasks_done'=>$done,'progress_percent'=>$total?round(($done/$total)*100,1):0]; }
    /** Handles the invoice payload operation for the current WorkIntel workflow. */ private function invoicePayload(ClientInvoice $i): array { return ['id'=>$i->id,'uuid'=>$i->uuid,'number'=>$i->number,'status'=>$i->status,'currency'=>$i->currency,'issue_date'=>$i->issue_date->toDateString(),'due_date'=>$i->due_date->toDateString(),'subtotal'=>(float)$i->subtotal,'discount_total'=>(float)$i->discount_total,'tax_percent'=>(float)$i->tax_percent,'tax_total'=>(float)$i->tax_total,'total'=>(float)$i->total,'amount_paid'=>(float)$i->amount_paid,'amount_due'=>(float)$i->amount_due,'notes'=>$i->notes,'terms'=>$i->terms,'lines'=>$i->relationLoaded('lines')?$i->lines->map(fn($l)=>['id'=>$l->id,'description'=>$l->description,'quantity'=>(float)$l->quantity,'unit_price'=>(float)$l->unit_price,'amount'=>(float)$l->amount,'project'=>$l->project?->name,'source_type'=>$l->source_type])->values():[],'payments'=>$i->relationLoaded('payments')?$i->payments->map(fn($p)=>['id'=>$p->id,'amount'=>(float)$p->amount,'method'=>$p->method,'reference'=>$p->reference,'paid_on'=>$p->paid_on->toDateString()])->values():[]]; }
    /** Handles the report payload operation for the current WorkIntel workflow. */ private function reportPayload(ClientReport $r): array { return ['id'=>$r->id,'uuid'=>$r->uuid,'name'=>$r->name,'report_type'=>$r->report_type,'project'=>$r->project?->name,'period_start'=>optional($r->period_start)->toDateString(),'period_end'=>optional($r->period_end)->toDateString(),'snapshot'=>$r->snapshot,'note'=>$r->note,'published_at'=>optional($r->published_at)->toIso8601String()]; }
}
