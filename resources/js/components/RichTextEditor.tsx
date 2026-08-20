import { useEffect, type ReactNode } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import Underline from '@tiptap/extension-underline'
import { Bold, Code2, Heading2, Italic, Link2, List, ListOrdered, Quote, Redo2, Strikethrough, Underline as UnderlineIcon, Undo2 } from 'lucide-react'
import { Button } from '../design-system'
import './rich-text-editor.css'

type Props = { value:string; onChange:(html:string)=>void; disabled?:boolean; placeholder?:string }

/** Handles the rich text editor operation for the WorkIntel client. */ export default function RichTextEditor({value,onChange,disabled=false,placeholder='Describe the work, acceptance criteria, links, and handoff notes…'}:Props){
  const editor=useEditor({
    extensions:[
      StarterKit,
      Underline,
      Link.configure({openOnClick:false,autolink:true,defaultProtocol:'https'}),
      Placeholder.configure({placeholder}),
    ],
    content:value || '',
    editable:!disabled,
    immediatelyRender:false,
    onUpdate:({editor})=>onChange(editor.isEmpty?'':editor.getHTML()),
  })

  useEffect(()=>{ if(editor && editor.getHTML() !== (value || '<p></p>')) editor.commands.setContent(value || '', {emitUpdate:false}) },[value,editor])
  useEffect(()=>{ editor?.setEditable(!disabled) },[disabled,editor])
  if(!editor) return <div className="task-rich-editor task-rich-editor--loading">Loading editor…</div>

  /** Updates set link state for the current workflow. */ const setLink=()=>{
    const previous=editor.getAttributes('link').href as string|undefined
    const href=window.prompt('Link URL',previous || 'https://')
    if(href===null)return
    if(!href.trim()){editor.chain().focus().unsetLink().run();return}
    editor.chain().focus().extendMarkRange('link').setLink({href:href.trim()}).run()
  }
  /** Handles the tool operation for the WorkIntel client. */ const tool=(title:string,active:boolean,onClick:()=>void,icon:ReactNode)=><Button type="button" size="sm" variant={active?'secondary':'ghost'} iconOnly aria-label={title} title={title} onClick={onClick}>{icon}</Button>

  return <div className={`task-rich-editor${disabled?' is-disabled':''}`}>
    {!disabled&&<div className="task-rich-editor__toolbar">
      {tool('Undo',false,()=>editor.chain().focus().undo().run(),<Undo2 size={14}/>)}
      {tool('Redo',false,()=>editor.chain().focus().redo().run(),<Redo2 size={14}/>)}
      <span className="task-rich-editor__separator"/>
      {tool('Bold',editor.isActive('bold'),()=>editor.chain().focus().toggleBold().run(),<Bold size={14}/>)}
      {tool('Italic',editor.isActive('italic'),()=>editor.chain().focus().toggleItalic().run(),<Italic size={14}/>)}
      {tool('Underline',editor.isActive('underline'),()=>editor.chain().focus().toggleUnderline().run(),<UnderlineIcon size={14}/>)}
      {tool('Strike',editor.isActive('strike'),()=>editor.chain().focus().toggleStrike().run(),<Strikethrough size={14}/>)}
      {tool('Heading',editor.isActive('heading',{level:2}),()=>editor.chain().focus().toggleHeading({level:2}).run(),<Heading2 size={14}/>)}
      {tool('Bullet list',editor.isActive('bulletList'),()=>editor.chain().focus().toggleBulletList().run(),<List size={14}/>)}
      {tool('Ordered list',editor.isActive('orderedList'),()=>editor.chain().focus().toggleOrderedList().run(),<ListOrdered size={14}/>)}
      {tool('Quote',editor.isActive('blockquote'),()=>editor.chain().focus().toggleBlockquote().run(),<Quote size={14}/>)}
      {tool('Code block',editor.isActive('codeBlock'),()=>editor.chain().focus().toggleCodeBlock().run(),<Code2 size={14}/>)}
      {tool('Link',editor.isActive('link'),setLink,<Link2 size={14}/>)}
    </div>}
    <EditorContent editor={editor}/>
  </div>
}
