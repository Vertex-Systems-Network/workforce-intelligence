<?php
namespace App\Services\Payroll;
use App\Models\PayrollExport;use App\Models\PayrollRun;use Illuminate\Support\Facades\Storage;use Illuminate\Support\Str;
/** Provides payroll export service behavior within the WorkIntel application. */ class PayrollExportService{
 /** Creates and persists the requested resource. */ public function create(PayrollRun $run,string $provider,string $format,int $userId):PayrollExport{
  abort_unless(in_array($format,['csv','json'],true),422,'Supported export formats are csv and json.');
  $run->load(['items.member.user','items.complianceLines']);$rows=$run->items->map(function($item){return ['employee_code'=>$item->member->employee_code,'employee'=>trim($item->member->user->first_name.' '.$item->member->user->last_name),'gross'=>(float)$item->gross_pay,'tax'=>(float)$item->tax_total,'statutory_deduction'=>(float)$item->statutory_deduction_total,'employer_contribution'=>(float)$item->employer_contribution_total,'benefit'=>(float)$item->benefit_total,'allowance'=>(float)$item->allowance_total,'net'=>(float)$item->net_pay,'currency'=>$item->currency];});
  $content=$format==='json'?json_encode(['provider'=>$provider,'run'=>$run->only(['uuid','name','period_start','period_end','pay_date','currency','run_type']),'items'=>$rows->values()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES):$this->csv($rows->all());
  $uuid=(string)Str::uuid();$name='payroll-'.$run->uuid.'-'.$provider.'-'.$uuid.'.'.$format;$path='private/payroll-exports/'.$run->workspace_id.'/'.$name;Storage::disk('local')->put($path,$content);$raw=Storage::disk('local')->get($path);
  return PayrollExport::create(['uuid'=>$uuid,'workspace_id'=>$run->workspace_id,'payroll_run_id'=>$run->id,'provider'=>$provider,'format'=>$format,'file_path'=>$path,'file_name'=>$name,'sha256'=>hash('sha256',$raw),'size_bytes'=>strlen($raw),'created_by'=>$userId,'created_at'=>now()]);
 }
 /** Handles the csv operation for the current WorkIntel workflow. */ private function csv(array $rows):string{$h=fopen('php://temp','r+');fputcsv($h,['employee_code','employee','gross','tax','statutory_deduction','employer_contribution','benefit','allowance','net','currency']);foreach($rows as $row)fputcsv($h,$row);rewind($h);$content=stream_get_contents($h);fclose($h);return $content;}
}
