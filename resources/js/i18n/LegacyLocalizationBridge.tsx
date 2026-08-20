import { useEffect } from 'react'
import { useLocalization } from './LocalizationContext'

type TextState={source:string;last:string}
type AttrState={source:string;last:string}
type Translator=(value:string)=>string
const textState=new WeakMap<Text,TextState>()
const attrState=new WeakMap<Element,Map<string,AttrState>>()
const translatedAttributes=['placeholder','title','aria-label'] as const

/** Return true for areas where literal text is business/user content rather than product UI copy. */
function shouldSkip(element:Element|null):boolean{
  return Boolean(element?.closest('[data-no-auto-i18n="true"], [data-business-value="true"], [contenteditable="true"], pre, code, script, style'))
}

/** Translate one text node while remembering its original English source across locale switches. */
function translateTextNode(node:Text,translate:Translator){
  if(shouldSkip(node.parentElement))return
  const current=node.nodeValue??''
  if(!/[A-Za-z]/.test(current))return
  let state=textState.get(node)
  if(!state||current!==state.last){state={source:current,last:current};textState.set(node,state)}
  const next=translate(state.source)
  if(next!==current){state.last=next;node.nodeValue=next}else state.last=current
}

/** Translate supported static element attributes without altering field values or submitted data. */
function translateAttributes(element:Element,translate:Translator){
  if(shouldSkip(element))return
  let states=attrState.get(element);if(!states){states=new Map();attrState.set(element,states)}
  for(const name of translatedAttributes){
    if(!element.hasAttribute(name))continue
    const current=element.getAttribute(name)??''
    if(!/[A-Za-z]/.test(current))continue
    let state=states.get(name)
    if(!state||current!==state.last){state={source:current,last:current};states.set(name,state)}
    const next=translate(state.source)
    if(next!==current){state.last=next;element.setAttribute(name,next)}else state.last=current
  }
}

/** Translate static legacy UI copy added by React while preserving dynamic workspace data. */
function scan(root:Node,translate:Translator){
  if(root.nodeType===Node.TEXT_NODE){translateTextNode(root as Text,translate);return}
  if(root.nodeType!==Node.ELEMENT_NODE&&root.nodeType!==Node.DOCUMENT_FRAGMENT_NODE)return
  if(root.nodeType===Node.ELEMENT_NODE)translateAttributes(root as Element,translate)
  const walker=document.createTreeWalker(root,NodeFilter.SHOW_ELEMENT|NodeFilter.SHOW_TEXT)
  let node=walker.nextNode();while(node){if(node.nodeType===Node.TEXT_NODE)translateTextNode(node as Text,translate);else translateAttributes(node as Element,translate);node=walker.nextNode()}
}

/**
 * Localize known static copy emitted by legacy deep-module pages without translating business data.
 * New code should continue using typed `t()` keys; this bridge exists only to finish legacy page coverage safely.
 */
export default function LegacyLocalizationBridge(){
  const {locale,text}=useLocalization()
  useEffect(()=>{
    const root=document.getElementById('root');if(!root)return
    scan(root,text)
    const observer=new MutationObserver(records=>{for(const record of records){if(record.type==='characterData')scan(record.target,text);for(const node of record.addedNodes)scan(node,text)}})
    observer.observe(root,{subtree:true,childList:true,characterData:true,attributes:false})
    return()=>observer.disconnect()
  },[locale,text])
  return null
}
