import { translatePageCopy } from './pageCopy'
import { enCore, trCore, ruCore, urCore, arCore } from './locales/core'
import { enWorkforce, trWorkforce, ruWorkforce, urWorkforce, arWorkforce } from './locales/workforce'
import { enBusiness, trBusiness, ruBusiness, urBusiness, arBusiness } from './locales/business'
import { enStudios, trStudios, ruStudios, urStudios, arStudios } from './locales/studios'
import { enCollaboration, trCollaboration, ruCollaboration, urCollaboration, arCollaboration } from './locales/collaboration'
import { enHelp, trHelp, ruHelp, urHelp, arHelp } from './locales/help'
export const localeOptions = [
  {code:'en',label:'English',dir:'ltr',intl:'en-US'},
  {code:'tr',label:'Türkçe',dir:'ltr',intl:'tr-TR'},
  {code:'ru',label:'Русский',dir:'ltr',intl:'ru-RU'},
  {code:'ur',label:'اردو',dir:'rtl',intl:'ur-PK'},
  {code:'ar',label:'العربية',dir:'rtl',intl:'ar'},
  {code:'de',label:'Deutsch',dir:'ltr',intl:'de-DE'},
  {code:'fr',label:'Français',dir:'ltr',intl:'fr-FR'},
  {code:'es',label:'Español',dir:'ltr',intl:'es-ES'},
  {code:'it',label:'Italiano',dir:'ltr',intl:'it-IT'},
  {code:'pt',label:'Português',dir:'ltr',intl:'pt-PT'},
] as const
export type LocaleCode = typeof localeOptions[number]['code']
export const coreLocales:LocaleCode[]=['en','tr','ru','ur','ar']
export const localeMap=Object.fromEntries(localeOptions.map(item=>[item.code,item])) as Record<LocaleCode,typeof localeOptions[number]>
/** Handles the normalize locale operation for the WorkIntel client. */ export function normalizeLocale(value?:string|null):LocaleCode{const code=(value||'en').toLowerCase().replace('_','-').split('-')[0] as LocaleCode;return localeMap[code]?code:'en'}


const en={...enCore,...enWorkforce,...enBusiness,...enStudios,...enCollaboration,...enHelp}
export type TranslationKey=keyof typeof en
const tr:Partial<Record<TranslationKey,string>>={...trCore,...trWorkforce,...trBusiness,...trStudios,...trCollaboration,...trHelp}
const ru:Partial<Record<TranslationKey,string>>={...ruCore,...ruWorkforce,...ruBusiness,...ruStudios,...ruCollaboration,...ruHelp}
const ur:Partial<Record<TranslationKey,string>>={...urCore,...urWorkforce,...urBusiness,...urStudios,...urCollaboration,...urHelp}
const ar:Partial<Record<TranslationKey,string>>={...arCore,...arWorkforce,...arBusiness,...arStudios,...arCollaboration,...arHelp}

export const dictionaries:Record<string,Partial<Record<TranslationKey,string>>>={en,tr,ru,ur,ar}
const englishTextKeys=new Map<string,TranslationKey>(Object.entries(en).map(([key,value])=>[value,key as TranslationKey]))
/** Resolve an exact English UI phrase to its canonical translation key when the phrase is registered. */ export function translationKeyForEnglishText(value:string):TranslationKey|undefined{return englishTextKeys.get(value.trim())}
/** Translate a known English UI phrase while leaving dynamic business data unchanged. */ export function translateKnownText(locale:LocaleCode,value:string):string{const key=translationKeyForEnglishText(value);return key?translate(locale,key):translatePageCopy(locale,value)}
/** Handles the translate operation for the WorkIntel client. */ export function translate(locale:LocaleCode,key:TranslationKey,vars:Record<string,string|number>={}):string{const raw=dictionaries[locale]?.[key]??en[key]??key;return Object.entries(vars).reduce((text,[name,value])=>text.replaceAll(`{{${name}}}`,String(value)),raw)}
