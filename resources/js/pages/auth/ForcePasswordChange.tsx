import { useState, type FormEvent } from 'react';
import { KeyRound } from 'lucide-react';
import { useAuth } from '../../auth/AuthContext';
import { Alert, Button, Field, Input, Box, Form, FormActions } from '../../design-system';
import { AuthHeading } from './AuthPrimitives';
/** Handles the force password change operation for the WorkIntel client. */ export default function ForcePasswordChange() { const { changePassword, logout } = useAuth(); const [current, setCurrent] = useState(''), [password, setPassword] = useState(''), [confirmation, setConfirmation] = useState(''), [error, setError] = useState(''), [busy, setBusy] = useState(false); /** Handles the submit operation for the WorkIntel client. */ /** Handles the submit operation for the WorkIntel client. */ const submit = async (e: FormEvent) => { e.preventDefault(); setBusy(true); setError(''); try {
    await changePassword({ currentPassword: current, password, passwordConfirmation: confirmation });
}
catch (e) {
    setError(e instanceof Error ? e.message : 'Could not change password.');
}
finally {
    setBusy(false);
} }; return <Box minHeight="100vh" display="grid" placeItems="center" bg="var(--bg)" p={24}><Box className="ui-card" width="min(460px,100%)" p={24}><Box width={42} height={42} radius={10} display="grid" placeItems="center" bg="var(--accent-dim)" color="var(--accent)" mb={14}><KeyRound size={20}/></Box><AuthHeading kicker="Account security" title="Change your temporary password" description="An administrator created or reset this account. Choose your own password before accessing workspace data."/>{error && <Alert tone="danger">{error}</Alert>}<Form onSubmit={submit} gap={12} mt={12}><Field label="Current / temporary password"><Input type="password" value={current} onChange={e => setCurrent(e.target.value)} required/></Field><Field label="New password"><Input type="password" value={password} onChange={e => setPassword(e.target.value)} required/></Field><Field label="Confirm new password"><Input type="password" value={confirmation} onChange={e => setConfirmation(e.target.value)} required/></Field><FormActions align="between"><Button variant="ghost" type="button" onClick={logout}>Sign out</Button><Button variant="primary" type="submit" loading={busy}>Save new password</Button></FormActions></Form></Box></Box>; }
