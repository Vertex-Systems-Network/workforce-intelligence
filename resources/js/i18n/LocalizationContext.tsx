import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useAuth } from '../auth/AuthContext'
import { apiRequest } from '../api/client'
import { localeMap, localeOptions, normalizeLocale, translate, translateKnownText, type LocaleCode, type TranslationKey } from './catalog'

type LocalizationValue={locale:LocaleCode;dir:'ltr'|'rtl';intlLocale:string;t:(key:TranslationKey,vars?:Record<string,string|number>)=>string;text:(value:string)=>string;setLocale:(locale:LocaleCode,persist?:boolean)=>Promise<void>;formatDate:(value:string|Date|number,options?:Intl.DateTimeFormatOptions)=>string;formatTime:(value:string|Date|number,options?:Intl.DateTimeFormatOptions)=>string;formatNumber:(value:number,options?:Intl.NumberFormatOptions)=>string;formatCurrency:(value:number,currency?:string)=>string;locales:typeof localeOptions}
const Context=createContext<LocalizationValue|null>(null)
const STORAGE_PREFIX='workintel-language'

/** Return the per-user storage key so one account never inherits another account's personal language. */
function storageKey(userId?:number|null){return `${STORAGE_PREFIX}:${userId??'guest'}`}
/** Resolve a browser locale for unauthenticated screens while preserving a saved guest preference. */
function detected(key:string):LocaleCode{const saved=window.localStorage.getItem(key);if(saved)return normalizeLocale(saved);return normalizeLocale(navigator.language)}

/** Provide locale, formatting and persistence without rebuilding or mutating navigation definitions. */
export function LocalizationProvider({children}:{children:ReactNode}){
 const {session}=useAuth()
 const workspace=session?.user.workspaces.find(w=>w.id===session.user.activeWorkspaceId)??session?.user.workspaces[0]
 const key=storageKey(session?.user.id)
 /** Resolve either the workspace default or a user's explicitly persisted personal override. */
 const resolveEffective=():LocaleCode=>{
   if(!session)return detected(key)
   if(session.user.useWorkspaceLocale)return normalizeLocale(workspace?.settings?.defaultLanguage)
   const stored=window.localStorage.getItem(key)
   return normalizeLocale(stored||session.user.locale)
 }
 const effective=resolveEffective()
 const [locale,setLocaleState]=useState<LocaleCode>(effective)

 useEffect(()=>{setLocaleState(resolveEffective())},[session?.user.id,session?.user.activeWorkspaceId,session?.user.useWorkspaceLocale,session?.user.locale,workspace?.settings?.defaultLanguage])
 useEffect(()=>{const meta=localeMap[locale];document.documentElement.lang=locale;document.documentElement.dir=meta.dir;document.documentElement.dataset.locale=locale;document.body.dir=meta.dir;window.localStorage.setItem(key,locale)},[locale,key])

 const value=useMemo<LocalizationValue>(()=>({
   locale,dir:localeMap[locale].dir,intlLocale:localeMap[locale].intl,locales:localeOptions,
   t:(translationKey,vars)=>translate(locale,translationKey,vars),text:(value)=>translateKnownText(locale,value),
   /** Persist a personal locale without refreshing the whole session or racing repeated language changes. */
   async setLocale(next,persist=true){
     const previous=locale
     setLocaleState(next);window.localStorage.setItem(key,next)
     if(!persist||!session)return
     try{await apiRequest('/api/v1/auth/locale',{method:'PUT',body:JSON.stringify({locale:next,use_workspace_locale:false}),silent:true})}
     catch(error){setLocaleState(previous);window.localStorage.setItem(key,previous);throw error}
   },
   /** Format dates using the user/workspace timezone and configured date pattern. */
   formatDate(value,options){const date=value instanceof Date?value:new Date(value);if(Number.isNaN(date.getTime()))return '—';const timezone=session?.user.timezone||workspace?.settings?.timezone||undefined;if(options)return new Intl.DateTimeFormat(localeMap[locale].intl,{...options,timeZone:options.timeZone||timezone}).format(date);const parts=new Intl.DateTimeFormat(localeMap[locale].intl,{year:'numeric',month:'2-digit',day:'2-digit',timeZone:timezone}).formatToParts(date);/** Return one localized date part used by the configured date pattern. */ const part=(type:string)=>parts.find(p=>p.type===type)?.value||'';const format=workspace?.settings?.dateFormat||'YYYY-MM-DD';return format.replace('YYYY',part('year')).replace('MM',part('month')).replace('DD',part('day'))},
   /** Format time using the workspace 12/24-hour setting and effective timezone. */
   formatTime(value,options){const date=value instanceof Date?value:new Date(value);if(Number.isNaN(date.getTime()))return '—';const timezone=session?.user.timezone||workspace?.settings?.timezone||undefined;return new Intl.DateTimeFormat(localeMap[locale].intl,{hour:'2-digit',minute:'2-digit',hour12:workspace?.settings?.timeFormat==='12h',timeZone:timezone,...options}).format(date)},
   /** Format a number for the active locale. */
   formatNumber(value,options){return new Intl.NumberFormat(localeMap[locale].intl,options).format(value)},
   /** Format monetary values with the workspace currency unless a currency is supplied. */
   formatCurrency(value,currency=workspace?.settings?.currency||'USD'){return new Intl.NumberFormat(localeMap[locale].intl,{style:'currency',currency}).format(value)},
 }),[locale,key,session?.user.id,session?.user.timezone,workspace?.id,workspace?.settings?.currency,workspace?.settings?.dateFormat,workspace?.settings?.timeFormat,workspace?.settings?.timezone])
 return <Context.Provider value={value}>{children}</Context.Provider>
}
/** Return localization when available without requiring public/marketing surfaces to mount the authenticated provider. */
export function useOptionalLocalization(){return useContext(Context)}
/** Return the active localization context. */
export function useLocalization(){const value=useContext(Context);if(!value)throw new Error('useLocalization must be used inside LocalizationProvider');return value}
