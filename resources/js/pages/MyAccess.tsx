import { useEffect, useState } from 'react';
import { CheckCircle2, Copy, ImagePlus, KeyRound, Save, ShieldCheck, Trash2, UserRound, XCircle } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { roleLabel } from '../access';
import { Alert, Badge, Button, Card, CardBody, CardHeader, Field, Input, Modal, Page, PageHeader, Image, Box, Grid, Inline, Stack } from '../design-system';
import { MediaPicker } from '../media/MediaPicker';
import type { MediaAsset } from '../media/types';
const rolePurpose: Record<string, string> = {
    owner: 'Full workspace ownership and administration.', admin: 'Full workspace administration.', hr: 'People operations and workspace attendance/leave administration.', manager: 'Manage team operations and workspace work without billing/access-control ownership.', 'team-lead': 'Manage assigned/team work only.', 'payroll-manager': 'Payroll and company time/pay records.', employee: 'Self-service access to your assigned work and your own records.', client: 'Legacy internal role; client access should use the Client Portal.',
};
const descriptions: Record<string, string> = {
    people: 'People & employee directory', organization: 'Departments, teams & job titles', projects: 'Projects', tasks: 'Tasks', time: 'Timesheets & timers', attendance: 'Attendance, shifts & leave', activity: 'Activity tracking', screenshots: 'Screenshots', payroll: 'Payroll', reports: 'Reports', clients: 'Clients', devices: 'Devices & agents', settings: 'Workspace settings', billing: 'Billing', notifications: 'Notifications', integrations: 'Integrations', api: 'Public API keys', security: 'Security & audit logs', hris: 'HRIS', performance: 'Performance & growth', expenses: 'Expenses', procurement: 'Procurement', job_costing: 'Job costing', field: 'Field workforce', enterprise: 'Enterprise governance',
};
type MfaStatus = {
    enabled: boolean;
    recovery_codes_remaining: number;
};
type Enrollment = {
    method_id: number;
    secret: string;
    otpauth_uri: string;
    recovery_codes: string[];
};
/** Handles the my access operation for the WorkIntel client. */ export default function MyAccess() {
    const { session, refreshSession, changePassword } = useAuth();
    const workspace = session?.user.workspaces.find(item => item.id === session.user.activeWorkspaceId);
    const [mfa, setMfa] = useState<MfaStatus | null>(null), [enrollment, setEnrollment] = useState<Enrollment | null>(null), [code, setCode] = useState(''), [error, setError] = useState(''), [busy, setBusy] = useState(false);
    const [profile, setProfile] = useState({ first_name: session?.user.firstName ?? '', last_name: session?.user.lastName ?? '', email: session?.user.email ?? '', phone: session?.user.phone ?? '', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, locale: session?.user.locale ?? 'en' });
    const [mediaPickerOpen, setMediaPickerOpen] = useState(false);
    const [passwordForm, setPasswordForm] = useState({ currentPassword: '', password: '', confirmation: '' });
    /** Loads load mfa data required by the current view. */ const loadMfa = async () => { try {
        setMfa(await apiRequest<MfaStatus>('/api/v1/mfa/status', { silent: true }));
    }
    catch { /* Phase 23 may not be migrated yet during rolling deploy. */ } };
    useEffect(() => { void loadMfa(); }, []);
    if (!workspace)
        return null;
    const isFull = workspace.role === 'owner' || workspace.role === 'admin' || workspace.permissions.includes('*');
    const groups = new Map<string, string[]>();
    for (const permission of workspace.permissions.filter(item => item !== '*')) {
        const [group] = permission.split('.');
        const rows = groups.get(group) ?? [];
        rows.push(permission);
        groups.set(group, rows);
    }
    /** Handles the begin operation for the WorkIntel client. */ const begin = async () => { setBusy(true); setError(''); try {
        const r = await apiRequest<{
            data: Enrollment;
        }>('/api/v1/mfa/begin', { method: 'POST' });
        setEnrollment(r.data);
        setCode('');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not begin MFA enrollment.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the confirm operation for the WorkIntel client. */ const confirmMfa = async () => { setBusy(true); setError(''); try {
        await apiRequest('/api/v1/mfa/confirm', { method: 'POST', body: JSON.stringify({ code }) });
        setEnrollment(null);
        setCode('');
        await loadMfa();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Authenticator code was not accepted.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the disable operation for the WorkIntel client. */ const disable = async () => { if (!code) {
        setError('Enter a current authenticator or recovery code first.');
        return;
    } setBusy(true); setError(''); try {
        await apiRequest('/api/v1/mfa/disable', { method: 'POST', body: JSON.stringify({ code }) });
        setCode('');
        await loadMfa();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not disable MFA.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the save profile operation for the WorkIntel client. */ const saveProfile = async () => { setBusy(true); setError(''); try {
        await apiRequest('/api/v1/auth/profile', { method: 'PUT', body: JSON.stringify({ ...profile, phone: profile.phone || null }) });
        await refreshSession();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not save profile.');
    }
    finally {
        setBusy(false);
    } };
    /** Selects an existing image from Media Library as the user's profile photo. */ const selectAvatar = async (asset: MediaAsset) => { setBusy(true); setError(''); try {
        await apiRequest('/api/v1/media/avatar', { method: 'POST', workspaceId: workspace.id, body: JSON.stringify({ media_asset_id: asset.id }) });
        await refreshSession();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not update profile photo.');
    }
    finally {
        setBusy(false);
    } };
    /** Removes the current media-backed profile photo. */ const removeAvatar = async () => { setBusy(true); setError(''); try {
        await apiRequest('/api/v1/media/avatar', { method: 'DELETE', workspaceId: workspace.id });
        await refreshSession();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not remove profile photo.');
    }
    finally {
        setBusy(false);
    } };
    /** Handles the save password operation for the WorkIntel client. */ const savePassword = async () => { setBusy(true); setError(''); try {
        await changePassword({ currentPassword: passwordForm.currentPassword, password: passwordForm.password, passwordConfirmation: passwordForm.confirmation });
        setPasswordForm({ currentPassword: '', password: '', confirmation: '' });
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not change password.');
    }
    finally {
        setBusy(false);
    } };
    return <Page><PageHeader title="My Access" description="Your workspace role, permissions and personal sign-in security"/>
  {error && <Alert tone="danger" mb={12}>{error}</Alert>}
  <Grid columns="minmax(240px,.75fr) minmax(0,1.25fr)" gap={14}>
   <Card><CardBody><Inline gap={12} align="center"><Box as="span" width={44} height={44} radius={10} display="grid" placeItems="center" bg="var(--accent-dim)" color="var(--accent)"><UserRound size={21}/></Box><div><Box size={16} weight={700}>{session?.user.firstName} {session?.user.lastName}</Box><div className="ui-card-description">{session?.user.email}</div></div></Inline><Stack mt={18} gap={10}><div><div className="ui-card-description">Workspace role</div><Box mt={4}><Badge tone="accent"><ShieldCheck size={12}/> {roleLabel(workspace.role)}</Badge></Box></div><div><div className="ui-card-description">Plan</div><Box weight={650} mt={3}>{workspace.plan}</Box></div><div><div className="ui-card-description">Access model</div><Box size={12} lineHeight={1.6} mt={3} color="var(--text-2)">{isFull ? 'Full workspace administration. Owner/Admin can access all modules.' : rolePurpose[workspace.role] ?? 'Your sidebar only shows modules granted by your role permissions.'}</Box></div></Stack></CardBody></Card>
   <Card><CardHeader title="Granted permissions" description={isFull ? 'Full access is granted by your administrative role.' : 'Permissions are grouped by product area.'}/><CardBody>{isFull ? <Box display="flex" gap={10} align="center" p={12} border="1px solid var(--success)" radius={8} bg="var(--success-dim)"><CheckCircle2 size={18} color="var(--success)"/><div><Box weight={650}>All workspace permissions</Box><div className="ui-card-description">No module-level restrictions for this role.</div></div></Box> : groups.size ? <Grid columns="repeat(auto-fill,minmax(220px,1fr))" gap={9}>{[...groups.entries()].map(([group, permissions]) => <Box key={group} p={11} border="1px solid var(--border)" radius={8}><Box display="flex" align="center" gap={7} weight={650} size={12}><KeyRound size={13} color="var(--accent)"/>{descriptions[group] ?? group}</Box><Stack gap={4} mt={8}>{permissions.map(permission => <Box as="span" key={permission} display="flex" align="center" gap={6} size={11} color="var(--text-2)"><CheckCircle2 size={11} color="var(--success)"/>{permission}</Box>)}</Stack></Box>)}</Grid> : <Box display="flex" gap={9} align="center" color="var(--text-2)"><XCircle size={16}/> No internal permissions are assigned to this role.</Box>}</CardBody></Card>
  </Grid>
  <Card mt={14}><CardHeader title="My profile" description="Personal identity, profile photo and account preferences"/><CardBody><div className="profile-photo-editor"><div className="profile-photo-editor__avatar">{session?.user.avatarUrl ? <Image src={session.user.avatarUrl} alt="Profile"/> : <span>{(session?.user.firstName?.[0] ?? 'U')}{session?.user.lastName?.[0] ?? ''}</span>}</div><div><strong>Profile photo</strong><p>Choose an existing image or upload a new photo through Media Library.</p><div className="profile-photo-editor__actions"><Button variant="outline" size="sm" onClick={() => setMediaPickerOpen(true)}><ImagePlus size={13}/> Change photo</Button>{session?.user.avatarUrl && <Button variant="ghost" size="sm" onClick={() => void removeAvatar()}><Trash2 size={13}/> Remove</Button>}</div></div></div><Grid columns="repeat(2,minmax(0,1fr))" gap={10}><Field label="First name"><Input value={profile.first_name} onChange={e => setProfile({ ...profile, first_name: e.target.value })}/></Field><Field label="Last name"><Input value={profile.last_name} onChange={e => setProfile({ ...profile, last_name: e.target.value })}/></Field><Field label="Email"><Input type="email" value={profile.email} onChange={e => setProfile({ ...profile, email: e.target.value })}/></Field><Field label="Phone"><Input value={profile.phone} onChange={e => setProfile({ ...profile, phone: e.target.value })}/></Field><Field label="Timezone"><Input value={profile.timezone} onChange={e => setProfile({ ...profile, timezone: e.target.value })}/></Field><Field label="Language"><Input value={profile.locale} onChange={e => setProfile({ ...profile, locale: e.target.value })}/></Field></Grid><Inline gap={8} align="center" wrap="wrap"><Button variant="primary" size="sm" loading={busy} onClick={() => void saveProfile()}><Save size={13}/> Save Profile</Button>{session?.user.emailVerified ? <Badge tone="success">Email verified</Badge> : <><Badge tone="warning">Email unverified</Badge><Button size="sm" variant="outline" onClick={() => void apiRequest('/api/v1/auth/email/resend', { method: 'POST' }).catch(e => setError(e instanceof Error ? e.message : 'Could not send verification.'))}>Send verification</Button></>}</Inline></CardBody></Card>
  <Card mt={14}><CardHeader title="Password" description="Change your password and revoke other signed-in sessions"/><CardBody><Grid columns="repeat(3,minmax(0,1fr))" gap={10}><Field label="Current password"><Input type="password" value={passwordForm.currentPassword} onChange={e => setPasswordForm({ ...passwordForm, currentPassword: e.target.value })}/></Field><Field label="New password"><Input type="password" value={passwordForm.password} onChange={e => setPasswordForm({ ...passwordForm, password: e.target.value })}/></Field><Field label="Confirm"><Input type="password" value={passwordForm.confirmation} onChange={e => setPasswordForm({ ...passwordForm, confirmation: e.target.value })}/></Field></Grid><Button variant="primary" size="sm" loading={busy} disabled={!passwordForm.currentPassword || !passwordForm.password} onClick={() => void savePassword()}><KeyRound size={13}/> Change Password</Button></CardBody></Card>
  <Card mt={14}><CardHeader title="Multi-factor authentication" description="TOTP authenticator with single-use recovery codes. Workspace administrators can require MFA only after targeted users are enrolled." action={mfa?.enabled ? <Badge tone="success">Enabled</Badge> : <Badge tone="neutral">Not enabled</Badge>}/><CardBody>
    {mfa?.enabled ? <Stack gap={10} maxWidth={520}><div className="ui-card-description">Recovery codes remaining: {mfa.recovery_codes_remaining}</div><Field label="Authenticator or recovery code"><Input value={code} onChange={e => setCode(e.target.value)} inputMode="numeric" autoComplete="one-time-code" placeholder="123456"/></Field><Button variant="danger" size="sm" loading={busy} onClick={() => void disable()}>Disable MFA</Button></Stack> : <Button variant="primary" size="sm" loading={busy} onClick={() => void begin()}><ShieldCheck size={14}/> Set up authenticator</Button>}
  </CardBody></Card>
  <Modal open={Boolean(enrollment)} onClose={() => !busy && setEnrollment(null)} title="Set up authenticator" description="Add this secret/URI to your authenticator, save the recovery codes, then confirm a 6-digit code." size="lg" footer={<><Button variant="outline" onClick={() => setEnrollment(null)} disabled={busy}>Cancel</Button><Button variant="primary" onClick={() => void confirmMfa()} loading={busy}>Confirm MFA</Button></>}>
    {enrollment && <Stack gap={12}><Field label="Authenticator secret"><Inline gap={7}><Input readOnly value={enrollment.secret}/><Button iconOnly aria-label="Copy secret" onClick={() => void navigator.clipboard.writeText(enrollment.secret)}><Copy size={14}/></Button></Inline></Field><Field label="otpauth URI"><Input readOnly value={enrollment.otpauth_uri}/></Field><div><Box size={12} weight={650} mb={7}>Recovery codes — copy now</Box><Grid columns="repeat(2,minmax(0,1fr))" gap={6}>{enrollment.recovery_codes.map(item => <Box as="code" key={item} p={8} border="1px solid var(--border)" radius={6}>{item}</Box>)}</Grid></div><Field label="6-digit authenticator code"><Input value={code} onChange={e => setCode(e.target.value)} inputMode="numeric" autoComplete="one-time-code" placeholder="123456"/></Field></Stack>}
  </Modal>
  <MediaPicker open={mediaPickerOpen} workspaceId={workspace.id} imagesOnly title="Choose profile photo" onClose={() => setMediaPickerOpen(false)} onSelect={asset => void selectAvatar(asset)}/>
 </Page>;
}
