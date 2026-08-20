<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReport;
use App\Services\ClientPortal\ClientReportService;
use App\Services\ClientPortal\ClientReportPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
/** Provides client report controller behavior within the WorkIntel application. */ class ClientReportController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ClientReportService $service){}
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse { $w=$request->attributes->get('workspace');$q=ClientReport::query()->with(['client:id,name,company_name','project:id,name'])->where('workspace_id',$w->id);if($request->filled('client_id'))$q->where('client_id',$request->integer('client_id'));return response()->json(['data'=>$q->latest()->get()]); }
    /** Creates and persists the requested resource. */ public function store(Request $request): JsonResponse { $w=$request->attributes->get('workspace');$data=$request->validate(['client_id'=>['required','integer'],'project_id'=>['nullable','integer'],'name'=>['required','string','max:180'],'report_type'=>['required','in:project_progress,time_summary,financial_summary'],'period_start'=>['nullable','date'],'period_end'=>['nullable','date'],'note'=>['nullable','string','max:5000'],'publish'=>['nullable','boolean']]);$client=Client::query()->where('workspace_id',$w->id)->whereKey($data['client_id'])->firstOrFail();return response()->json(['data'=>$this->service->generate($client,$data,$request->user()->id)->load(['client:id,name','project:id,name'])],201); }
    /** Handles the pdf operation for the current WorkIntel workflow. */ public function pdf(Request $request, ClientReport $clientReport, ClientReportPdfService $pdf): Response { $this->ensure($request,$clientReport);return response($pdf->render($clientReport),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.Str::slug($clientReport->name).'.pdf"']); }
    /** Handles the publish operation for the current WorkIntel workflow. */ public function publish(Request $request, ClientReport $clientReport): JsonResponse { $this->ensure($request,$clientReport);if($clientReport->project_id)abort_unless($clientReport->project?->client_visible,422,'This project is hidden from the client portal.');$clientReport->update(['published_at'=>now()]);return response()->json(['data'=>$clientReport->fresh(['client:id,name','project:id,name'])]); }
    /** Handles the unpublish operation for the current WorkIntel workflow. */ public function unpublish(Request $request, ClientReport $clientReport): JsonResponse { $this->ensure($request,$clientReport);$clientReport->update(['published_at'=>null]);return response()->json(['data'=>$clientReport->fresh(['client:id,name','project:id,name'])]); }
    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, ClientReport $clientReport): JsonResponse { $this->ensure($request,$clientReport);$clientReport->delete();return response()->json(['message'=>'Client report deleted.']); }
    /** Handles the ensure operation for the current WorkIntel workflow. */ private function ensure(Request $request, ClientReport $report): void { abort_unless($report->workspace_id===$request->attributes->get('workspace')->id,404); }
}
