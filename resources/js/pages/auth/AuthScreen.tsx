import { useEffect, useState } from 'react'
import AuthLayout from './AuthLayout'
import Login from './Login'
import Register from './Register'
import JoinWorkspace from './JoinWorkspace'
import VerifyEmail from './VerifyEmail'
import { ForgotPassword, ResetPasswordScreen } from './PasswordRecovery'

export type PublicBranding = { product_name?:string; accent_color?:string|null; logo_url?:string|null; favicon_url?:string|null; hide_powered_by?:boolean; white_labeled?:boolean; login_title?:string|null; login_subtitle?:string|null }

/** Handles the auth screen operation for the WorkIntel client. */ export default function AuthScreen() {
  const path=window.location.pathname.replace(/\/+$/,'')||'/'
  const params=new URLSearchParams(window.location.search)
  const initialMode=path.startsWith('/join/')?'join':path.startsWith('/invite/')?'invite':path==='/reset-password'?'reset':path==='/verify-email'?'verify':'login'
  const [mode, setMode] = useState<'login' | 'register' | 'forgot' | 'reset' | 'join' | 'invite' | 'verify'>(initialMode)
  const [branding,setBranding]=useState<PublicBranding>({product_name:'WorkIntel',white_labeled:false})

  useEffect(()=>{
    let alive=true
    fetch('/api/platform/branding/current',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():null).then(data=>{
      if(!alive||!data)return
      setBranding(data)
      if(data.product_name)document.title=data.product_name
      if(data.accent_color)document.documentElement.style.setProperty('--accent',data.accent_color)
      if(data.favicon_url){let link=document.querySelector<HTMLLinkElement>('link[rel="icon"]');if(!link){link=document.createElement('link');link.rel='icon';document.head.appendChild(link)}link.href=data.favicon_url}
    }).catch(()=>undefined)
    return()=>{alive=false}
  },[])

  const product=branding.product_name || 'WorkIntel'
  /** Handles the go login operation for the WorkIntel client. */ const goLogin=()=>{if(window.location.pathname!=='/app')window.history.replaceState({},'', '/app');setMode('login')}
  const joinSlug=path.startsWith('/join/')?decodeURIComponent(path.split('/')[2]||''):undefined
  const inviteToken=path.startsWith('/invite/')?decodeURIComponent(path.split('/')[2]||''):undefined
  return <AuthLayout branding={branding}>
    {mode==='join'?<JoinWorkspace workspaceSlug={joinSlug} onLogin={goLogin} productName={product}/>
      :mode==='invite'?<JoinWorkspace inviteToken={inviteToken} onLogin={goLogin} productName={product}/>
      :mode==='verify'?<VerifyEmail token={params.get('token')||''} onLogin={goLogin} productName={product}/>
      :mode==='reset'?<ResetPasswordScreen token={params.get('token')||''} email={params.get('email')||''} onLogin={goLogin} productName={product}/>
      :mode==='forgot'?<ForgotPassword onLogin={()=>setMode('login')} productName={product}/>
      :mode === 'login'
      ? <Login productName={product} onRegister={() => setMode('register')} onForgot={()=>setMode('forgot')} />
      : <Register productName={product} onLogin={() => setMode('login')} />}
  </AuthLayout>
}
