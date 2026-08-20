import { emitToast } from '../design-system/toast'
/** Provides api error behavior for the WorkIntel client. */ export class ApiError extends Error {
  /** Initializes the class state and required dependencies. */ constructor(message: string, public status: number, public details?: unknown) {
    super(message)
    this.name = 'ApiError'
  }
}

const API_URL = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')

let pendingRequests = 0
const requestListeners = new Set<(pending: number) => void>()

/** Notify auth state owners when the server confirms that the browser session is no longer valid. */
function invalidateAuthOnStatus(status:number,path:string):boolean{
  if (![401, 419].includes(status)) return false
  if (path.includes('/auth/login') || path.includes('/auth/register') || path.includes('/auth/password/')) return false
  window.dispatchEvent(new CustomEvent('workintel:auth-invalidated', { detail: { status, path } }))
  return true
}

/** Handles the publish request state operation for the WorkIntel client. */ function publishRequestState() {
  requestListeners.forEach(listener => listener(pendingRequests))
}

/** Handles the start request operation for the WorkIntel client. */ function startRequest() {
  pendingRequests += 1
  publishRequestState()
}

/** Handles the finish request operation for the WorkIntel client. */ function finishRequest() {
  pendingRequests = Math.max(0, pendingRequests - 1)
  publishRequestState()
}

/** Handles the subscribe request activity operation for the WorkIntel client. */ export function subscribeRequestActivity(listener: (pending: number) => void) {
  requestListeners.add(listener)
  listener(pendingRequests)
  return () => { requestListeners.delete(listener) }
}

/** Returns read cookie data required by the current workflow. */ function readCookie(name: string): string | null {
  const prefix = `${name}=`
  const cookie = document.cookie
    .split('; ')
    .find(item => item.startsWith(prefix))

  if (!cookie) return null
  return decodeURIComponent(cookie.slice(prefix.length))
}

/** Handles the requires csrf token operation for the WorkIntel client. */ function requiresCsrfToken(method: string): boolean {
  return !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())
}

