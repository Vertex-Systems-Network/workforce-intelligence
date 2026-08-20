import type { ReactNode } from 'react'
import { ArrowLeft, Gauge } from 'lucide-react'
import { Pressable } from '../../design-system'

/** Render the compact product identity shown when the desktop auth showcase is hidden. */
export function AuthMobileBrand({productName='WorkIntel'}:{productName?:string}){
  return <div className="auth-mobile-brand"><span><Gauge size={17}/></span>{productName}</div>
}

/** Render one consistent authentication heading hierarchy across login, registration and recovery flows. */
export function AuthHeading({kicker,title,description}:{kicker:ReactNode;title:ReactNode;description?:ReactNode}){
  return <div className="auth-heading"><div className="auth-heading__kicker">{kicker}</div><h2>{title}</h2>{description&&<p>{description}</p>}</div>
}

/** Render the shared auth back action with the same hit target, icon and visual priority. */
export function AuthBackButton({onClick,label='Back to sign in'}:{onClick:()=>void;label?:ReactNode}){
  return <Pressable className="auth-back" type="button" onClick={onClick}><ArrowLeft size={14}/>{label}</Pressable>
}
