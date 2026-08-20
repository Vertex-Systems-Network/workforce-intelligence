import { ImagePlus, Library, Star, Upload, X } from 'lucide-react'
import { useEffect, useRef, useState, type DragEvent } from 'react'
import { apiRequest } from '../api/client'
import { Alert, Badge, Button, EmptyState, Modal, SearchInput, Select, Option, Tabs, Pressable, HiddenFileInput, ProgressBar } from '../design-system'
import { MediaThumbnail } from './MediaThumbnail'
import { mediaUploadConstraintError, uploadMediaFiles, type MediaUploadCapabilities } from './upload'
import type { MediaAsset, MediaCollection } from './types'


/** Return whether one Media Library asset matches the chooser's HTML accept contract. */
function acceptsAsset(asset:MediaAsset,accept?:string,imagesOnly=false):boolean{
  if(imagesOnly&&asset.category!=='image')return false
  if(!accept||accept.trim()==='')return true
  const mime=String(asset.mime_type??'').toLowerCase(),extension=`.${String(asset.extension??'').toLowerCase()}`
  return accept.split(',').map(value=>value.trim().toLowerCase()).filter(Boolean).some(rule=>rule.startsWith('.')?extension===rule:rule.endsWith('/*')?mime.startsWith(rule.slice(0,-1)):mime===rule)
}
/** Return whether a local file matches the chooser's accept contract, including drag-and-drop uploads. */
function acceptsFile(file:File,accept?:string,imagesOnly=false):boolean{
  if(imagesOnly&&!file.type.toLowerCase().startsWith('image/'))return false
  if(!accept||accept.trim()==='')return true
  const mime=file.type.toLowerCase(),name=file.name.toLowerCase()
  return accept.split(',').map(value=>value.trim().toLowerCase()).filter(Boolean).some(rule=>rule.startsWith('.')?name.endsWith(rule):rule.endsWith('/*')?mime.startsWith(rule.slice(0,-1)):mime===rule)
}