/** Handles the api request operation for the WorkIntel client. */ export async function apiRequest<T>(
  path: string,
  options: RequestInit & { workspaceId?: number; silent?: boolean } = {},
): Promise<T> {
  const { workspaceId, silent = false, ...requestOptions } = options
  const headers = new Headers(requestOptions.headers)
  const method = requestOptions.method ?? 'GET'

  headers.set('Accept', 'application/json')
  const locale = window.localStorage.getItem('workintel-language')
  if (locale && !headers.has('X-Locale')) headers.set('X-Locale', locale)

  if (requestOptions.body && !(requestOptions.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  if (workspaceId) {
    headers.set('X-Workspace-Id', String(workspaceId))
  }

  if (requiresCsrfToken(method)) {
    const xsrfToken = readCookie('XSRF-TOKEN')
    if (xsrfToken) {
      headers.set('X-XSRF-TOKEN', xsrfToken)
    }
  }

  if (!silent) startRequest()

  try {
    const response = await fetch(`${API_URL}${path}`, {
      ...requestOptions,
      method,
      headers,
      credentials: 'include',
    })

    const payload = response.status === 204
      ? null
      : await response.json().catch(() => null)

    if (!response.ok) {
      const authInvalidated = invalidateAuthOnStatus(response.status, path)
      const error = new ApiError(getErrorMessage(payload), response.status, payload)
      if (!silent && !authInvalidated) emitToast({ tone: 'danger', title: response.status >= 500 ? 'Server error' : 'Request failed', message: error.message })
      throw error
    }

    if (!silent && method.toUpperCase() !== 'GET' && payload && typeof (payload as any).message === 'string') {
      emitToast({ tone: 'success', title: (payload as any).message })
    }
    return payload as T
  } catch (error) {
    if (!silent && !(error instanceof ApiError)) emitToast({ tone: 'danger', title: 'Network error', message: error instanceof Error ? error.message : 'The request could not be completed.' })
    throw error
  } finally {
    if (!silent) finishRequest()
  }
}


export type ApiUploadOptions = { workspaceId?:number; silent?:boolean; onProgress?:(progress:{loaded:number;total:number;percent:number})=>void }

/** Upload multipart form data with real browser progress while preserving WorkIntel auth, locale and toast behavior. */
export function apiUpload<T>(path:string,body:FormData,options:ApiUploadOptions={}):Promise<T>{
  const {workspaceId,silent=false,onProgress}=options
  const locale=window.localStorage.getItem('workintel-language')
  const xsrfToken=readCookie('XSRF-TOKEN')
  if(!silent)startRequest()
  return new Promise<T>((resolve,reject)=>{
    const request=new XMLHttpRequest()
    request.open('POST',`${API_URL}${path}`,true)
    request.withCredentials=true
    request.setRequestHeader('Accept','application/json')
    if(locale)request.setRequestHeader('X-Locale',locale)
    if(workspaceId)request.setRequestHeader('X-Workspace-Id',String(workspaceId))
    if(xsrfToken)request.setRequestHeader('X-XSRF-TOKEN',xsrfToken)
    request.upload.addEventListener('progress',event=>{if(!event.lengthComputable)return;const total=Math.max(1,event.total);onProgress?.({loaded:event.loaded,total,percent:Math.min(100,Math.round(event.loaded/total*100))})})
    request.addEventListener('load',()=>{
      const payload=request.responseText?(()=>{try{return JSON.parse(request.responseText)}catch{return null}})():null
      if(request.status>=200&&request.status<300){if(!silent&&payload&&typeof payload.message==='string')emitToast({tone:'success',title:payload.message});resolve(payload as T);return}
      const authInvalidated=invalidateAuthOnStatus(request.status||0,path)
      const message=request.status===413?'Upload exceeds the PHP or web-server request limit. Reduce the file size or raise upload_max_filesize/post_max_size.':getErrorMessage(payload)
      const error=new ApiError(message,request.status||0,payload)
      if(!silent&&!authInvalidated)emitToast({tone:'danger',title:request.status>=500?'Server error':'Upload failed',message:error.message})
      reject(error)
    })
    request.addEventListener('error',()=>{const error=new ApiError('The upload could not reach the server.',0);if(!silent)emitToast({tone:'danger',title:'Upload failed',message:error.message});reject(error)})
    request.addEventListener('abort',()=>reject(new ApiError('The upload was cancelled.',0)))
    request.addEventListener('loadend',()=>{if(!silent)finishRequest()})
    request.send(body)
  })
}


/** Fetches an authenticated binary response without forcing a browser download. */ export async function apiBlob(path: string, workspaceId?: number, silent = true): Promise<Blob> {
  const headers = new Headers({ Accept: '*/*' })
  const locale = window.localStorage.getItem('workintel-language')
  if (locale) headers.set('X-Locale', locale)
  if (workspaceId) headers.set('X-Workspace-Id', String(workspaceId))
  if (!silent) startRequest()
  try {
    const response = await fetch(`${API_URL}${path}`, { method: 'GET', headers, credentials: 'include' })
    if (!response.ok) {
      invalidateAuthOnStatus(response.status, path)
      const payload = await response.json().catch(() => null)
      throw new ApiError(getErrorMessage(payload), response.status, payload)
    }
    return await response.blob()
  } finally { if (!silent) finishRequest() }
}

/** Returns get error message data required by the current workflow. */ function getErrorMessage(payload: any): string {
  if (payload?.errors && typeof payload.errors === 'object') {
    for (const value of Object.values(payload.errors)) {
      if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0]
      }
    }
  }

  if (typeof payload?.message === 'string' && payload.message.trim()) {
    return payload.message
  }

  return 'The request could not be completed.'
}

/** Handles the api download operation for the WorkIntel client. */ export async function apiDownload(path: string, workspaceId?: number): Promise<{ blob: Blob; filename: string }> {
  const headers = new Headers({ Accept: 'application/octet-stream' })
  const locale = window.localStorage.getItem('workintel-language')
  if (locale) headers.set('X-Locale', locale)
  if (workspaceId) headers.set('X-Workspace-Id', String(workspaceId))

  startRequest()
  try {
    const response = await fetch(`${API_URL}${path}`, {
      method: 'GET',
      headers,
      credentials: 'include',
    })

    if (!response.ok) {
      invalidateAuthOnStatus(response.status, path)
      const payload = await response.json().catch(() => null)
      const error = new ApiError(getErrorMessage(payload), response.status, payload)
      emitToast({ tone: 'danger', title: 'Download failed', message: error.message })
      throw error
    }

    const disposition = response.headers.get('content-disposition') ?? ''
    const match = disposition.match(/filename\*?=(?:UTF-8''|\")?([^\";]+)/i)
    const filename = match ? decodeURIComponent(match[1].replace(/\"/g, '').trim()) : 'download'
    return { blob: await response.blob(), filename }
  } finally {
    finishRequest()
  }
}
