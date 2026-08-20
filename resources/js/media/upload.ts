import { apiBlob, apiRequest, apiUpload } from '../api/client'
import type { MediaAsset } from './types'

export type MediaUploadFailure={name:string;message:string}
export type MediaUploadResult={asset:MediaAsset;duplicate:boolean}
export type MediaUploadResponse={data:MediaUploadResult[];failures?:MediaUploadFailure[];message:string;limits?:{max_file_mb:number;php_upload_max_bytes:number;php_post_max_bytes:number}}
export type MediaUploadCapabilities={max_file_mb:number;php_upload_max_bytes:number;php_post_max_bytes:number;writable?:boolean;max_files_per_request?:number;resumable_uploads?:boolean;chunk_size_bytes?:number;renditions_available?:boolean}
type UploadSession={uuid:string;original_name:string;size_bytes:number;chunk_size_bytes:number;total_chunks:number;received_chunks:number[];status:string;expires_at?:string|null}

/** Return a user-facing upload constraint error before the browser spends time sending an impossible request. */
export function mediaUploadConstraintError(files:File[],capabilities:MediaUploadCapabilities|null|undefined):string|null{
  if(!files.length)return 'Choose at least one file.'
  if(!capabilities)return null
  if(capabilities.writable===false)return 'Media storage is not writable. Ask an administrator to repair storage/app/private permissions.'
  const countLimit=Math.max(1,capabilities.max_files_per_request??20)
  if(files.length>countLimit)return `Upload at most ${countLimit} files at a time.`
  const appLimit=Math.max(1,capabilities.max_file_mb)*1024*1024
  const phpFileLimit=capabilities.php_upload_max_bytes>0?capabilities.php_upload_max_bytes:Number.POSITIVE_INFINITY
  const phpPostLimit=capabilities.php_post_max_bytes>0?capabilities.php_post_max_bytes:Number.POSITIVE_INFINITY
  const perFileLimit=capabilities.resumable_uploads?appLimit:Math.min(appLimit,phpFileLimit,phpPostLimit)
  const oversized=files.find(file=>file.size>perFileLimit)
  if(oversized)return `${oversized.name} is too large. The effective per-file limit is ${Math.max(1,Math.floor(perFileLimit/1024/1024))} MB.`
  return null
}

/** Compute a chunk checksum when Web Crypto is available; the server still validates the final binary independently. */
async function chunkChecksum(blob:Blob):Promise<string|null>{try{if(!globalThis.crypto?.subtle)return null;const digest=await crypto.subtle.digest('SHA-256',await blob.arrayBuffer());return [...new Uint8Array(digest)].map(value=>value.toString(16).padStart(2,'0')).join('')}catch{return null}}
/** Build a stable browser key so an interrupted file can reconnect to its existing server upload session. */
function resumeKey(file:File,workspaceId:number){return `workintel.media.resume.${workspaceId}.${encodeURIComponent(file.name)}.${file.size}.${file.lastModified}`}
/** Read local resumable state without letting storage restrictions break upload. */
function storedSession(key:string){try{return window.localStorage.getItem(key)}catch{return null}}
/** Persist or clear local resumable state without making browser storage a hard dependency. */
function saveSession(key:string,value:string|null){try{if(value)window.localStorage.setItem(key,value);else window.localStorage.removeItem(key)}catch{}}

