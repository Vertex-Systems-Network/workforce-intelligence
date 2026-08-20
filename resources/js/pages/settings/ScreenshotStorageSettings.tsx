import { useEffect, useState, type FormEvent } from 'react';
import { Cloud, HardDrive, RefreshCw, ShieldCheck, Trash2 } from 'lucide-react';
import { apiRequest } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { useConfirmAction, Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Select, Switch, Grid, Inline, Stack, Text, Form, Option, Box, EmptyState, LoadingState} from '../../design-system';
type Provider = {
    id: number;
    name: string;
    provider_type: string;
    enabled: boolean;
    is_primary: boolean;
    fallback_to_local: boolean;
    delete_local_after_sync: boolean;
    root_path: string | null;
    health_status: string;
    consecutive_failures: number;
    last_tested_at: string | null;
    last_success_at: string | null;
    last_error: string | null;
    public_config: Record<string, unknown>;
    configured_secret_fields: string[];
};
type Payload = {
    providers: Provider[];
    provider_types: Array<{
        key: string;
        label: string;
    }>;
    jobs: Record<string, number>;
    external_storage_entitled: boolean;
};
type Draft = {
    name: string;
    provider_type: string;
    root_path: string;
    fallback_to_local: boolean;
    delete_local_after_sync: boolean;
    config: Record<string, string | boolean | number>;
};
const blank: Draft = { name: '', provider_type: 's3', root_path: 'workintel-screenshots', fallback_to_local: true, delete_local_after_sync: false, config: { region: 'us-east-1' } };
const publicFields: Record<string, Array<[
    string,
    string,
    string?
]>> = {
    ftp: [['host', 'Host'], ['port', 'Port', 'number'], ['username', 'Username'], ['password', 'Password', 'password']],
    sftp: [['host', 'Host'], ['port', 'Port', 'number'], ['username', 'Username'], ['password', 'Password', 'password'], ['private_key_path', 'Private key path'], ['passphrase', 'Passphrase', 'password']],
    s3: [['access_key', 'Access key'], ['secret_key', 'Secret key', 'password'], ['region', 'Region'], ['bucket', 'Bucket'], ['endpoint', 'Endpoint (optional)'], ['session_token', 'Session token', 'password']],
    google_drive: [['access_token', 'Access token', 'password'], ['folder_id', 'Folder ID'], ['refresh_token', 'Refresh token', 'password'], ['client_id', 'OAuth client ID'], ['client_secret', 'OAuth client secret', 'password']],
    onedrive: [['access_token', 'Access token', 'password'], ['drive_id', 'Drive ID (optional)'], ['tenant_id', 'Tenant ID'], ['refresh_token', 'Refresh token', 'password'], ['client_id', 'OAuth client ID'], ['client_secret', 'OAuth client secret', 'password']],
    azure_blob: [['account_url', 'Account URL'], ['container', 'Container'], ['sas_token', 'SAS token', 'password']],
};
/** Handles the status tone operation for the WorkIntel client. */ function statusTone(status: string): 'success' | 'danger' | 'warning' | 'neutral' { return status === 'healthy' ? 'success' : status === 'unhealthy' ? 'danger' : status === 'degraded' ? 'warning' : 'neutral'; }
/** Handles the screenshot storage settings operation for the WorkIntel client. */ export default function ScreenshotStorageSettings() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId ?? 0;
    const [data, setData] = useState<Payload | null>(null), [draft, setDraft] = useState<Draft>(blank), [editing, setEditing] = useState<Provider | null>(null), [busy, setBusy] = useState(false), [message, setMessage] = useState('');
    /** Loads load data required by the current view. */ const load = async () => { if (!workspaceId)
        return; try {
        setData(await apiRequest<Payload>('/api/v1/screenshots/storage/providers', { workspaceId, silent: true }));
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Could not load screenshot storage.');
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Updates set config state for the current workflow. */ const setConfig = (key: string, value: string | number | boolean) => setDraft(d => ({ ...d, config: { ...d.config, [key]: value } }));
    /** Handles the begin edit operation for the WorkIntel client. */ const beginEdit = (p: Provider) => { setEditing(p); setDraft({ name: p.name, provider_type: p.provider_type, root_path: p.root_path ?? '', fallback_to_local: p.fallback_to_local, delete_local_after_sync: p.delete_local_after_sync, config: { ...p.public_config } as Record<string, string | boolean | number> }); };
    /** Handles the reset operation for the WorkIntel client. */ const reset = () => { setEditing(null); setDraft(blank); };
    /** Handles the save operation for the WorkIntel client. */ const save = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setMessage(''); try {
        if (editing)
            await apiRequest(`/api/v1/screenshots/storage/providers/${editing.id}`, { method: 'PUT', workspaceId, body: JSON.stringify({ name: draft.name, root_path: draft.root_path || null, fallback_to_local: draft.fallback_to_local, delete_local_after_sync: draft.delete_local_after_sync, config: draft.config }) });
        else
            await apiRequest('/api/v1/screenshots/storage/providers', { method: 'POST', workspaceId, body: JSON.stringify(draft) });
        setMessage(editing ? 'Storage provider updated.' : 'Storage provider created.');
        reset();
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Could not save provider.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the action operation for the WorkIntel client. */ const action = async (p: Provider, kind: 'test' | 'activate' | 'queue-existing' | 'delete') => { setBusy(true); setMessage(''); try {
        if (kind === 'delete') {
            if (!await confirmAction({ title: 'Remove screenshot storage provider?', description: `Remove ${p.name}?`, confirmLabel: 'Remove', danger: true }))
                return;
            await apiRequest(`/api/v1/screenshots/storage/providers/${p.id}`, { method: 'DELETE', workspaceId });
        }
        else
            await apiRequest(`/api/v1/screenshots/storage/providers/${p.id}/${kind}`, { method: 'POST', workspaceId, body: kind === 'queue-existing' ? JSON.stringify({ limit: 1000 }) : undefined });
        setMessage(kind === 'test' ? 'Connection test completed.' : kind === 'activate' ? 'Primary storage updated.' : kind === 'queue-existing' ? 'Existing screenshots queued for migration.' : 'Provider removed.');
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Storage action failed.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the retry operation for the WorkIntel client. */ const retry = async () => { setBusy(true); try {
        await apiRequest('/api/v1/screenshots/storage/retry-failed', { method: 'POST', workspaceId });
        setMessage('Failed transfers queued for retry.');
        await load();
    }
    catch (e) {
        setMessage(e instanceof Error ? e.message : 'Retry failed.');
    }
    finally {
        setBusy(false);
    } };
    if (!data)
        return <LoadingState title="Loading screenshot storage providers…" text="Checking local spool health and configured external providers."/>;
    return <Stack gap={14}><div><Box as="h2" m={0} size={17}>Screenshot Storage</Box><Text className="ui-card-description" as="p" mt={4}>Screenshots always land in a private local safety spool first. External copies are checksum-verified before local cleanup can occur.</Text></div>{message && <Alert tone={message.includes('failed') || message.includes('Could not') ? 'danger' : 'info'}>{message}</Alert>}{!data.external_storage_entitled && <Alert tone="warning">External screenshot storage is available on Gold and Platinum. Local private storage remains active.</Alert>}
 <Card><CardHeader title="Storage health" description="Queued transfers retry automatically with backoff. A failed remote provider never silently discards the local safety copy." action={(data.jobs.failed ?? 0) > 0 ? <Button variant="outline" size="sm" loading={busy} onClick={() => void retry()}><RefreshCw size={13}/> Retry failed</Button> : undefined}/><CardBody><Inline gap={12} wrap="wrap">{Object.entries(data.jobs).map(([k, v]) => <Box key={k} p="8px 12px" border="1px solid var(--border)" radius={8}><div className="ui-card-description">{k}</div><strong>{v}</strong></Box>)}{Object.keys(data.jobs).length === 0 && <EmptyState title="No pending storage transfers" text="The local safety spool has no queued, failed or active transfer jobs."/>}</Inline></CardBody></Card>
 <Grid columns="repeat(auto-fill,minmax(280px,1fr))" gap={10}>{data.providers.map(p => <Card key={p.id}><CardHeader title={p.name} description={data.provider_types.find(t => t.key === p.provider_type)?.label ?? p.provider_type} action={<Inline gap={5}>{p.is_primary && <Badge tone="success">Primary</Badge>}<Badge tone={statusTone(p.health_status)}>{p.health_status}</Badge></Inline>}/><CardBody><div className="ui-card-description">{p.last_error ? `Last error: ${p.last_error}` : p.last_success_at ? `Last success: ${new Date(p.last_success_at).toLocaleString()}` : 'Not tested yet'}</div><Inline gap={6} wrap="wrap" mt={12}><Button size="sm" variant="outline" loading={busy} onClick={() => void action(p, 'test')}><ShieldCheck size={13}/> Test</Button>{!p.is_primary && <Button size="sm" variant="outline" disabled={!p.enabled} onClick={() => void action(p, 'activate')}>Set Primary</Button>}{p.provider_type !== 'local' && <><Button size="sm" variant="ghost" onClick={() => beginEdit(p)}>Edit</Button><Button size="sm" variant="ghost" onClick={() => void action(p, 'queue-existing')}>Migrate existing</Button><Button size="sm" variant="ghost" onClick={() => void action(p, 'delete')}><Trash2 size={13}/></Button></>}</Inline></CardBody></Card>)}</Grid>
 {data.external_storage_entitled && <Card><CardHeader title={editing ? 'Edit storage provider' : 'Add external storage provider'} description="Credentials are encrypted at rest and never returned by the API after saving."/><CardBody><Form onSubmit={save} gap={12}><Grid columns="1fr 1fr" gap={10}><Field label="Name"><Input value={draft.name} onChange={e => setDraft({ ...draft, name: e.target.value })} required/></Field><Field label="Provider"><Select value={draft.provider_type} disabled={Boolean(editing)} onChange={e => setDraft({ ...blank, name: draft.name, provider_type: e.target.value })}>{data.provider_types.filter(x => x.key !== 'local').map(x => <Option key={x.key} value={x.key}>{x.label}</Option>)}</Select></Field><Field label="Root path"><Input value={draft.root_path} onChange={e => setDraft({ ...draft, root_path: e.target.value })} placeholder="workintel-screenshots"/></Field>{(publicFields[draft.provider_type] ?? []).map(([key, label, type]) => <Field key={key} label={label} hint={type === 'password' && editing && editing.configured_secret_fields.includes(key) ? 'Already configured — leave blank to keep existing value.' : undefined}><Input type={type === 'password' ? 'password' : type === 'number' ? 'number' : 'text'} value={String(draft.config[key] ?? '')} onChange={e => setConfig(key, type === 'number' ? Number(e.target.value) : e.target.value)} placeholder={type === 'password' && editing ? '••••••••' : ''}/></Field>)}</Grid><Stack gap={8}><Inline justify="space-between" align="center"><span><Text as="strong" size={12}>Fallback to local</Text><Text className="ui-card-description" display="block">If the remote provider cannot be read, serve the verified local safety copy when available.</Text></span><Switch checked={draft.fallback_to_local} onChange={v => setDraft({ ...draft, fallback_to_local: v })}/></Inline><Inline justify="space-between" align="center"><span><Text as="strong" size={12}>Delete local after verified sync</Text><Text className="ui-card-description" display="block">Only removes the local binary after remote upload + download checksum verification succeeds.</Text></span><Switch checked={draft.delete_local_after_sync} onChange={v => setDraft({ ...draft, delete_local_after_sync: v })}/></Inline></Stack><Inline justify="flex-end" gap={8}>{editing && <Button type="button" variant="ghost" onClick={reset}>Cancel</Button>}<Button type="submit" loading={busy}>{editing ? 'Save Provider' : 'Add Provider'}</Button></Inline></Form></CardBody></Card>}
 <Alert tone="info"><Cloud size={14}/> AWS S3-compatible mode also supports Cloudflare R2, DigitalOcean Spaces and MinIO by setting the endpoint. Google Drive and OneDrive accept a current access token or OAuth refresh credentials. Azure Blob uses a container SAS.</Alert></Stack>;
}
