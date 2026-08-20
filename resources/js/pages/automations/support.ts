export type ConnectorAction = {
    key: string;
    name: string;
    fields: string[];
};
export type Connector = {
    id: string;
    name: string;
    category: string;
    description: string;
    auth: string;
    actions: ConnectorAction[];
};
export type Integration = {
    id: number;
    provider: string;
    name: string;
    status: string;
    last_error: string | null;
};
export type WorkflowRow = {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    trigger_type: string;
    trigger_event: string | null;
    trigger_config: Record<string, unknown> | null;
    conditions: Condition[] | null;
    condition_mode: 'all' | 'any';
    failure_policy: 'stop' | 'continue';
    max_run_seconds: number;
    next_run_at: string | null;
    last_run_at: string | null;
    actions_count: number;
    runs_count: number;
    actions?: ActionForm[];
};
export type RunRow = {
    id: number;
    uuid: string;
    automation_workflow_id: number;
    trigger_event: string | null;
    status: string;
    attempts: number;
    error: string | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
    workflow?: {
        id: number;
        name: string;
    };
    steps?: Array<{
        id: number;
        position: number;
        name: string;
        status: string;
        attempts: number;
        error: string | null;
        input: unknown;
        output: unknown;
    }>;
    dead_letter?: {
        id: number;
    } | null;
};
export type HookRow = {
    id: number;
    uuid: string;
    name: string;
    event_name: string;
    workflow_id: number | null;
    workflow?: {
        id: number;
        name: string;
    } | null;
    token_prefix: string;
    status: string;
    rate_limit_per_minute: number;
    last_used_at: string | null;
    endpoint: string;
};
export type DeadRow = {
    id: number;
    uuid: string;
    automation_run_id: number;
    reason: string;
    retry_count: number;
    resolved_at: string | null;
    created_at: string;
    run?: RunRow;
};
export type Condition = {
    field: string;
    operator: string;
    value: unknown;
};
export type ActionForm = {
    position?: number;
    name: string;
    action_type: 'connector' | 'webhook' | 'notification' | 'task.create';
    action_key: string;
    integration_connection_id: number | null;
    config: Record<string, any>;
    continue_on_error: boolean;
    retry_max: number;
    timeout_seconds: number;
};
export type Template = {
    key: string;
    name: string;
    description: string;
    trigger_event: string;
    conditions: Condition[];
    action: ActionForm;
};
export type WorkflowForm = {
    name: string;
    description: string;
    status: string;
    trigger_type: string;
    trigger_event: string;
    trigger_config: Record<string, any>;
    conditions: Condition[];
    condition_mode: 'all' | 'any';
    failure_policy: 'stop' | 'continue';
    max_run_seconds: number;
    actions: ActionForm[];
};
export type Overview = {
    schema_ready: boolean;
    workflows: WorkflowRow[];
    runs: RunRow[];
    dead_letters: DeadRow[];
    hooks: HookRow[];
    integrations: Integration[];
    connectors: Connector[];
    triggers: string[];
    condition_operators: string[];
    templates: Template[];
    projects: Array<{
        id: number;
        name: string;
        code: string;
    }>;
    people: Array<{
        id: number;
        user: {
            first_name: string;
            last_name: string;
        };
    }>;
    can_manage: boolean;
};
/** Handles the empty action operation for the WorkIntel client. */ export const emptyAction = (): ActionForm => ({ name: 'Action', action_type: 'notification', action_key: 'notify', integration_connection_id: null, config: { role_slugs: ['owner', 'admin'], title: 'Automation notification', body: 'Event {{event.type}} completed.', 'severity': 'info' }, continue_on_error: false, retry_max: 2, timeout_seconds: 12 });
/** Handles the empty form operation for the WorkIntel client. */ export const emptyForm = (): WorkflowForm => ({ name: 'New automation', description: '', status: 'draft', trigger_type: 'event', trigger_event: 'workspace.activity', trigger_config: { frequency: 'daily', at: '09:00' }, conditions: [] as Condition[], condition_mode: 'all' as 'all' | 'any', failure_policy: 'stop' as 'stop' | 'continue', max_run_seconds: 30, actions: [emptyAction()] });
/** Formats fmt data for display. */ export const fmt = (v: string | null | undefined) => v ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(v)) : '—';
/** Handles the tone operation for the WorkIntel client. */ export const tone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' => status === 'succeeded' || status === 'active' ? 'success' : status === 'failed' ? 'danger' : status === 'partial' || status === 'running' || status === 'queued' ? 'warning' : 'neutral';
/** Handles the maybe json operation for the WorkIntel client. */ export const maybeJson = (value: any) => { if (typeof value !== 'string')
    return value; const t = value.trim(); if ((t.startsWith('{') && t.endsWith('}')) || (t.startsWith('[') && t.endsWith(']'))) {
    try {
        return JSON.parse(t);
    }
    catch {
        return value;
    }
} return value; };
/** Handles the normalize config operation for the WorkIntel client. */ export const normalizeConfig = (config: Record<string, any>) => Object.fromEntries(Object.entries(config).map(([k, v]) => [k, maybeJson(v)]));
