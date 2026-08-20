import { useEffect, useMemo, useState } from 'react'
import WebsiteRenderer from './WebsiteRenderer'
import type { PublicWebsitePayload } from './types'

/** Returns a public Website Studio route descriptor for live, staging-preview or custom-domain delivery. */
function routeDescriptor(){
 const path=window.location.pathname.replace(/\/+$/,'')||'/'
 const marker=(window as any).__WORKINTEL_PUBLIC_WEBSITE_HOST__ as string|undefined
 if(marker)return {mode:'host' as const,host:marker,path:path==='/'?'':path.replace(/^\//,'')}
 const parts=path.split('/').filter(Boolean)
 if(parts[0]==='site-preview'&&parts[1])return {mode:'preview' as const,token:parts[1],path:''}
 if(parts[0]==='site'&&parts[1])return {mode:'workspace' as const,workspace:parts[1],path:parts.slice(2).join('/')}
 return null
}

/** Adds or updates one SEO meta tag without touching unrelated application head tags. */
function setMeta(name:string,content:string,property=false){let element=document.head.querySelector<HTMLMetaElement>(`meta[${property?'property':'name'}="${CSS.escape(name)}"]`);if(!element){element=document.createElement('meta');element.setAttribute(property?'property':'name',name);document.head.appendChild(element)}element.content=content}

/** Renders one published workspace website on its /site path or assigned custom domain. */
export default function PublicWebsiteApp(){
 const descriptor=useMemo(routeDescriptor,[])
 const [payload,setPayload]=useState<PublicWebsitePayload|null>(null),[loading,setLoading]=useState(true),[error,setError]=useState('')
 useEffect(()=>{if(!descriptor){setError('Website route is invalid.');setLoading(false);return}if(descriptor.mode==='preview')setMeta('robots','noindex,nofollow');const params=new URLSearchParams();params.set('path',descriptor.path);const language=new URLSearchParams(window.location.search).get('lang');if(language)params.set('lang',language);const url=descriptor.mode==='preview'?`/api/v1/public-websites/preview/${encodeURIComponent(descriptor.token)}`:descriptor.mode==='host'?`/api/v1/public-websites/resolve?host=${encodeURIComponent(descriptor.host)}&${params}`:`/api/v1/public-websites/${encodeURIComponent(descriptor.workspace)}?${params}`;fetch(url,{headers:{Accept:'application/json'}}).then(async response=>{if(!response.ok)throw new Error(response.status===404?'This website or page is not published.':'Could not load this website.');return response.json()}).then((data:PublicWebsitePayload)=>{setPayload(data);document.documentElement.lang=data.page.language;document.documentElement.dir=['ar','ur'].includes(data.page.language)?'rtl':'ltr';document.title=data.page.seo_title||`${data.page.title} · ${data.site.name}`;if(data.page.seo_description)setMeta('description',data.page.seo_description);setMeta('og:title',data.page.seo_title||data.page.title,true);if(data.page.seo_description)setMeta('og:description',data.page.seo_description,true);if(data.page.og_image?.url)setMeta('og:image',new URL(data.page.og_image.url,window.location.origin).toString(),true)}).catch(err=>setError(err instanceof Error?err.message:'Could not load this website.')).finally(()=>setLoading(false))},[descriptor])
 if(loading)return <div className="wi-public-site-state" role="status" aria-live="polite"><span className="wi-public-site-spinner" aria-hidden="true"/>Loading website…</div>
 if(error||!payload)return <div className="wi-public-site-state" role="alert"><strong>Website unavailable</strong><span>{error||'The requested page is not available.'}</span></div>
 const workspaceSlug=descriptor?.mode==='workspace'?descriptor.workspace:(payload.site.workspace_slug||'')
 const basePath=descriptor?.mode==='workspace'?`/site/${workspaceSlug}`:''
 const isPreview=descriptor?.mode==='preview'||Boolean((payload as PublicWebsitePayload&{is_preview?:boolean}).is_preview)
 return <WebsiteRenderer payload={payload} workspaceSlug={workspaceSlug} basePath={basePath} preview={isPreview}/>
}
