export type RunStatus = 'draft' | 'calculated' | 'review' | 'approved' | 'paid';
export type PayType = 'hourly' | 'daily' | 'monthly' | 'yearly' | 'project';
export type Adjustment = {
    id: number;
    category: string;
    direction: 'earning' | 'deduction';
    label: string;
    amount: string | number;
    note?: string | null;
};
export type PayrollProject = {
    id: number;
    amount: string | number;
    project?: {
        id: number;
        name: string;
        code: string | null;
    };
};
export type PayrollItem = {
    id: number;
    member_id: number;
    pay_type: PayType;
    currency: string;
    tracked_seconds: number;
    regular_seconds: number;
    overtime_seconds: number;
    weekend_seconds: number;
    holiday_seconds: number;
    attendance_days: string | number;
    unpaid_leave_days: string | number;
    project_units: number;
    base_pay: string | number;
    overtime_pay: string | number;
    weekend_pay: string | number;
    holiday_pay: string | number;
    unpaid_leave_deduction: string | number;
    bonus_total: string | number;
    commission_total: string | number;
    reimbursement_total: string | number;
    deduction_total: string | number;
    tax_total: string | number;
    adjustment_total: string | number;
    gross_pay: string | number;
    net_pay: string | number;
    status: string;
    rate_snapshot: Record<string, unknown>;
    member: {
        id: number;
        user: {
            first_name: string;
            last_name: string;
            email: string;
        };
        department?: {
            name: string;
        } | null;
    };
    adjustments: Adjustment[];
    projects: PayrollProject[];
};
export type PayrollAction = {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    occurred_at: string;
    user?: {
        first_name: string;
        last_name: string;
    } | null;
};
export type PayrollRun = {
    id: number;
    uuid: string;
    name: string;
    period_start: string;
    period_end: string;
    pay_date: string | null;
    currency: string;
    status: RunStatus;
    note: string | null;
    calculated_at: string | null;
    approved_at: string | null;
    paid_at: string | null;
    locked_at: string | null;
    items?: PayrollItem[];
    actions?: PayrollAction[];
    items_count?: number;
    net_total?: string | number | null;
    gross_total?: string | number | null;
};
export type CompensationProfile = {
    id: number;
    pay_type: PayType;
    currency: string;
    hourly_rate: string | null;
    daily_rate: string | null;
    monthly_salary: string | null;
    annual_salary: string | null;
    project_rate: string | null;
    premium_hourly_rate: string | null;
    standard_hours_per_day: string;
    standard_hours_per_week: string;
    overtime_multiplier: string;
    weekend_multiplier: string;
    holiday_multiplier: string;
    default_tax_percent: string;
    deduct_unpaid_leave: boolean;
    proration_mode: 'calendar_days' | 'none';
    effective_from: string;
    note: string | null;
};
export type CompensationRow = {
    member_id: number;
    name: string;
    email: string;
    job_title: string | null;
    department: string | null;
    profile: CompensationProfile | null;
};
export type CompensationForm = {
    pay_type: PayType;
    currency: string;
    hourly_rate: string;
    daily_rate: string;
    monthly_salary: string;
    annual_salary: string;
    project_rate: string;
    premium_hourly_rate: string;
    standard_hours_per_day: string;
    standard_hours_per_week: string;
    overtime_multiplier: string;
    weekend_multiplier: string;
    holiday_multiplier: string;
    default_tax_percent: string;
    deduct_unpaid_leave: boolean;
    proration_mode: 'calendar_days' | 'none';
    effective_from: string;
    note: string;
};
export const statusTone: Record<RunStatus, 'neutral' | 'info' | 'warning' | 'success' | 'accent'> = { draft: 'neutral', calculated: 'info', review: 'warning', approved: 'success', paid: 'accent' };
export const statusLabel: Record<RunStatus, string> = { draft: 'Draft', calculated: 'Calculated', review: 'Under Review', approved: 'Approved', paid: 'Paid' };
export const payLabel: Record<PayType, string> = { hourly: 'Hourly', daily: 'Daily', monthly: 'Monthly', yearly: 'Yearly', project: 'Project-based' };
/** Handles the empty comp operation for the WorkIntel client. */ export const emptyComp = (currency: string): CompensationForm => ({ pay_type: 'monthly', currency, hourly_rate: '', daily_rate: '', monthly_salary: '', annual_salary: '', project_rate: '', premium_hourly_rate: '', standard_hours_per_day: '8', standard_hours_per_week: '40', overtime_multiplier: '1.5', weekend_multiplier: '1.5', holiday_multiplier: '2', default_tax_percent: '0', deduct_unpaid_leave: true, proration_mode: 'calendar_days', effective_from: new Date().toISOString().slice(0, 10), note: '' });
/** Handles the money operation for the WorkIntel client. */ export function money(value: number | string | null | undefined, currency = 'USD') { return new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(value ?? 0)); }
/** Handles the hours operation for the WorkIntel client. */ export function hours(seconds: number) { return `${(seconds / 3600).toFixed(1)}h`; }
/** Handles the date label operation for the WorkIntel client. */ export function dateLabel(value: string | null | undefined) { if (!value)
    return '—'; const date = new Date(value.includes('T') ? value : `${value}T00:00:00`); return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date); }
