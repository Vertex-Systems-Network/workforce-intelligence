<?php
namespace App\Services\Installation;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentTemplateRenderer;
/** Provides installation guide pdf service behavior within the WorkIntel application. */ class InstallationGuidePdfService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly DocumentTemplateRenderer $renderer){}
    /** Builds render output for the current workflow. */ public function render(array $guide,string $language='en'):string
    {
        $blocks=[['type'=>'heading','level'=>1,'text'=>$guide['title']],['type'=>'text','text'=>$guide['summary']],['type'=>'heading','level'=>2,'text'=>'Requirements'],['type'=>'text','text'=>implode("\n",array_map(fn($v)=>'• '.$v,$guide['requirements']??[]))]];
        foreach($guide['steps'] as $i=>$step){$blocks[]=['type'=>'heading','level'=>2,'text'=>($i+1).'. '.$step['title']];$blocks[]=['type'=>'text','text'=>$step['text']];if(!empty($step['command']))$blocks[]=['type'=>'text','text'=>'Command: '.$step['command']];}
        $template=new DocumentTemplate(['name'=>$guide['title'],'language'=>$language,'orientation'=>'portrait','primary_color'=>'#111827','secondary_color'=>'#6B7280','content_schema'=>$blocks]);
        return $this->renderer->renderPdf($template,[]);
    }
}
