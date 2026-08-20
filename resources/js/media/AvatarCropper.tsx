import { useEffect, useRef, useState } from 'react'
import { Button, Field, Input, Modal } from '../design-system'

/** Provides dependency-free square avatar cropping with drag positioning and zoom. */
export function AvatarCropper({file,onCancel,onConfirm}:{file:File|null;onCancel:()=>void;onConfirm:(blob:Blob)=>Promise<void>|void}){
  const canvasRef=useRef<HTMLCanvasElement|null>(null)
  const imageRef=useRef<HTMLImageElement|null>(null)
  const [zoom,setZoom]=useState(1),[offset,setOffset]=useState({x:0,y:0}),[drag,setDrag]=useState<{x:number;y:number;ox:number;oy:number}|null>(null),[busy,setBusy]=useState(false)
  /** Draws the current crop state into the square output canvas. */
  const draw=()=>{const canvas=canvasRef.current,img=imageRef.current;if(!canvas||!img)return;const size=canvas.width,base=Math.max(size/img.naturalWidth,size/img.naturalHeight),scale=base*zoom,w=img.naturalWidth*scale,h=img.naturalHeight*scale,maxX=Math.max(0,(w-size)/2),maxY=Math.max(0,(h-size)/2),x=Math.max(-maxX,Math.min(maxX,offset.x)),y=Math.max(-maxY,Math.min(maxY,offset.y)),ctx=canvas.getContext('2d');if(!ctx)return;ctx.clearRect(0,0,size,size);ctx.drawImage(img,(size-w)/2+x,(size-h)/2+y,w,h)}
  useEffect(()=>{if(!file)return;const url=URL.createObjectURL(file),img=new Image();img.onload=()=>{imageRef.current=img;setZoom(1);setOffset({x:0,y:0});requestAnimationFrame(draw)};img.src=url;return()=>URL.revokeObjectURL(url)},[file])
  useEffect(()=>{draw()},[zoom,offset])
  /** Finalizes the canvas as a compressed PNG avatar blob. */
  const confirm=async()=>{const canvas=canvasRef.current;if(!canvas)return;setBusy(true);try{const blob=await new Promise<Blob>((resolve,reject)=>canvas.toBlob(value=>value?resolve(value):reject(new Error('Avatar crop failed.')),'image/png',0.92));await onConfirm(blob)}finally{setBusy(false)}}
  return <Modal open={Boolean(file)} onClose={()=>!busy&&onCancel()} title="Crop profile photo" description="Drag the image to position it, then adjust the zoom." size="md" footer={<><Button variant="outline" onClick={onCancel} disabled={busy}>Cancel</Button><Button variant="primary" onClick={()=>void confirm()} loading={busy}>Use photo</Button></>}>
    <div className="avatar-cropper"><canvas ref={canvasRef} width={420} height={420} onPointerDown={event=>setDrag({x:event.clientX,y:event.clientY,ox:offset.x,oy:offset.y})} onPointerMove={event=>{if(!drag)return;setOffset({x:drag.ox+(event.clientX-drag.x),y:drag.oy+(event.clientY-drag.y)})}} onPointerUp={()=>setDrag(null)} onPointerLeave={()=>setDrag(null)}/><Field label="Zoom"><Input type="range" min="1" max="3" step="0.01" value={zoom} onChange={event=>setZoom(Number(event.target.value))}/></Field></div>
  </Modal>
}