/** Lets any supported editor choose an existing Media Library asset or upload a new file with progress feedback. */
export function MediaPicker({open,workspaceId,onClose,onSelect,onSelectMany,imagesOnly=false,title='Select media',accept,allowUpload=true,multiple=false,maxFiles=20}:{open:boolean;workspaceId:number;onClose:()=>void;onSelect:(asset:MediaAsset)=>void;onSelectMany?:(assets:MediaAsset[])=>void;imagesOnly?:boolean;title?:string;accept?:string;allowUpload?:boolean;multiple?:boolean;maxFiles?:number}){
  const [rows,setRows]=useState<MediaAsset[]>([]),[collections,setCollections]=useState<MediaCollection[]>([]),[search,setSearch]=useState(''),[collectionId,setCollectionId]=useState(''),[favoritesOnly,setFavoritesOnly]=useState(false),[category,setCategory]=useState(''),[loading,setLoading]=useState(false),[uploading,setUploading]=useState(false),[progress,setProgress]=useState(0),[error,setError]=useState(''),[tab,setTab]=useState<'library'|'upload'>('library'),[dragging,setDragging]=useState(false),[capabilities,setCapabilities]=useState<MediaUploadCapabilities|null>(null),[selectedAssets,setSelectedAssets]=useState<MediaAsset[]>([])
  const inputRef=useRef<HTMLInputElement|null>(null)

  /** Loads the current picker results using the Media Library search endpoint. */
  const load=async()=>{if(!open)return;setLoading(true);try{const params=new URLSearchParams({per_page:'60'});if(search.trim())params.set('search',search.trim());if(collectionId)params.set('collection_id',collectionId);if(favoritesOnly)params.set('favorite','1');if(imagesOnly)params.set('category','image');else if(category)params.set('category',category);const [response,health]=await Promise.all([apiRequest<{data:MediaAsset[];collections:MediaCollection[]}>(`/api/v1/media?${params}`,{workspaceId,silent:true}),allowUpload?apiRequest<{data:MediaUploadCapabilities}>('/api/v1/media/capabilities',{workspaceId,silent:true}).catch(()=>null):Promise.resolve(null)]);setRows(response.data.filter(asset=>acceptsAsset(asset,accept,imagesOnly)));setCollections(response.collections??[]);setCapabilities(health?.data??null)}catch(reason){setError(reason instanceof Error?reason.message:'Could not load Media Library.')}finally{setLoading(false)}}
  useEffect(()=>{const timer=window.setTimeout(()=>void load(),180);return()=>window.clearTimeout(timer)},[open,search,collectionId,favoritesOnly,category,workspaceId,imagesOnly,accept,allowUpload])
  useEffect(()=>{if(open){setError('');setProgress(0);setTab('library');setSelectedAssets([]);setCollectionId('');setFavoritesOnly(false);setCategory('')}},[open])

  /** Toggle one reusable asset in multiple-selection mode without closing the Media Library. */
  const toggleAsset=(asset:MediaAsset)=>setSelectedAssets(current=>{if(current.some(row=>row.id===asset.id))return current.filter(row=>row.id!==asset.id);if(current.length>=Math.max(1,maxFiles)){setError(`You can select up to ${Math.max(1,maxFiles)} files at once.`);return current}setError('');return [...current,asset]})
  /** Commit the current multi-selection to the calling file workflow. */
  const commitSelection=()=>{if(!selectedAssets.length)return;if(onSelectMany)onSelectMany(selectedAssets);else onSelect(selectedAssets[0]);onClose()}

  /** Upload files from inside the picker and select the first successful asset. */
  const upload=async(files:FileList|File[]|null)=>{const selected=Array.from(files??[]);const allowedCount=multiple?Math.max(1,maxFiles):1;const candidate=selected.slice(0,allowedCount);const list=candidate.filter(file=>acceptsFile(file,accept,imagesOnly));if(!list.length){if(selected.length)setError('The selected file type is not allowed for this field.');return}const messages:string[]=[];if(selected.length>allowedCount)messages.push(`Only the first ${allowedCount} file${allowedCount===1?'':'s'} will be processed.`);if(list.length!==candidate.length)messages.push(`${candidate.length-list.length} unsupported file${candidate.length-list.length===1?' was':'s were'} skipped.`);setError(messages.join(' '));const constraint=mediaUploadConstraintError(list,capabilities);if(constraint){setError(constraint);return}setUploading(true);setProgress(0);try{const response=await uploadMediaFiles(list,workspaceId,{capabilities,onProgress:setProgress});const assets=response.data.map(item=>item.asset);const asset=assets[0];if(response.failures?.length)setError(response.failures.map(item=>`${item.name}: ${item.message}`).join(' · '));if(assets.length){if(multiple&&onSelectMany)onSelectMany(assets.slice(0,allowedCount));else if(asset)onSelect(asset);onClose()}else await load()}catch(reason){setError(reason instanceof Error?reason.message:'Upload failed.')}finally{setUploading(false);if(inputRef.current)inputRef.current.value=''}}
  /** Handle dropped files without navigating the browser away from the editor. */
  const drop=(event:DragEvent<HTMLDivElement>)=>{event.preventDefault();setDragging(false);void upload(event.dataTransfer.files)}

  return <Modal open={open} onClose={onClose} title={title} description="Choose an existing workspace asset or upload a new file without leaving your workflow." size="xl" footer={<><Button variant="outline" onClick={onClose}>Cancel</Button>{multiple&&tab==='library'&&<Button variant="primary" disabled={!selectedAssets.length} onClick={commitSelection}>Use {selectedAssets.length} selected</Button>}</>}>
    <div className="media-picker">
      {error&&<Alert tone="danger">{error}</Alert>}
      <Tabs value={tab} onChange={value=>setTab(value as 'library'|'upload')} tabs={[{value:'library',label:<><Library size={13}/> Media Library</>},{value:'upload',label:<><Upload size={13}/> Upload</>}]} />
      {allowUpload&&capabilities&&<div className="media-picker__health"><Badge tone={capabilities.writable?'success':'danger'}>{capabilities.writable?'Storage ready':'Storage unavailable'}</Badge><span>App limit {capabilities.max_file_mb} MB/file · {multiple?`Up to ${Math.max(1,maxFiles)} files · `:''}PHP upload limit {Math.max(1,Math.round(capabilities.php_upload_max_bytes/1024/1024))} MB{capabilities.resumable_uploads?' · resumable large files':''}</span></div>}
      {tab==='library'?<>
        <div className="media-picker__toolbar"><SearchInput value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search media…"/>{!imagesOnly&&<Select value={category} onChange={event=>setCategory(event.target.value)}><Option value="">All types</Option><Option value="image">Images</Option><Option value="video">Video</Option><Option value="document">Documents</Option><Option value="audio">Audio</Option></Select>}<Select value={collectionId} onChange={event=>setCollectionId(event.target.value)}><Option value="">All collections</Option>{collections.map(collection=><Option key={collection.id} value={collection.id}>{collection.name} ({collection.assets_count})</Option>)}</Select><Button variant={favoritesOnly?"secondary":"outline"} onClick={()=>setFavoritesOnly(value=>!value)}><Star size={13}/> Favorites</Button>{allowUpload&&<Button variant="outline" onClick={()=>setTab('upload')}><Upload size={14}/> Upload new</Button>}</div>
        {loading?<div className="media-picker__grid">{Array.from({length:8}).map((_,i)=><div key={i} className="media-card-skeleton"/>)}</div>:rows.length?<div className="media-picker__grid">{rows.map(asset=>{const selected=selectedAssets.some(row=>row.id===asset.id);return <Pressable key={asset.id} type="button" className={`media-picker__asset${selected?' is-selected':''}`} aria-pressed={multiple?selected:undefined} onClick={()=>{if(multiple){toggleAsset(asset);return}onSelect(asset);onClose()}}><MediaThumbnail asset={asset} workspaceId={workspaceId}/><span>{asset.name}</span><small>{asset.extension?.toUpperCase()||asset.category}{asset.is_favorite?' · Favorite':''}{selected?' · Selected':''}</small></Pressable>})}</div>:<EmptyState icon={<ImagePlus size={28}/>} title="No media found" text={search?'Try a different search.':'Upload the first media asset for this workspace.'} action={allowUpload?<Button size="sm" onClick={()=>setTab('upload')}><Upload size={13}/> Upload media</Button>:undefined}/>}</>
      :<div className={`media-picker__dropzone${dragging?' is-dragging':''}${uploading?' is-busy':''}`} onDragEnter={event=>{event.preventDefault();setDragging(true)}} onDragOver={event=>event.preventDefault()} onDragLeave={()=>setDragging(false)} onDrop={drop}>
        <Upload size={28}/><strong>{uploading?`Uploading ${progress}%`:'Drop files here'}</strong><span>or choose files from this device. Uploaded assets are saved to Media Library and remain reusable.</span>{uploading&&<div className="media-upload-progress"><ProgressBar value={progress} label="Media upload progress" /></div>}<Button disabled={!allowUpload||uploading} loading={uploading} onClick={()=>inputRef.current?.click()}><Upload size={14}/> Choose file{imagesOnly?'':'s'}</Button><HiddenFileInput ref={inputRef} multiple={multiple} accept={accept??(imagesOnly?'image/*':undefined)} onChange={event=>void upload(event.target.files)}/>{!uploading&&<Pressable type="button" className="media-picker__back" onClick={()=>setTab('library')}><X size={12}/> Back to library</Pressable>}
      </div>}
    </div>
  </Modal>
}
