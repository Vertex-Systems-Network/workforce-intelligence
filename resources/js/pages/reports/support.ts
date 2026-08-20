import { Activity, Banknote, CalendarClock, Clock3, FolderKanban, Users } from 'lucide-react';
export type Dimension = {
    key: string;
    label: string;
};
export type Metric = {
    key: string;
    label: string;
    format: 'hours' | 'money' | 'number';
};
export type FilterDef = {
    key: string;
    label: string;
    source: string;
    type: 'multi' | 'boolean';
};
export type Dataset = {
    key: string;
    label: string;
    description: string;
    dimensions: Dimension[];
    metrics: Metric[];
    filters: FilterDef[];
    default_dimensions: string[];
    default_metrics: string[];
};
export type Option = {
    id: number;
    name: string;
};
export type Catalog = {
    datasets: Dataset[];
    can_manage: boolean;
    options: {
        members: Option[];
        departments: Option[];
        projects: Option[];
        clients: Option[];
    };
};
export type Visualization = {
    type: 'table' | 'bar' | 'line' | 'area';
    x: string | null;
    y: string | null;
};
export type ReportConfig = {
    dataset: string;
    date_preset: 'custom' | 'last_7_days' | 'last_30_days' | 'this_week' | 'last_week' | 'this_month' | 'last_month';
    date_from: string;
    date_to: string;
    dimensions: string[];
    metrics: string[];
    filters: Record<string, unknown>;
    sort: {
        key: string;
        direction: 'asc' | 'desc';
    };
    limit: number;
    visualization: Visualization;
};
export type Column = {
    key: string;
    label: string;
    type: 'dimension' | 'metric';
    format: string;
};
export type Preview = {
    dataset: string;
    range: {
        from: string;
        to: string;
    };
    configuration: ReportConfig;
    columns: Column[];
    rows: Record<string, any>[];
    row_count: number;
    summary: Record<string, number>;
};
export type SavedReport = {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    dataset: string;
    configuration: ReportConfig;
    is_shared: boolean;
    last_run_at: string | null;
    schedules_count: number;
    creator?: {
        first_name: string;
        last_name: string;
    };
};
export type ReportExport = {
    id: number;
    uuid: string;
    format: 'csv' | 'xlsx' | 'pdf';
    status: string;
    filename: string | null;
    size_bytes: number | null;
    completed_at: string | null;
};
export type ReportRun = {
    id: number;
    uuid: string;
    name: string;
    dataset: string;
    configuration: ReportConfig;
    status: string;
    row_count: number;
    columns: Column[] | null;
    summary: Record<string, number> | null;
    rows: Record<string, any>[] | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
    exports: ReportExport[];
};
export type Schedule = {
    id: number;
    uuid: string;
    name: string;
    frequency: 'daily' | 'weekly' | 'monthly';
    time_of_day: string;
    day_of_week: number | null;
    day_of_month: number | null;
    timezone: string;
    export_format: 'csv' | 'xlsx' | 'pdf';
    active: boolean;
    last_run_at: string | null;
    next_run_at: string | null;
    saved_report: {
        id: number;
        name: string;
        dataset: string;
    };
};
/** Handles the today operation for the WorkIntel client. */ export const today = () => new Date().toISOString().slice(0, 10);
/** Handles the days ago operation for the WorkIntel client. */ export const daysAgo = (days: number) => { const d = new Date(); d.setDate(d.getDate() - days); return d.toISOString().slice(0, 10); };
/** Handles the money operation for the WorkIntel client. */ export const money = (value: unknown, currency = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(value ?? 0));
/** Handles the number operation for the WorkIntel client. */ export const number = (value: unknown) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(Number(value ?? 0));
/** Handles the date time operation for the WorkIntel client. */ export const dateTime = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '—';
export const datasetIcons: Record<string, typeof Clock3> = { time_entries: Clock3, attendance: CalendarClock, payroll: Banknote, activity: Activity, projects: FolderKanban, employees: Users };
/** Handles the default config operation for the WorkIntel client. */ export function defaultConfig(dataset: Dataset): ReportConfig {
    return { dataset: dataset.key, date_preset: 'last_30_days', date_from: daysAgo(29), date_to: today(), dimensions: [...dataset.default_dimensions], metrics: [...dataset.default_metrics], filters: {}, sort: { key: dataset.default_metrics[0], direction: 'desc' }, limit: 5000, visualization: { type: 'table', x: dataset.default_dimensions[0] ?? null, y: dataset.default_metrics[0] ?? null } };
}
/** Formats format cell data for display. */ export function formatCell(value: any, column: Column, currency = 'USD') {
    if (column.format === 'money')
        return money(value, currency);
    if (column.format === 'hours')
        return `${number(value)}h`;
    if (column.format === 'number' && column.type === 'metric')
        return number(value);
    return String(value ?? '—');
}
/** Handles the trigger download operation for the WorkIntel client. */ export function triggerDownload(blob: Blob, filename: string) { const url = URL.createObjectURL(blob); const anchor = document.createElement('a'); anchor.href = url; anchor.download = filename; document.body.appendChild(anchor); anchor.click(); anchor.remove(); URL.revokeObjectURL(url); }
