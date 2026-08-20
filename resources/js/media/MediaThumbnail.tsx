import { Image } from '../design-system'
import { File, FileAudio, FileText, Film, Image as ImageIcon } from 'lucide-react'
import { useEffect, useState } from 'react'
import { apiBlob } from '../api/client'
import type { MediaAsset } from './types'

/** Renders an authenticated thumbnail or a category icon without exposing private storage paths. */
export function MediaThumbnail({asset,workspaceId,className=''}:{asset:MediaAsset;workspaceId:number;className?:string}){
  const [url,setUrl]=useState<string|null>(asset.public_url??null)
  useEffect(()=>{
    if(asset.public_url){setUrl(asset.public_url);return}
    if(asset.category!=='image'){setUrl(null);return}
    let active=true;let objectUrl=''
    apiBlob(asset.content_url,workspaceId,true).then(blob=>{if(!active)return;objectUrl=URL.createObjectURL(blob);setUrl(objectUrl)}).catch(()=>{if(active)setUrl(null)})
    return()=>{active=false;if(objectUrl)URL.revokeObjectURL(objectUrl)}
  },[asset.id,asset.content_url,asset.public_url,asset.category,workspaceId])
  if(url&&asset.category==='image')return <Image className={`media-thumb ${className}`} src={url} alt={asset.alt_text||asset.name}/>
  const Icon=asset.category==='video'?Film:asset.category==='audio'?FileAudio:asset.category==='document'?FileText:asset.category==='image'?ImageIcon:File
  return <div className={`media-thumb media-thumb--icon ${className}`} aria-label={asset.category}><Icon size={26}/><span>{asset.extension?.toUpperCase()||asset.category}</span></div>
}
