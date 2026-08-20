<?php

namespace App\Services\ClientPortal;

use App\Models\ClientInvoice;
use App\Services\Documents\DocumentContextService;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Support\Facades\Schema;

/** Provides client invoice pdf service behavior within the WorkIntel application. */ class ClientInvoicePdfService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly DocumentContextService $contexts,
    ) {}

    /** Builds render output for the current workflow. */ public function render(ClientInvoice $invoice): string
    {
        $invoice->loadMissing(['client.workspace.preferences','lines.project','payments']);
        $workspace=$invoice->client->workspace;
        if (Schema::hasTable('document_templates')) {
            $template=$this->templates->defaultTemplate($workspace,'invoice');
            if($template){
                $context=$this->contexts->invoiceModelContext($workspace,$invoice);
                return $this->renderer->renderPdf($template,$context);
            }
        }

        $lines=[
            $workspace->name,
            'INVOICE '.$invoice->number,
            'Issue: '.$invoice->issue_date->format('Y-m-d').'   Due: '.$invoice->due_date->format('Y-m-d'),
            'Bill To: '.($invoice->client->company_name ?: $invoice->client->name),
            'Email: '.($invoice->client->billing_email ?: $invoice->client->email ?: '—'),
            $invoice->client->billing_address ? 'Address: '.preg_replace('/\s+/', ' ', $invoice->client->billing_address) : '',
            $invoice->client->tax_id ? 'Tax ID: '.$invoice->client->tax_id : '',
            str_repeat('-',95),
            'Description | Qty | Rate | Amount',
            str_repeat('-',95),
        ];
        foreach($invoice->lines as $line)$lines[]=$this->truncate($line->description,54).' | '.number_format((float)$line->quantity,2).' | '.$invoice->currency.' '.number_format((float)$line->unit_price,2).' | '.$invoice->currency.' '.number_format((float)$line->amount,2);
        $lines=array_merge($lines,[str_repeat('-',95),'Subtotal: '.$invoice->currency.' '.number_format((float)$invoice->subtotal,2),'Discount: '.$invoice->currency.' '.number_format((float)$invoice->discount_total,2),'Tax ('.number_format((float)$invoice->tax_percent,2).'%): '.$invoice->currency.' '.number_format((float)$invoice->tax_total,2),'Total: '.$invoice->currency.' '.number_format((float)$invoice->total,2),'Paid: '.$invoice->currency.' '.number_format((float)$invoice->amount_paid,2),'Amount Due: '.$invoice->currency.' '.number_format((float)$invoice->amount_due,2)]);
        if($invoice->notes)$lines[]='Notes: '.preg_replace('/\s+/', ' ', $invoice->notes); if($invoice->terms)$lines[]='Terms: '.preg_replace('/\s+/', ' ', $invoice->terms);
        return $this->legacyPdf($lines);
    }

    /** Handles the legacy pdf operation for the current WorkIntel workflow. */ private function legacyPdf(array $lines): string
    {
        $pages=array_chunk(array_values(array_filter($lines,fn($x)=>$x!=='')),48);$objects=[];$pageIds=[];$fontId=3;$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        foreach($pages as $i=>$pageLines){$pageId=4+$i*2;$contentId=$pageId+1;$pageIds[]=$pageId;$stream="BT\n/F1 9 Tf\n36 806 Td\n";foreach($pageLines as $n=>$line){if($n>0)$stream.="0 -15 Td\n";$stream.='('.$this->escape((string)$line).") Tj\n";}$stream.='ET';$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream";}
        $objects[2]='<< /Type /Pages /Count '.count($pageIds).' /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds)).'] >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];$max=max(array_keys($objects));for($id=1;$id<=$max;$id++){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".($objects[$id]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($id=1;$id<=$max;$id++)$pdf.=sprintf('%010d 00000 n ',$offsets[$id])."\n";$pdf.='trailer << /Size '.($max+1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";return $pdf;
    }
    /** Handles the escape operation for the current WorkIntel workflow. */ private function escape(string $value): string {$encoded=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$encoded!==false?$encoded:$value);}
    /** Handles the truncate operation for the current WorkIntel workflow. */ private function truncate(string $value,int $length):string{return mb_strlen($value)>$length?mb_substr($value,0,$length-1).'…':$value;}
}