/** Upload one large file in durable chunks and resume only missing chunks from a prior browser interruption. */
async function uploadMediaFileResumable(file:File,workspaceId:number,options:{folderId?:number|null;capabilities:MediaUploadCapabilities;onProgress?:(percent:number)=>void}):Promise<MediaUploadResponse>{
  const key=resumeKey(file,workspaceId);let session:UploadSession|null=null;const stored=storedSession(key)
  if(stored){try{const response=await apiRequest<{data:UploadSession}>(`/api/v1/media/uploads/${encodeURIComponent(stored)}`,{workspaceId,silent:true});if(response.data.status==='active'&&response.data.original_name===file.name&&response.data.size_bytes===file.size)session=response.data;else saveSession(key,null)}catch{saveSession(key,null)}}
  if(!session){const response=await apiRequest<{data:UploadSession}>('/api/v1/media/uploads',{method:'POST',workspaceId,silent:true,body:JSON.stringify({original_name:file.name,mime_type:file.type||null,size_bytes:file.size,folder_id:options.folderId??null,chunk_size_bytes:options.capabilities.chunk_size_bytes??5*1024*1024})});session=response.data;saveSession(key,session.uuid)}
  const received=new Set(session.received_chunks??[]),chunkSize=session.chunk_size_bytes,total=Math.max(1,file.size)
  let durableBytes=0;for(const index of received)durableBytes+=Math.min(chunkSize,Math.max(0,file.size-index*chunkSize));options.onProgress?.(Math.min(99,Math.round(durableBytes/total*100)))
  for(let index=0;index<session.total_chunks;index++){if(received.has(index))continue;const start=index*chunkSize,end=Math.min(file.size,start+chunkSize),blob=file.slice(start,end),body=new FormData();body.append('chunk',blob,`${file.name}.part-${index}`);const checksum=await chunkChecksum(blob);if(checksum)body.append('checksum_sha256',checksum);await apiUpload<{data:UploadSession}>(`/api/v1/media/uploads/${encodeURIComponent(session.uuid)}/chunks/${index}`,body,{workspaceId,onProgress:progress=>{const current=durableBytes+(progress.percent/100)*blob.size;options.onProgress?.(Math.min(99,Math.round(current/total*100)))}});durableBytes+=blob.size;received.add(index);options.onProgress?.(Math.min(99,Math.round(durableBytes/total*100)))}
  const complete=await apiRequest<MediaUploadResponse>(`/api/v1/media/uploads/${encodeURIComponent(session.uuid)}/complete`,{method:'POST',workspaceId,silent:true,body:JSON.stringify({})});saveSession(key,null);options.onProgress?.(100);return complete
}

/** Upload media files one-by-one so failures stay isolated and large files can switch to resumable transport. */
export async function uploadMediaFiles(files:File[],workspaceId:number,options:{folderId?:number|null;capabilities?:MediaUploadCapabilities|null;onProgress?:(percent:number)=>void}={}):Promise<MediaUploadResponse>{
  const data:MediaUploadResult[]=[],failures:MediaUploadFailure[]=[];const totalBytes=Math.max(1,files.reduce((sum,file)=>sum+file.size,0));let finishedBytes=0
  for(const file of files){try{const capabilities=options.capabilities??null;const phpDirect=Math.min(capabilities?.php_upload_max_bytes||Number.POSITIVE_INFINITY,capabilities?.php_post_max_bytes||Number.POSITIVE_INFINITY);const chunkThreshold=Math.min(12*1024*1024,Number.isFinite(phpDirect)?Math.max(1024*1024,phpDirect-256*1024):12*1024*1024);const resumable=Boolean(capabilities?.resumable_uploads&&(file.size>chunkThreshold||file.size>(capabilities?.chunk_size_bytes??5*1024*1024)*2));let response:MediaUploadResponse
      /** Translate current-file progress into aggregate multi-file progress. */
      const fileProgress=(percent:number)=>options.onProgress?.(Math.round((finishedBytes+(percent/100)*file.size)/totalBytes*100))
      if(resumable&&capabilities)response=await uploadMediaFileResumable(file,workspaceId,{folderId:options.folderId,capabilities,onProgress:fileProgress})
      else{const body=new FormData();body.append('file',file,file.name);if(options.folderId)body.append('folder_id',String(options.folderId));response=await apiUpload<MediaUploadResponse>('/api/v1/media',body,{workspaceId,onProgress:progress=>fileProgress(progress.percent)})}
      data.push(...response.data);failures.push(...(response.failures??[]))
    }catch(reason){failures.push({name:file.name,message:reason instanceof Error?reason.message:'Upload failed.'})}finally{finishedBytes+=file.size;options.onProgress?.(Math.round(finishedBytes/totalBytes*100))}}
  return {data,failures,message:`${data.length} media file${data.length===1?'':'s'} processed.${failures.length?` ${failures.length} failed.`:''}`}
}

/** Replace the current binary of an asset while preserving the previous immutable version. */
export async function replaceMediaBinary(assetId:number,file:File,workspaceId:number,onProgress?:(percent:number)=>void):Promise<MediaAsset>{const body=new FormData();body.append('file',file,file.name);const response=await apiUpload<{data:MediaAsset}>(`/api/v1/media/${assetId}/replace`,body,{workspaceId,onProgress:progress=>onProgress?.(progress.percent)});return response.data}
/** Materialize an existing Media Library asset as a browser File for legacy upload endpoints. */
export async function mediaAssetToFile(asset:MediaAsset,workspaceId:number):Promise<File>{const blob=await apiBlob(asset.content_url,workspaceId,false);return new File([blob],asset.original_name||asset.name,{type:asset.mime_type||blob.type||'application/octet-stream',lastModified:Date.now()})}
