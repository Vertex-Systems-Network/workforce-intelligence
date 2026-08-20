<?php
namespace App\Services\ClientPortal;
use App\Models\ClientReport;
use App\Services\Documents\DocumentContextService;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Support\Facades\Schema;
/** Provides client report pdf service behavior within the WorkIntel application. */ class ClientReportPdfService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly DocumentTemplateService $templates,private readonly DocumentTemplateRenderer $renderer,private readonly DocumentContextService $contexts){}
    /** Builds render output for the current workflow. */ public function render(ClientReport $report): string
    {
        $report->loadMissing(['client.workspace.preferences','project']);$workspace=$report->client->workspace;
        if(Schema::hasTable('document_templates')){$template=$this->templates->defaultTemplate($workspace,'client_report');if($template)return $this->renderer->renderPdf($template,$this->contexts->clientReportModelContext($workspace,$report));}
        $s=$report->snapshot??[];$lines=[$workspace->name,$report->name,'Client: '.($report->client->company_name?:$report->client->name),'Period: '.optional($report->period_start)->format('Y-m-d').' to '.optional($report->period_end)->format('Y-m-d'),'Type: '.ucwords(str_replace('_',' ',$report->report_type)),str_repeat('-',95)];
        if($report->report_type==='project_progress')foreach(($s['projects']??[]) as $p)$lines[]=sprintf('%s | %s | %s%% | %d/%d tasks',$p['name']??'Project',$p['status']??'—',$p['progress_percent']??0,$p['tasks_done']??0,$p['tasks_total']??0);
        elseif($report->report_type==='time_summary'){$lines[]='Tracked Hours: '.($s['tracked_hours']??0);$lines[]='Billable Hours: '.($s['billable_hours']??0);foreach(($s['projects']??[]) as $p)$lines[]=sprintf('%s | tracked %sh | billable %sh',$p['name']??'Project',$p['tracked_hours']??0,$p['billable_hours']??0);}
        else{$currency=$s['currency']??$report->client->currency;$lines[]='Invoiced: '.$currency.' '.number_format((float)($s['invoiced']??0),2);$lines[]='Paid: '.$currency.' '.number_format((float)($s['paid']??0),2);$lines[]='Outstanding: '.$currency.' '.number_format((float)($s['outstanding']??0),2);$lines[]='Invoices: '.($s['invoice_count']??0);foreach(($s['invoices']??[]) as $i)$lines[]=sprintf('%s | %s | total %s %.2f | due %s %.2f',$i['number']??'Invoice',$i['status']??'—',$currency,(float)($i['total']??0),$currency,(float)($i['due']??0));}
        if($report->note)$lines[]='Note: '.preg_replace('/\s+/',' ',$report->note);return $this->legacyPdf($lines);
    }
    /** Handles the legacy pdf operation for the current WorkIntel workflow. */ private function legacyPdf(array $lines):string{$pages=array_chunk(array_values(array_filter($lines,fn($x)=>$x!=='')),48);$objects=[];$pageIds=[];$fontId=3;$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';foreach($pages as $i=>$pageLines){$pageId=4+$i*2;$contentId=$pageId+1;$pageIds[]=$pageId;$stream="BT\n/F1 9 Tf\n36 806 Td\n";foreach($pageLines as $n=>$line){if($n>0)$stream.="0 -15 Td\n";$stream.='('.$this->escape((string)$line).") Tj\n";}$stream.='ET';$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream";}$objects[2]='<< /Type /Pages /Count '.count($pageIds).' /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds)).'] >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];$max=max(array_keys($objects));for($id=1;$id<=$max;$id++){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".($objects[$id]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($id=1;$id<=$max;$id++)$pdf.=sprintf('%010d 00000 n ',$offsets[$id])."\n";$pdf.='trailer << /Size '.($max+1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";return $pdf;}
    /** Handles the escape operation for the current WorkIntel workflow. */ private function escape(string $v):string{$e=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$v);return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$e!==false?$e:$v);}
}
