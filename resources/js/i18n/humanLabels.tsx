import { useLocalization } from './LocalizationContext'
import type { TranslationKey } from './catalog'

const LABEL_KEYS:Record<string,TranslationKey>={
 active:'label.active',inactive:'label.inactive',pending:'label.pending',approved:'label.approved',rejected:'label.rejected',draft:'label.draft',archived:'label.archived',completed:'label.completed',cancelled:'label.cancelled',canceled:'label.cancelled',office:'label.office',remote:'label.remote',hybrid:'label.hybrid',field:'label.field',public:'label.public',private:'label.private',owner:'label.owner',admin:'label.admin',administrator:'label.admin',manager:'label.manager',employee:'label.employee',
}

/** Convert technical identifiers into readable fallback text when no curated translation exists. */
export function humanizeIdentifier(value:string){return value.replace(/[._-]+/g,' ').replace(/\s+/g,' ').trim().replace(/\b\w/g,letter=>letter.toUpperCase())}
/** Resolve a technical enum/status value to a curated translation key when one exists. */
export function humanLabelKey(value:string):TranslationKey|undefined{return LABEL_KEYS[value.trim().toLowerCase()]}
/** Return a localized human-readable label for status, role, mode and similar enum values. */
export function useHumanLabel(){const {t}=useLocalization();return(value:string|null|undefined)=>{if(!value)return '—';const key=humanLabelKey(value);return key?t(key):humanizeIdentifier(value)}}
/** Render one localized technical value without exposing snake_case or dotted identifiers. */
export function HumanLabel({value}:{value:string|null|undefined}){const label=useHumanLabel();return <>{label(value)}</>}
