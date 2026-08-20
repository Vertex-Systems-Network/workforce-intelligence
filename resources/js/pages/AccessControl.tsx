import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Archive, Check, Copy, LockKeyhole, Plus, RefreshCw, RotateCcw, Save, ShieldCheck, Trash2, Users } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useConfirmAction, ErrorState, Alert, Badge, Button, Card, CardBody, Field, Input, Modal, Page, PageHeader, Select, Textarea, Pressable, Checkbox, ChoiceList, ChoiceRow, Radio, Box, Grid, Inline, Stack, Text, Label, Option, FormDialog } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Effect = 'inherit' | 'allow' | 'deny';
type Scope = {
    scope_type: string;
    scope_ids: number[];
};
type Role = {
    id: number;
    name: string;
    description: string | null;
    slug: string;
    is_system: boolean;
    status: 'active' | 'archived';
    template_key: string | null;
    editable: boolean;
    members_count: number;
    permissions: string[];
    denies: string[];
    permission_rules: Record<string, 'allow' | 'deny'>;
    scopes: Record<string, Scope>;
    modules: Record<string, 'allow' | 'deny'>;
};
type Permission = {
    id: number;
    name: string;
    slug: string;
};
type Module = {
    key: string;
    label: string;
};
type ScopeResource = {
    key: string;
    label: string;
};
type ScopeType = {
    key: string;
    label: string;
};
type Member = {
    id: number;
    name: string;
    email: string;
    role_ids: number[];
    primary_role_id: number | null;
};
type Template = {
    key: string;
    name: string;
    description: string;
};
type Payload = {
    roles: Role[];
    permissions: Record<string, Permission[]>;
    modules: Module[];
    scope_resources: ScopeResource[];
    scope_types: ScopeType[];
    dimensions: {
        departments: Array<{
            id: number;
            name: string;
        }>;
        legal_entities: Array<{
            id: number;
            name: string;
        }>;
        business_units: Array<{
            id: number;
            name: string;
            legal_entity_id: number;
        }>;
    };
    templates: Template[];
    members: Member[];
};
type RoleDraft = {
    name: string;
    description: string;
    permission_rules: Record<string, Effect>;
    scopes: Record<string, Scope>;
    modules: Record<string, Effect>;
};
const rolePurpose: Record<string, string> = {
    owner: 'Full workspace ownership. This role is fixed and cannot be restricted.',
    admin: 'Full workspace administration. This role is fixed and cannot be restricted.',
    hr: 'People, HRIS, attendance and workforce operations.',
    manager: 'Team operations, projects and task management.',
    'team-lead': 'Team-scoped work management.',
    'payroll-manager': 'Payroll and finance operations.',
    employee: 'Self-service and assigned work.',
    client: 'Deprecated internal role. Use the Client Portal instead.',
};
const blankDraft: RoleDraft = { name: '', description: '', permission_rules: {}, scopes: {}, modules: {} };
/** Handles the access control operation for the WorkIntel client. */ export default function AccessControl() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [selectedId, setSelectedId] = useState<number | null>(null), [draft, setDraft] = useState<RoleDraft>(blankDraft), [tab, setTab] = useState<'permissions' | 'scopes' | 'modules' | 'members'>('permissions');
    const [loading, setLoading] = useState(true), [saving, setSaving] = useState(false), [error, setError] = useState(''), [message, setMessage] = useState('');
    const [createOpen, setCreateOpen] = useState(false);
    const [cloneOpen, setCloneOpen] = useState(false);
    const [cloneName, setCloneName] = useState(''), [createMode, setCreateMode] = useState<'blank' | 'template' | 'clone'>('blank'), [createName, setCreateName] = useState(''), [createDescription, setCreateDescription] = useState(''), [templateKey, setTemplateKey] = useState(''), [cloneId, setCloneId] = useState('');
    const [memberOpen, setMemberOpen] = useState(false), [memberDraft, setMemberDraft] = useState<Member | null>(null), [memberRoleIds, setMemberRoleIds] = useState<number[]>([]), [primaryRoleId, setPrimaryRoleId] = useState<number | null>(null);
    /** Loads load data required by the current view. */ const load = async (preferredId?: number) => { if (!workspaceId)
        return; setLoading(true); setError(''); try {
        const payload = await apiRequest<Payload>('/api/v1/access-control', { workspaceId });
        setData(payload);
        const id = preferredId ?? selectedId;
        const role = payload.roles.find(x => x.id === id) ?? payload.roles.find(x => x.status === 'active') ?? payload.roles[0];
        setSelectedId(role?.id ?? null);
        if (role)
            setDraft(fromRole(role));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load roles and permissions.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    const selected = useMemo(() => data?.roles.find(x => x.id === selectedId) ?? null, [data, selectedId]);
    const activeRoles = useMemo(() => data?.roles.filter(r => r.status === 'active') ?? [], [data]);
    /** Handles the select role operation for the WorkIntel client. */ const selectRole = (role: Role) => { setSelectedId(role.id); setDraft(fromRole(role)); setMessage(''); };
    /** Updates set permission state for the current workflow. */ const setPermission = (slug: string, effect: Effect) => setDraft(v => ({ ...v, permission_rules: { ...v.permission_rules, [slug]: effect } }));
    /** Updates set module state for the current workflow. */ const setModule = (key: string, effect: Effect) => setDraft(v => ({ ...v, modules: { ...v.modules, [key]: effect } }));
    /** Updates set scope state for the current workflow. */ const setScope = (resource: string, scope_type: string) => setDraft(v => ({ ...v, scopes: { ...v.scopes, [resource]: { scope_type, scope_ids: v.scopes[resource]?.scope_ids ?? [] } } }));
    /** Handles the toggle scope id operation for the WorkIntel client. */ const toggleScopeId = (resource: string, id: number) => setDraft(v => { const current = v.scopes[resource] ?? { scope_type: 'inherit', scope_ids: [] }; const ids = current.scope_ids.includes(id) ? current.scope_ids.filter(x => x !== id) : [...current.scope_ids, id]; return { ...v, scopes: { ...v.scopes, [resource]: { ...current, scope_ids: ids } } }; });
    /** Handles the save operation for the WorkIntel client. */ const save = async () => { if (!selected?.editable || !workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/access-control/roles/${selected.id}`, { method: 'PUT', workspaceId, body: JSON.stringify(draft) });
        setMessage(`${draft.name} updated.`);
        await load(selected.id);
        window.dispatchEvent(new CustomEvent('workintel:permissions-changed'));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save role.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the create role operation for the WorkIntel client. */ const createRole = async () => { if (!workspaceId || !createName.trim())
        return; setSaving(true); setError(''); try {
        const body: any = { name: createName.trim(), description: createDescription || null };
        if (createMode === 'template')
            body.template_key = templateKey;
        if (createMode === 'clone')
            body.clone_role_id = Number(cloneId);
        const r = await apiRequest<{
            data: {
                id: number;
            };
        }>('/api/v1/access-control/roles', { method: 'POST', workspaceId, body: JSON.stringify(body) });
        setCreateOpen(false);
        setCreateName('');
        setCreateDescription('');
        setMessage('Custom role created.');
        await load(r.data.id);
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create role.');
    }
    finally {
        setSaving(false);
    } };
    /** Open the shared clone-role dialog instead of a browser-native prompt. */ const openClone = () => { if (!selected)
        return; setCloneName(`${selected.name} Copy`); setCloneOpen(true); };
    /** Clone the selected role using an explicit audited role name. */ const cloneSelected = async (event: FormEvent) => { event.preventDefault(); if (!selected || !workspaceId || !cloneName.trim())
        return; setSaving(true); try {
        const r = await apiRequest<{
            data: {
                id: number;
            };
        }>(`/api/v1/access-control/roles/${selected.id}/clone`, { method: 'POST', workspaceId, body: JSON.stringify({ name: cloneName.trim() }) });
        setCloneOpen(false);
        await load(r.data.id);
        setMessage('Role cloned.');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not clone role.');
    }
    finally {
        setSaving(false);
    } };
    /** Handles the archive operation for the WorkIntel client. */ const archive = async () => { if (!selected || !workspaceId || !await confirmAction({ title: 'Archive custom role?', description: `Archive ${selected.name}? Members must be reassigned first.`, confirmLabel: 'Archive', danger: true }))
        return; try {
        await apiRequest(`/api/v1/access-control/roles/${selected.id}/archive`, { method: 'POST', workspaceId });
        setMessage('Role archived.');
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not archive role.');
    } };
    /** Handles the restore operation for the WorkIntel client. */ const restore = async () => { if (!selected || !workspaceId)
        return; try {
        await apiRequest(`/api/v1/access-control/roles/${selected.id}/restore`, { method: 'POST', workspaceId });
        await load(selected.id);
        setMessage('Role restored.');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not restore role.');
    } };
    /** Handles the remove operation for the WorkIntel client. */ const remove = async () => { if (!selected || !workspaceId || !await confirmAction({ title: 'Permanently delete role?', description: `Permanently delete archived role ${selected.name}?`, confirmLabel: 'Delete permanently', danger: true }))
        return; try {
        await apiRequest(`/api/v1/access-control/roles/${selected.id}`, { method: 'DELETE', workspaceId });
        await load();
        setMessage('Role deleted.');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not delete role.');
    } };
    /** Handles the open member operation for the WorkIntel client. */ const openMember = (m: Member) => { setMemberDraft(m); setMemberRoleIds([...m.role_ids]); setPrimaryRoleId(m.primary_role_id); setMemberOpen(true); };
    /** Handles the toggle member role operation for the WorkIntel client. */ const toggleMemberRole = (id: number) => setMemberRoleIds(v => v.includes(id) ? v.filter(x => x !== id) : [...v, id]);
    /** Handles the save member roles operation for the WorkIntel client. */ const saveMemberRoles = async () => { if (!memberDraft || !workspaceId)
        return; setSaving(true); setError(''); try {
        await apiRequest(`/api/v1/access-control/members/${memberDraft.id}/roles`, { method: 'PUT', workspaceId, body: JSON.stringify({ role_ids: memberRoleIds, primary_role_id: primaryRoleId }) });
        setMemberOpen(false);
        setMessage(`${memberDraft.name} roles updated.`);
        await load(selectedId ?? undefined);
        window.dispatchEvent(new CustomEvent('workintel:permissions-changed'));
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update member roles.');
    }
    finally {
        setSaving(false);
    } };
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Access control unavailable" text={error || 'Roles and permissions could not be loaded.'} retry={() => load()}/></Page>;
    return <Page>
  <PageHeader title="Roles & Access" description="Custom roles, multiple-role assignments, explicit deny rules and scoped data access" actions={<Inline gap={7}><Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw size={13}/> Refresh</Button><Button size="sm" onClick={() => setCreateOpen(true)}><Plus size={13}/> Custom Role</Button></Inline>}/>
  {error && <Alert tone="danger">{error}</Alert>}{message && <Alert tone="success">{message}</Alert>}
  <Grid columns="280px minmax(0,1fr)" gap={14} mt={14}>
   <Card><CardBody p={8}><Box p="7px 8px 10px" className="ui-card-description">Workspace roles</Box>{data.roles.map(role => <Pressable key={role.id} type="button" onClick={() => selectRole(role)} className={`ui-nav-item${selectedId === role.id ? ' is-active' : ''}`} width="100%" mb={3} opacity={role.status === 'archived' ? .65 : 1}><ShieldCheck size={15}/><Box as="span" flex={1} textAlign="left"><Text display="block" size={12} weight={600}>{role.name}</Text><span className="ui-card-description">{role.members_count} member{role.members_count === 1 ? '' : 's'} · {role.status}</span></Box>{!role.editable && <LockKeyhole size={12}/>}</Pressable>)}</CardBody></Card>
   {selected && <Card><CardBody>
    <Inline justify="space-between" align="flex-start" gap={12} mb={14}><div><Inline gap={8} align="center" wrap="wrap"><Box as="h2" size={17} m={0}>{selected.name}</Box><Badge>{selected.slug}</Badge>{selected.is_system ? <Badge tone="accent">System</Badge> : <Badge>Custom</Badge>}{selected.status === 'archived' && <Badge tone="warning">Archived</Badge>}</Inline><Box className="ui-card-description" mt={5}>{selected.description || rolePurpose[selected.slug] || 'Custom workspace access role.'}</Box></div><Inline gap={6} wrap="wrap" justify="flex-end">{selected.editable && selected.status === 'active' && <><Button size="sm" variant="outline" onClick={openClone}><Copy size={13}/> Clone</Button>{!selected.is_system && <Button size="sm" variant="outline" onClick={() => void archive()}><Archive size={13}/> Archive</Button>}<Button size="sm" loading={saving} onClick={() => void save()}><Save size={13}/> Save</Button></>}{!selected.is_system && selected.status === 'archived' && <><Button size="sm" variant="outline" onClick={() => void restore()}><RotateCcw size={13}/> Restore</Button><Button size="sm" variant="danger" onClick={() => void remove()}><Trash2 size={13}/> Delete</Button></>}</Inline></Inline>
    <Box display="flex" gap={6} borderBottom="1px solid var(--border)" pb={8} mb={14}>{(['permissions', 'scopes', 'modules', 'members'] as const).map(key => <Button key={key} size="sm" variant={tab === key ? 'primary' : 'ghost'} onClick={() => setTab(key)}>{key === 'permissions' ? 'Permissions' : key === 'scopes' ? 'Data Scopes' : key === 'modules' ? 'Modules' : 'Members'}</Button>)}</Box>
    {tab === 'permissions' && <Stack gap={15}>{selected.editable && <Grid columns="1fr 1fr" gap={10}><Field label="Role name"><Input value={draft.name} onChange={e => setDraft(v => ({ ...v, name: e.target.value }))}/></Field><Field label="Description"><Input value={draft.description} onChange={e => setDraft(v => ({ ...v, description: e.target.value }))}/></Field></Grid>}{Object.entries(data.permissions).map(([group, permissions]) => <section key={group}><Box weight={650} size={12} mb={7}>{group}</Box><Grid columns="repeat(auto-fill,minmax(280px,1fr))" gap={6}>{permissions.map(permission => { const effect = selected.editable ? (draft.permission_rules[permission.slug] ?? 'inherit') : 'allow'; return <Box key={permission.slug} display="grid" gridColumns="1fr 95px" gap={8} align="center" p="8px 9px" border="1px solid var(--border)" radius={7}><div><Text as="strong" size={12}>{permission.name}</Text><div className="ui-card-description">{permission.slug}</div></div><Select disabled={!selected.editable} value={effect} onChange={e => setPermission(permission.slug, e.target.value as Effect)}><Option value="inherit">Inherit</Option><Option value="allow">Allow</Option><Option value="deny">Deny</Option></Select></Box>; })}</Grid></section>)}</Stack>}
    {tab === 'scopes' && <div><Alert tone="info">Data scopes constrain records even when a role has a matching permission. Multiple roles combine their visible scopes; explicit permission/module denies still win.</Alert><Stack gap={8} mt={12}>{data.scope_resources.map(resource => { const scope = draft.scopes[resource.key] ?? { scope_type: 'inherit', scope_ids: [] }; const options = scopeOptions(data, scope.scope_type); return <Box key={resource.key} display="grid" gridColumns="180px 220px 1fr" gap={10} align="center" p="9px 0" borderBottom="1px solid var(--border)"><strong>{resource.label}</strong><Select disabled={!selected.editable} value={scope.scope_type} onChange={e => setScope(resource.key, e.target.value)}>{data.scope_types.map(x => <Option key={x.key} value={x.key}>{x.label}</Option>)}</Select><div>{['department', 'legal_entity', 'business_unit'].includes(scope.scope_type) ? <Inline gap={6} wrap="wrap">{dimensionRows(data, scope.scope_type).map(item => <Label key={item.id} display="flex" gap={5} align="center" size={12}><Checkbox checked={scope.scope_ids.includes(item.id)} onChange={() => toggleScopeId(resource.key, item.id)}/>{item.name}</Label>)}{!dimensionRows(data, scope.scope_type).length && <span className="ui-card-description">{options}</span>}</Inline> : <div className="ui-card-description">{options}</div>}</div></Box>; })}</Stack></div>}
    {tab === 'modules' && <div><Alert tone="info">Module Deny is a hard boundary across multiple roles. Allow never grants a permission by itself; the role still needs the underlying permission.</Alert><Grid columns="repeat(auto-fill,minmax(260px,1fr))" gap={7} mt={12}>{data.modules.map(module => <Box key={module.key} display="grid" gridColumns="1fr 105px" gap={8} align="center" p="9px" border="1px solid var(--border)" radius={7}><div><strong>{module.label}</strong><div className="ui-card-description">{module.key}</div></div><Select disabled={!selected.editable} value={draft.modules[module.key] ?? 'inherit'} onChange={e => setModule(module.key, e.target.value as Effect)}><Option value="inherit">Inherit</Option><Option value="allow">Allow</Option><Option value="deny">Deny</Option></Select></Box>)}</Grid></div>}
    {tab === 'members' && <div><Box className="ui-card-description" mb={8}>Members can hold multiple roles. The Primary role controls the role label/home context; effective permissions are combined with Deny taking precedence.</Box><Stack gap={6}>{data.members.map(m => <Pressable type="button" key={m.id} onClick={() => openMember(m)} className="schedule-list-row" width="100%" textAlign="left" cursor="pointer"><div><strong>{m.name}</strong><small>{m.email}</small></div><Inline gap={5} wrap="wrap" justify="flex-end">{m.role_ids.map(id => { const r = data.roles.find(x => x.id === id); return r ? <Badge key={id} tone={id === m.primary_role_id ? 'accent' : 'neutral'}>{r.name}</Badge> : null; })}</Inline></Pressable>)}</Stack></div>}
   </CardBody></Card>}
  </Grid>
  <FormDialog open={cloneOpen} onClose={() => setCloneOpen(false)} title="Clone role" description={selected ? `Create a new custom role from ${selected.name}.` : undefined} formId="clone-role-form" onSubmit={cloneSelected} submitLabel="Clone role" loading={saving} disabled={!cloneName.trim()}><Field label="New role name"><Input value={cloneName} onChange={event => setCloneName(event.target.value)} autoFocus required/></Field></FormDialog>
  <Modal open={createOpen} onClose={() => !saving && setCreateOpen(false)} title="Create custom role" description="Start blank, from a role template, or clone an existing role." footer={<><Button variant="outline" onClick={() => setCreateOpen(false)}>Cancel</Button><Button loading={saving} onClick={() => void createRole()}>Create Role</Button></>}><Stack gap={11}><Field label="Start from"><Select value={createMode} onChange={e => setCreateMode(e.target.value as typeof createMode)}><Option value="blank">Blank role</Option><Option value="template">Role template</Option><Option value="clone">Existing role</Option></Select></Field>{createMode === 'template' && <Field label="Template"><Select value={templateKey} onChange={e => setTemplateKey(e.target.value)}><Option value="">Select template</Option>{data.templates.map(x => <Option key={x.key} value={x.key}>{x.name}</Option>)}</Select></Field>}{createMode === 'clone' && <Field label="Clone"><Select value={cloneId} onChange={e => setCloneId(e.target.value)}><Option value="">Select role</Option>{data.roles.filter(r => r.status === 'active').map(r => <Option key={r.id} value={r.id}>{r.name}</Option>)}</Select></Field>}<Field label="Role name"><Input value={createName} onChange={e => setCreateName(e.target.value)} placeholder="Regional Manager"/></Field><Field label="Description"><Textarea rows={3} value={createDescription} onChange={e => setCreateDescription(e.target.value)}/></Field></Stack></Modal>
  <Modal open={memberOpen} onClose={() => !saving && setMemberOpen(false)} title={memberDraft ? `Roles · ${memberDraft.name}` : 'Member roles'} description="Assign one or more roles. Explicit deny rules across assigned roles take precedence." footer={<><Button variant="outline" onClick={() => setMemberOpen(false)}>Cancel</Button><Button loading={saving} onClick={() => void saveMemberRoles()} disabled={!memberRoleIds.length}>Save Roles</Button></>}><ChoiceList columns={1} maxHeight="lg">{activeRoles.map(role => { const selected = memberRoleIds.includes(role.id); return <ChoiceRow key={role.id} selected={selected}><Checkbox checked={selected} onChange={() => toggleMemberRole(role.id)}/><Box flex={1}><strong>{role.name}</strong><div className="ui-card-description">{role.slug}</div></Box>{selected && <Inline gap={5} align="center"><Radio name="primary-role" checked={primaryRoleId === role.id} onChange={() => setPrimaryRoleId(role.id)}/><span>Primary</span></Inline>}</ChoiceRow>; })}</ChoiceList></Modal>
 </Page>;
}
/** Handles the from role operation for the WorkIntel client. */ function fromRole(role: Role): RoleDraft { return { name: role.name, description: role.description ?? '', permission_rules: { ...role.permission_rules }, scopes: { ...role.scopes }, modules: { ...role.modules } }; }
/** Handles the scope options operation for the WorkIntel client. */ function scopeOptions(data: Payload, type: string) { if (type === 'department')
    return data.dimensions.departments.length ? `${data.dimensions.departments.length} departments available. Empty selection means the member’s department.` : 'No departments configured.'; if (type === 'legal_entity')
    return data.dimensions.legal_entities.length ? `${data.dimensions.legal_entities.length} legal entities available. Empty selection means the member’s entity.` : 'No legal entities configured.'; if (type === 'business_unit')
    return data.dimensions.business_units.length ? `${data.dimensions.business_units.length} business units available. Empty selection means the member’s unit.` : 'No business units configured.'; if (type === 'team')
    return 'Member teams + direct reports.'; if (type === 'workspace')
    return 'All active workspace members.'; if (type === 'own')
    return 'Only the signed-in member.'; return 'Falls back to existing permission scope.'; }
/** Handles the dimension rows operation for the WorkIntel client. */ function dimensionRows(data: Payload, type: string): Array<{
    id: number;
    name: string;
}> { if (type === 'department')
    return data.dimensions.departments; if (type === 'legal_entity')
    return data.dimensions.legal_entities; if (type === 'business_unit')
    return data.dimensions.business_units; return []; }
