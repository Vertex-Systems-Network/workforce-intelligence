export type Provider = {
    id: number;
    uuid: string;
    name: string;
    type: 'oidc' | 'saml';
    status: string;
    domains: string[];
    enforce_login: boolean;
    jit_provisioning: boolean;
    default_role_slug: string;
    runtime_ready: boolean;
};
export type SecurityPolicy = {
    require_mfa: boolean;
    mfa_role_slugs: string[];
    session_ttl_minutes: number;
    max_active_sessions: number;
    allow_password_login: boolean;
    require_sso: boolean;
    password_min_length: number;
    block_compromised_devices: boolean;
};
export type IpRule = {
    id: number;
    name: string;
    cidr: string;
    action: string;
    priority: number;
    active: boolean;
};
export type AccessPolicy = {
    id: number;
    name: string;
    resource: string;
    action: string;
    effect: string;
    priority: number;
    conditions: Record<string, unknown>;
    active: boolean;
};
export type ScimToken = {
    id: number;
    name: string;
    token_prefix: string;
    scopes: string[];
    last_used_at: string | null;
    expires_at: string | null;
    revoked_at: string | null;
};
export type Session = {
    id: number;
    uuid: string;
    user_id: number;
    ip_address: string | null;
    user_agent: string | null;
    last_seen_at: string;
    expires_at: string | null;
    revoked_at: string | null;
    user?: {
        first_name: string;
        last_name: string;
        email: string;
    };
};
export type MobileSession = {
    id: number;
    uuid: string;
    member_id: number;
    token_prefix: string;
    platform: string;
    device_name: string | null;
    app_version: string | null;
    last_used_at: string | null;
    last_used_ip: string | null;
    expires_at: string | null;
    revoked_at: string | null;
};
export type LegalEntity = {
    id: number;
    code: string;
    name: string;
    country_code: string | null;
    currency: string;
    timezone: string;
    status: string;
};
export type BusinessUnit = {
    id: number;
    legal_entity_id: number | null;
    code: string;
    name: string;
    leader_member_id: number | null;
    status: string;
};
export type Governance = {
    id: number;
    dataset: string;
    retention_days: number | null;
    residency_region: string | null;
    storage_class: string;
    deletion_mode: string;
    legal_hold: boolean;
};
export type OrgMember = {
    id: number;
    employee_code: string | null;
    legal_entity_id: number | null;
    business_unit_id: number | null;
    user: {
        first_name: string;
        last_name: string;
        email: string;
    };
};
export type OrgProject = {
    id: number;
    name: string;
    code: string | null;
    legal_entity_id: number | null;
    business_unit_id: number | null;
};
export type OrgCostCenter = {
    id: number;
    name: string;
    code: string;
    legal_entity_id: number | null;
    business_unit_id: number | null;
};
export type Payload = {
    providers: Provider[];
    security_policy: SecurityPolicy;
    ip_rules: IpRule[];
    access_policies: AccessPolicy[];
    scim_tokens: ScimToken[];
    sessions: Session[];
    mobile_sessions: MobileSession[];
    legal_entities: LegalEntity[];
    business_units: BusinessUnit[];
    governance: Governance[];
    organization_members: OrgMember[];
    organization_projects: OrgProject[];
    organization_cost_centers: OrgCostCenter[];
    mfa: {
        enabled: boolean;
    };
};
export type Tab = 'identity' | 'security' | 'directory' | 'organization' | 'governance';
/** Handles the tone operation for the WorkIntel client. */ export const tone = (status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'accent' => ['active', 'enabled'].includes(status) ? 'success' : ['revoked', 'inactive', 'deny'].includes(status) ? 'danger' : ['allow'].includes(status) ? 'info' : 'neutral';
