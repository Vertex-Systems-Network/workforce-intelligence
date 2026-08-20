import { ImagePlus, Paperclip } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Button, Field, Inline } from '../design-system'
import { MediaPicker } from './MediaPicker'
import { mediaAssetToFile } from './upload'
import type { MediaAsset } from './types'

type MediaFileFieldProps={workspaceId:number;label?:ReactNode;hint?:ReactNode;accept?:string;multiple?:boolean;disabled?:boolean;onFiles:(files:File[],source:{kind:'upload'|'media';asset?:MediaAsset})=>Promise<void>|void;valueLabel?:string;imagesOnly?:boolean;compact?:boolean;maxFiles?:number}

/** Offer both Media Library selection and direct upload anywhere a workflow needs a file. */
export function MediaFileField({workspaceId,label='File',hint,accept,multiple=false,disabled=false,onFiles,valueLabel,imagesOnly=false,compact=false,maxFiles=20}:MediaFileFieldProps){
  const [picker,setPicker]=useState(false),[busy,setBusy]=useState(false),[selectedName,setSelectedName]=useState(valueLabel??'')
  useEffect(()=>setSelectedName(valueLabel??''),[valueLabel])
  /** Convert one or more existing Media Library assets to browser Files for current workflow upload contracts. */
  const libraryMany=async(assets:MediaAsset[])=>{if(!assets.length)return;setBusy(true);try{const rows=await Promise.all(assets.slice(0,Math.max(1,maxFiles)).map(asset=>mediaAssetToFile(asset,workspaceId)));await onFiles(rows,{kind:'media',asset:assets[0]});setSelectedName(rows.length>1?`${rows.length} files selected`:assets[0].name)}finally{setBusy(false);setPicker(false)}}
  /** Convert one existing Media Library asset to the common workflow contract. */
  const library=async(asset:MediaAsset)=>libraryMany([asset])
  const controls=<div className={`media-file-field${compact?' is-compact':''}`}><Inline><Button type="button" size="sm" variant="outline" disabled={disabled||busy} loading={busy} onClick={()=>setPicker(true)}>{imagesOnly?<ImagePlus size={13}/>:<Paperclip size={13}/>} Choose {imagesOnly?'image':'file'}</Button></Inline>{selectedName&&<span className="media-file-field__value">{selectedName}</span>}<MediaPicker open={picker} workspaceId={workspaceId} onClose={()=>setPicker(false)} onSelect={asset=>void library(asset)} onSelectMany={assets=>void libraryMany(assets)} multiple={multiple} maxFiles={maxFiles} imagesOnly={imagesOnly} accept={accept} title={`Choose ${typeof label==='string'?label.toLowerCase():'file'}`} /></div>
  return compact?controls:<Field label={label} hint={hint}>{controls}</Field>
}
