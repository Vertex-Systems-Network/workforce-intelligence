import { useEffect, useState, type FormEvent } from 'react'
import { ArrowRight, Eye, EyeOff } from 'lucide-react'
import { useAuth } from '../../auth/AuthContext'
import { apiRequest } from '../../api/client'
import { CLIENT_PORTAL_DEMO, DEMO_ACCOUNTS } from '../../auth/demoData'
import { Alert, Button, Field, IconButton, Input, Pressable, Checkbox, Form, Label } from '../../design-system'
import { useLocalization } from '../../i18n/LocalizationContext'
import { AuthHeading, AuthMobileBrand } from './AuthPrimitives'

/** Handles the login operation for the WorkIntel client. */ export default function Login({ onRegister, onForgot, productName = 'WorkIntel' }: { onRegister: () => void; onForgot:()=>void; productName?: string }) {
  const { login } = useAuth()
  const { t } = useLocalization()
  const [email, setEmail] = useState('owner@acme.test')
  const [password, setPassword] = useState('password')
  const [remember, setRemember] = useState(true)
  const [mfaCode, setMfaCode] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [runtimeDemos, setRuntimeDemos] = useState<Array<{email:string;password:string;name:string;role:string;role_name:string}>>([])
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    void apiRequest<{data:Array<{email:string;password:string;name:string;role:string;role_name:string}>}>('/api/v1/auth/demo-accounts',{silent:true})
      .then(result=>setRuntimeDemos(result.data)).catch(()=>setRuntimeDemos([]))
  }, [])

  /** Handles the submit operation for the WorkIntel client. */ async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      await login({ email, password, remember, mfaCode })
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to sign in.')
    } finally {
      setSubmitting(false)
    }
  }

  return <>
    <AuthMobileBrand productName={productName}/>
    <AuthHeading kicker={t('auth.welcome')} title={t('auth.signin_title')} description={t('auth.signin_text')}/>

    {error && <Alert tone="danger">{error}</Alert>}

    <Form className="auth-form" onSubmit={submit}>
      <Field label={t('auth.email')}><Input type="email" autoComplete="email" value={email} onChange={event => setEmail(event.target.value)} placeholder="you@company.com" required /></Field>
      <Field label={t('auth.password')}><div className="auth-password"><Input type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={password} onChange={event => setPassword(event.target.value)} required /><IconButton type="button" variant="ghost" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(value => !value)}>{showPassword ? <EyeOff size={15}/> : <Eye size={15}/>}</IconButton></div></Field>
      <Field label={t('auth.mfa')} hint="Only required when your workspace enforces MFA."><Input value={mfaCode} onChange={event => setMfaCode(event.target.value)} inputMode="numeric" autoComplete="one-time-code" placeholder="123456" /></Field>
      <div className="auth-form__row"><Label className="auth-check"><Checkbox checked={remember} onChange={event => setRemember(event.target.checked)}/><span>{t('auth.keep_signed_in')}</span></Label><Pressable type="button" className="auth-link-button" onClick={onForgot}>{t('auth.forgot')}</Pressable></div>
      <Button variant="primary" size="lg" type="submit" disabled={submitting}>{submitting ? t('auth.signing_in') : <>{t('auth.sign_in')} <ArrowRight size={15}/></>}</Button>
    </Form>

    <div className="auth-divider"><span>{t('auth.demo_accounts')}</span></div>
    <div className="auth-demo-list">
      {(runtimeDemos.length?runtimeDemos.map(account=><Pressable key={account.email} type="button" onClick={()=>{setEmail(account.email);setPassword(account.password)}}><span>{account.name.split(' ').map(v=>v[0]).join('').slice(0,2).toUpperCase()}</span><div><strong>{account.role_name}</strong><small>{account.email}</small></div><code>{account.password}</code></Pressable>):DEMO_ACCOUNTS.map(account => <Pressable key={account.user.email} type="button" onClick={() => { setEmail(account.user.email); setPassword(account.password) }}><span>{account.user.avatar}</span><div><strong>{account.user.workspaces[0].role.replace('-', ' ')}</strong><small>{account.user.email}</small></div><code>{account.password}</code></Pressable>))}
      <Pressable type="button" onClick={()=>{window.location.href=CLIENT_PORTAL_DEMO.path}}><span>CP</span><div><strong>{CLIENT_PORTAL_DEMO.label}</strong><small>{CLIENT_PORTAL_DEMO.email}</small></div><code>{CLIENT_PORTAL_DEMO.password}</code></Pressable>
    </div>
    <p className="auth-switch">{t('auth.new_to',{product:productName})} <Pressable type="button" onClick={onRegister}>{t('auth.create_workspace')}</Pressable></p>
  </>
}
