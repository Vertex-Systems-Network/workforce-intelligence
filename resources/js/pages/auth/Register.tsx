import { useState, type FormEvent } from 'react'
import { ArrowRight, Check, Eye, EyeOff } from 'lucide-react'
import { useAuth } from '../../auth/AuthContext'
import { Alert, Button, Field, IconButton, Input, Checkbox, Form, Label } from '../../design-system'
import { AuthBackButton, AuthHeading, AuthMobileBrand } from './AuthPrimitives'

/** Handles the register operation for the WorkIntel client. */ export default function Register({ onLogin, productName = 'WorkIntel' }: { onLogin: () => void; productName?: string }) {
  const { register } = useAuth()
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [workEmail, setWorkEmail] = useState('')
  const [companyName, setCompanyName] = useState('')
  const [password, setPassword] = useState('')
  const [agreeToTerms, setAgreeToTerms] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const passwordChecks = [
    { label: '8 or more characters', pass: password.length >= 8 },
    { label: 'Contains a number', pass: /\d/.test(password) },
  ]

  /** Handles the submit operation for the WorkIntel client. */ async function submit(event: FormEvent) {
    event.preventDefault()
    setError('')
    if (!passwordChecks.every(item => item.pass)) {
      setError('Use a password with at least 8 characters and one number.')
      return
    }

    setSubmitting(true)
    try {
      await register({ firstName, lastName, workEmail, companyName, password, agreeToTerms })
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to create the workspace.')
    } finally {
      setSubmitting(false)
    }
  }

  return <>
    <AuthMobileBrand productName={productName}/>
    <AuthBackButton onClick={onLogin}/>
    <AuthHeading kicker="Start your workspace" title={<>Create your {productName} account</>} description="The first account becomes the workspace owner."/>

    {error && <Alert tone="danger">{error}</Alert>}

    <Form className="auth-form" onSubmit={submit}>
      <div className="auth-form__grid"><Field label="First name"><Input value={firstName} onChange={event => setFirstName(event.target.value)} autoComplete="given-name" required /></Field><Field label="Last name"><Input value={lastName} onChange={event => setLastName(event.target.value)} autoComplete="family-name" required /></Field></div>
      <Field label="Work email"><Input type="email" value={workEmail} onChange={event => setWorkEmail(event.target.value)} autoComplete="email" placeholder="you@company.com" required /></Field>
      <Field label="Company or workspace name"><Input value={companyName} onChange={event => setCompanyName(event.target.value)} placeholder="Acme Corp" required /></Field>
      <Field label="Password"><div className="auth-password"><Input type={showPassword ? 'text' : 'password'} value={password} onChange={event => setPassword(event.target.value)} autoComplete="new-password" required /><IconButton type="button" variant="ghost" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(value => !value)}>{showPassword ? <EyeOff size={15}/> : <Eye size={15}/>}</IconButton></div></Field>
      <div className="auth-password-rules">{passwordChecks.map(item => <span className={item.pass ? 'is-valid' : ''} key={item.label}><Check size={12}/>{item.label}</span>)}</div>
      <Label className="auth-check auth-check--terms"><Checkbox checked={agreeToTerms} onChange={event => setAgreeToTerms(event.target.checked)}/><span>I agree to the Terms of Service and Privacy Policy.</span></Label>
      <Button variant="primary" size="lg" type="submit" disabled={submitting}>{submitting ? 'Creating workspace…' : <>Create workspace <ArrowRight size={15}/></>}</Button>
    </Form>
  </>
}
