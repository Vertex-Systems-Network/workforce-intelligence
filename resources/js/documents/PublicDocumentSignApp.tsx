import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react'
import { CheckCircle2, FileSignature, LoaderCircle, PenLine, RotateCcw, XCircle } from 'lucide-react'
import { Alert, Button, Card, CardBody, Field, Input, Modal, Page, Pressable, Checkbox, Label, Link } from '../design-system'
import { translatePageCopy } from '../i18n/pageCopy'
import type { LocaleCode } from '../i18n/catalog'
import './public-document-sign.css'

type SigningPayload={request_uuid:string;document_uuid?:string|null;filename?:string|null;document_type?:string|null;signer_name?:string|null;signer_email?:string|null;role_label?:string|null;status:string;expires_at?:string|null;file_url:string;language?:string;direction?:'ltr'|'rtl'}

/** Renders the token-scoped public e-signature surface without requiring workspace authentication. */
export default function PublicDocumentSignApp(){
  const token=window.location.pathname.split('/').filter(Boolean).at(-1)??''
  const [data,setData]=useState<SigningPayload|null>(null)
  const [loading,setLoading]=useState(true),[busy,setBusy]=useState(''),[error,setError]=useState(''),[complete,setComplete]=useState('')
  const [method,setMethod]=useState<'typed'|'drawn'>('typed'),[typedName,setTypedName]=useState(''),[consent,setConsent]=useState(false),[declineOpen,setDeclineOpen]=useState(false)
  const canvasRef=useRef<HTMLCanvasElement|null>(null),drawingRef=useRef(false),lastRef=useRef<{x:number;y:number}|null>(null)
  const locale=((data?.language??document.documentElement.lang??'en').split('-')[0]||'en') as LocaleCode
  /** Localizes public signing chrome without mutating signer or document business data. */
  const lp=(value:string)=>translatePageCopy(locale,value)

  /** Loads safe request metadata using only the unguessable public signing token. */
  const load=async()=>{setLoading(true);setError('');try{const response=await fetch(`/api/v1/public/documents/sign/${encodeURIComponent(token)}`,{headers:{Accept:'application/json'}});const payload=await response.json().catch(()=>null);if(!response.ok)throw new Error(lp(payload?.message??'This signature request is unavailable.'));setData(payload.data);setTypedName(payload.data.signer_name??'');document.documentElement.dir=payload.data.direction??'ltr';document.documentElement.lang=payload.data.language??'en'}catch(reason){setError(reason instanceof Error?reason.message:lp('This signature request is unavailable.'))}finally{setLoading(false)}}
  useEffect(()=>{void load()},[token])

  /** Starts a pressure-independent pointer stroke on the signature canvas. */
  const startDraw=(event:ReactPointerEvent<HTMLCanvasElement>)=>{const canvas=canvasRef.current;if(!canvas)return;canvas.setPointerCapture(event.pointerId);const rect=canvas.getBoundingClientRect();drawingRef.current=true;lastRef.current={x:(event.clientX-rect.left)*(canvas.width/rect.width),y:(event.clientY-rect.top)*(canvas.height/rect.height)}}
  /** Draws one line segment while the pointer remains pressed. */
  const draw=(event:ReactPointerEvent<HTMLCanvasElement>)=>{if(!drawingRef.current||!lastRef.current)return;const canvas=canvasRef.current;if(!canvas)return;const rect=canvas.getBoundingClientRect();const next={x:(event.clientX-rect.left)*(canvas.width/rect.width),y:(event.clientY-rect.top)*(canvas.height/rect.height)};const ctx=canvas.getContext('2d');if(!ctx)return;ctx.strokeStyle='#111827';ctx.lineWidth=2.2;ctx.lineCap='round';ctx.beginPath();ctx.moveTo(lastRef.current.x,lastRef.current.y);ctx.lineTo(next.x,next.y);ctx.stroke();lastRef.current=next}
  /** Ends the active signature canvas stroke. */
  const endDraw=()=>{drawingRef.current=false;lastRef.current=null}
  /** Clears all drawn signature pixels while preserving canvas dimensions. */
  const clearSignature=()=>{const canvas=canvasRef.current;if(canvas)canvas.getContext('2d')?.clearRect(0,0,canvas.width,canvas.height)}
  /** Returns a compact PNG data URI when the signer chose drawn signature mode. */
  const signatureData=()=>method==='drawn'?canvasRef.current?.toDataURL('image/png')??'':undefined
  /** Submits explicit signing consent to the hash-token endpoint. */
  const sign=async()=>{if(!data||busy)return;setBusy('sign');setError('');try{const response=await fetch(`/api/v1/public/documents/sign/${encodeURIComponent(token)}`,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({signature_method:method,typed_name:typedName,signature_data:signatureData(),consent})});const payload=await response.json().catch(()=>null);if(!response.ok)throw new Error(lp(String(payload?.message??Object.values(payload?.errors??{})?.flat()?.[0]??'The signature could not be submitted.')));setComplete('signed');setData(current=>current?{...current,status:'signed'}:current)}catch(reason){setError(reason instanceof Error?reason.message:lp('The signature could not be submitted.'))}finally{setBusy('')}}
  /** Declines the signing request and permanently records the decision. */
  const decline=async()=>{if(!data||busy)return;setBusy('decline');setError('');try{const response=await fetch(`/api/v1/public/documents/sign/${encodeURIComponent(token)}/decline`,{method:'POST',headers:{Accept:'application/json'}});const payload=await response.json().catch(()=>null);if(!response.ok)throw new Error(lp(payload?.message??'The signature request could not be declined.'));setComplete('declined');setDeclineOpen(false);setData(current=>current?{...current,status:'declined'}:current)}catch(reason){setError(reason instanceof Error?reason.message:lp('The signature request could not be declined.'))}finally{setBusy('')}}

  if(loading)return <Page className="public-document-sign"><div className="public-document-sign__loading"><LoaderCircle className="spin" size={24}/> {lp('Loading secure document…')}</div></Page>
  if(error&&!data)return <Page className="public-document-sign"><Card><CardBody><Alert tone="danger">{error}</Alert></CardBody></Card></Page>
  if(!data)return null
  const terminal=complete||['signed','declined','expired'].includes(data.status)
  return <Page className="public-document-sign"><Link className="ui-skip-link" href="#document-sign-main">{lp('Skip to main content')}</Link><main id="document-sign-main" tabIndex={-1} className="public-document-sign__shell">
    <header className="public-document-sign__header"><div className="public-document-sign__brand"><FileSignature size={22}/><div><strong>{lp('WorkIntel Secure Sign')}</strong><span>{lp('Electronic signature request')}</span></div></div><span className={`public-document-sign__status is-${data.status}`}>{data.status}</span></header>
    {error&&<Alert tone="danger">{error}</Alert>}
    {terminal?<Card><CardBody><div className="public-document-sign__complete">{(complete||data.status)==='signed'?<CheckCircle2 size={34}/>:<XCircle size={34}/>}<h1>{(complete||data.status)==='signed'?lp('Document signed'):lp('Signature request closed')}</h1><p>{(complete||data.status)==='signed'?lp('Your signature has been securely recorded. You may close this page.'):lp('This signing request is no longer active.')}</p></div></CardBody></Card>:<div className="public-document-sign__grid">
      <section className="public-document-sign__document"><iframe title={data.filename??lp('Document')} src={data.file_url}/></section>
      <aside className="public-document-sign__panel"><Card><CardBody><div className="public-document-sign__summary"><h1>{lp('Review and sign')}</h1><dl><div><dt>{lp('Document')}</dt><dd>{data.filename??data.document_type??lp('Document')}</dd></div><div><dt>{lp('Signer')}</dt><dd>{data.signer_name??data.signer_email??lp('Authorized signer')}</dd></div>{data.role_label&&<div><dt>{lp('Role')}</dt><dd>{data.role_label}</dd></div>}{data.expires_at&&<div><dt>{lp('Expires')}</dt><dd>{new Date(data.expires_at).toLocaleString(data.language||undefined)}</dd></div>}</dl></div>
        <div className="public-document-sign__method"><Pressable type="button" aria-pressed={method==='typed'} className={method==='typed'?'is-active':''} onClick={()=>setMethod('typed')}>{lp('Type signature')}</Pressable><Pressable type="button" aria-pressed={method==='drawn'} className={method==='drawn'?'is-active':''} onClick={()=>setMethod('drawn')}>{lp('Draw signature')}</Pressable></div>
        {method==='typed'?<Field label={lp('Full legal name')}><Input value={typedName} onChange={event=>setTypedName(event.target.value)} autoComplete="name"/></Field>:<div className="public-document-sign__canvas-wrap"><div><span>{lp('Draw your signature')}</span><Button size="sm" variant="ghost" onClick={clearSignature}><RotateCcw size={13}/> {lp('Clear')}</Button></div><canvas ref={canvasRef} width={640} height={180} role="img" aria-label={lp('Signature drawing area. A typed signature is available as a keyboard-friendly alternative.')} onPointerDown={startDraw} onPointerMove={draw} onPointerUp={endDraw} onPointerCancel={endDraw} onPointerLeave={endDraw}/></div>}
        <Label className="public-document-sign__consent"><Checkbox checked={consent} onChange={event=>setConsent(event.target.checked)}/><span>{lp('I consent to use this electronic signature and confirm that I reviewed the document above.')}</span></Label>
        <div className="public-document-sign__actions"><Button variant="danger" disabled={Boolean(busy)} onClick={()=>setDeclineOpen(true)}>{busy==='decline'?lp('Declining…'):lp('Decline')}</Button><Button disabled={!consent||(method==='typed'&&!typedName.trim())||Boolean(busy)} loading={busy==='sign'} onClick={()=>void sign()}><PenLine size={14}/> {lp('Sign document')}</Button></div>
      </CardBody></Card></aside>
    </div>}
    <footer>{lp('Secure token access · No account required · Changes are audit recorded')}</footer>
    <Modal open={declineOpen} onClose={()=>setDeclineOpen(false)} title={lp('Decline signature request')} description={lp('This decision will be audit recorded and cannot be undone.')} footer={<><Button variant="outline" disabled={Boolean(busy)} onClick={()=>setDeclineOpen(false)}>{lp('Cancel')}</Button><Button variant="danger" loading={busy==='decline'} onClick={()=>void decline()}>{lp('Confirm decline')}</Button></>}><Alert tone="warning" dismissible={false}>{lp('Decline this signature request? This action will be recorded.')}</Alert></Modal>
  </main></Page>
}
