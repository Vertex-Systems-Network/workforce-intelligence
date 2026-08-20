<?php

namespace App\Services\Documents;

/** Provides document template catalog behavior within the WorkIntel application. */ final class DocumentTemplateCatalog
{
    public const TYPES = [
        'invoice' => 'Client Invoice',
        'client_report' => 'Client Report',
        'payslip' => 'Payslip',
        'billing_invoice' => 'Subscription Invoice',
        'receipt' => 'Receipt',
        'quote' => 'Quote',
        'purchase_order' => 'Purchase Order',
        'timesheet' => 'Timesheet',
        'attendance_report' => 'Attendance Report',
        'employment_contract' => 'Employment Contract',
        'offer_letter' => 'Offer Letter',
        'custom' => 'Custom Document',
    ];

    public const BLOCKS = [
        'page' => 'Page',
        'logo' => 'Brand / Logo',
        'heading' => 'Heading',
        'text' => 'Plain Text',
        'rich_text' => 'Rich Text',
        'field' => 'Dynamic Field',
        'image' => 'Media Image',
        'key_value' => 'Key / Value',
        'table' => 'Repeating Table',
        'totals' => 'Totals',
        'formula' => 'Formula',
        'conditional' => 'Conditional Section',
        'repeat' => 'Repeat Section',
        'columns' => 'Columns',
        'callout' => 'Callout',
        'stamp' => 'Stamp',
        'qr' => 'QR Code',
        'barcode' => 'Barcode',
        'reusable' => 'Reusable Component',
        'divider' => 'Divider',
        'spacer' => 'Spacer',
        'signature' => 'Signature',
        'page_number' => 'Page Number',
        'page_break' => 'Page Break',
        'footer' => 'Flow Footer',
    ];

    /** Handles the variables operation for the current WorkIntel workflow. */ public static function variables(string $type): array
    {
        $common = [
            'workspace.name','workspace.company_name','workspace.legal_name','workspace.currency','workspace.timezone',
            'workspace.support_email','workspace.support_phone','workspace.address','document.generated_at','document.workflow_status','document.approved_at','document.signed_at',
        ];
        $specific = match ($type) {
            'invoice' => ['invoice.number','invoice.issue_date','invoice.due_date','invoice.currency','invoice.subtotal','invoice.discount_total','invoice.tax_percent','invoice.tax_total','invoice.total','invoice.amount_paid','invoice.amount_due','invoice.notes','invoice.terms','client.name','client.company_name','client.email','client.billing_email','client.billing_address','client.tax_id','invoice.lines'],
            'client_report' => ['report.name','report.report_type','report.period_start','report.period_end','report.note','client.name','client.company_name','project.name','report.summary_lines'],
            'payslip' => ['payroll.run_name','payroll.period_start','payroll.period_end','employee.name','employee.email','employee.code','employee.job_title','pay.currency','pay.base_pay','pay.overtime_pay','pay.allowance_total','pay.bonus_total','pay.commission_total','pay.reimbursement_total','pay.gross_pay','pay.deduction_total','pay.tax_total','pay.net_pay'],
            'billing_invoice' => ['billing.number','billing.status','billing.currency','billing.subtotal','billing.tax_total','billing.discount_total','billing.total','billing.amount_paid','billing.amount_due','billing.issued_at','billing.due_at','billing.lines'],
            default => [],
        };
        return array_values(array_unique([...$common, ...$specific]));
    }

    /** Handles the sample operation for the current WorkIntel workflow. */ public static function sample(string $type): array
    {
        $base = [
            'workspace'=>['name'=>'Acme Corporation','company_name'=>'Acme Corporation','legal_name'=>'Acme Corporation LLC','currency'=>'USD','timezone'=>'UTC','support_email'=>'support@example.test','support_phone'=>'+1 555 0100','address'=>'100 Business Avenue, Dubai'],
            'document'=>['generated_at'=>now()->format('Y-m-d H:i')],
        ];
        return match ($type) {
            'invoice' => $base + ['client'=>['name'=>'Alex Morgan','company_name'=>'Northstar Ltd','email'=>'alex@example.test','billing_email'=>'billing@example.test','billing_address'=>'12 Market Road','tax_id'=>'TRN-10001'],'invoice'=>['number'=>'INV-2026-0001','issue_date'=>'2026-08-12','due_date'=>'2026-08-26','currency'=>'USD','subtotal'=>'1,200.00','discount_total'=>'0.00','tax_percent'=>'5.00','tax_total'=>'60.00','total'=>'1,260.00','amount_paid'=>'0.00','amount_due'=>'1,260.00','notes'=>'Thank you for your business.','terms'=>'Payment due within 14 days.','lines'=>[['description'=>'Consulting services','quantity'=>'8.00','unit_price'=>'150.00','amount'=>'1,200.00']]]],
            'client_report' => $base + ['client'=>['name'=>'Alex Morgan','company_name'=>'Northstar Ltd'],'project'=>['name'=>'Growth Project'],'report'=>['name'=>'Monthly Project Summary','report_type'=>'project_progress','period_start'=>'2026-08-01','period_end'=>'2026-08-31','note'=>'Prepared for client review.','summary_lines'=>[['label'=>'Progress','value'=>'68%'],['label'=>'Completed tasks','value'=>'34 / 50']]]],
            'payslip' => $base + ['employee'=>['name'=>'Ahmed Khan','email'=>'ahmed@example.test','code'=>'EMP-001','job_title'=>'Project Manager'],'payroll'=>['run_name'=>'August 2026 Payroll','period_start'=>'2026-08-01','period_end'=>'2026-08-31'],'pay'=>['currency'=>'USD','base_pay'=>'4,500.00','overtime_pay'=>'240.00','allowance_total'=>'300.00','bonus_total'=>'0.00','commission_total'=>'0.00','reimbursement_total'=>'80.00','gross_pay'=>'5,120.00','deduction_total'=>'400.00','tax_total'=>'320.00','net_pay'=>'4,400.00']],
            'billing_invoice' => $base + ['billing'=>['number'=>'BILL-2026-1001','status'=>'open','currency'=>'USD','subtotal'=>'240.00','tax_total'=>'0.00','discount_total'=>'0.00','total'=>'240.00','amount_paid'=>'0.00','amount_due'=>'240.00','issued_at'=>'2026-08-12','due_at'=>'2026-08-19','lines'=>[['description'=>'Gold plan · 20 seats','quantity'=>'20','unit_price'=>'12.00','amount'=>'240.00']]]],
            default => $base + ['custom'=>['title'=>'Custom Document','reference'=>'DOC-001']],
        };
    }

    /** Handles the default schema operation for the current WorkIntel workflow. */ public static function defaultSchema(string $type): array
    {
        return match ($type) {
            'invoice' => [
                ['id'=>'brand','type'=>'logo','label'=>'{{workspace.company_name}}'],
                ['id'=>'title','type'=>'heading','text'=>'INVOICE {{invoice.number}}','level'=>1],
                ['id'=>'dates','type'=>'key_value','items'=>[['label'=>'Issue date','value'=>'{{invoice.issue_date}}'],['label'=>'Due date','value'=>'{{invoice.due_date}}']]],
                ['id'=>'billto','type'=>'text','text'=>'Bill to: {{client.company_name}}\n{{client.billing_email}}\n{{client.billing_address}}'],
                ['id'=>'line_divider','type'=>'divider'],
                ['id'=>'lines','type'=>'table','source'=>'invoice.lines','columns'=>[['label'=>'Description','key'=>'description'],['label'=>'Qty','key'=>'quantity'],['label'=>'Rate','key'=>'unit_price'],['label'=>'Amount','key'=>'amount']]],
                ['id'=>'totals','type'=>'totals','items'=>[['label'=>'Subtotal','value'=>'{{invoice.currency}} {{invoice.subtotal}}'],['label'=>'Tax','value'=>'{{invoice.currency}} {{invoice.tax_total}}'],['label'=>'Total','value'=>'{{invoice.currency}} {{invoice.total}}'],['label'=>'Amount due','value'=>'{{invoice.currency}} {{invoice.amount_due}}']]],
                ['id'=>'notes','type'=>'text','text'=>'Notes: {{invoice.notes}}\nTerms: {{invoice.terms}}'],
                ['id'=>'footer','type'=>'footer','text'=>'{{workspace.support_email}} · {{workspace.support_phone}}'],
            ],
            'payslip' => [
                ['id'=>'brand','type'=>'logo','label'=>'{{workspace.company_name}}'],['id'=>'title','type'=>'heading','text'=>'PAYSLIP','level'=>1],
                ['id'=>'employee','type'=>'key_value','items'=>[['label'=>'Employee','value'=>'{{employee.name}}'],['label'=>'Employee code','value'=>'{{employee.code}}'],['label'=>'Role','value'=>'{{employee.job_title}}'],['label'=>'Period','value'=>'{{payroll.period_start}} – {{payroll.period_end}}']]],
                ['id'=>'earnings','type'=>'key_value','items'=>[['label'=>'Base pay','value'=>'{{pay.currency}} {{pay.base_pay}}'],['label'=>'Overtime','value'=>'{{pay.currency}} {{pay.overtime_pay}}'],['label'=>'Allowances','value'=>'{{pay.currency}} {{pay.allowance_total}}'],['label'=>'Gross pay','value'=>'{{pay.currency}} {{pay.gross_pay}}'],['label'=>'Deductions','value'=>'{{pay.currency}} {{pay.deduction_total}}'],['label'=>'Tax','value'=>'{{pay.currency}} {{pay.tax_total}}'],['label'=>'Net pay','value'=>'{{pay.currency}} {{pay.net_pay}}']]],
                ['id'=>'footer','type'=>'footer','text'=>'Generated {{document.generated_at}}'],
            ],
            'client_report' => [
                ['id'=>'brand','type'=>'logo','label'=>'{{workspace.company_name}}'],['id'=>'title','type'=>'heading','text'=>'{{report.name}}','level'=>1],['id'=>'client','type'=>'text','text'=>'Client: {{client.company_name}}\nPeriod: {{report.period_start}} – {{report.period_end}}'],['id'=>'summary','type'=>'table','source'=>'report.summary_lines','columns'=>[['label'=>'Metric','key'=>'label'],['label'=>'Value','key'=>'value']]],['id'=>'note','type'=>'text','text'=>'{{report.note}}'],['id'=>'footer','type'=>'footer','text'=>'{{workspace.support_email}}'],
            ],
            'billing_invoice' => [
                ['id'=>'brand','type'=>'logo','label'=>'{{workspace.company_name}}'],['id'=>'title','type'=>'heading','text'=>'SUBSCRIPTION INVOICE {{billing.number}}','level'=>1],['id'=>'meta','type'=>'key_value','items'=>[['label'=>'Issued','value'=>'{{billing.issued_at}}'],['label'=>'Due','value'=>'{{billing.due_at}}'],['label'=>'Status','value'=>'{{billing.status}}']]],['id'=>'lines','type'=>'table','source'=>'billing.lines','columns'=>[['label'=>'Description','key'=>'description'],['label'=>'Qty','key'=>'quantity'],['label'=>'Unit price','key'=>'unit_price'],['label'=>'Amount','key'=>'amount']]],['id'=>'totals','type'=>'totals','items'=>[['label'=>'Total','value'=>'{{billing.currency}} {{billing.total}}'],['label'=>'Amount due','value'=>'{{billing.currency}} {{billing.amount_due}}']]],['id'=>'footer','type'=>'footer','text'=>'{{workspace.support_email}}'],
            ],
            default => [
                ['id'=>'brand','type'=>'logo','label'=>'{{workspace.company_name}}'],['id'=>'title','type'=>'heading','text'=>strtoupper(str_replace('_',' ',self::TYPES[$type] ?? 'Document')),'level'=>1],['id'=>'body','type'=>'text','text'=>'Add text, fields, tables and signatures using the Document Studio.'],['id'=>'signature','type'=>'signature','label'=>'Authorized signature'],['id'=>'footer','type'=>'footer','text'=>'Generated {{document.generated_at}}'],
            ],
        };
    }
}
