import { Languages } from 'lucide-react'
import { Select, Label, Option } from '../design-system'
import { useLocalization } from './LocalizationContext'
import { coreLocales } from './catalog'

/** Render the active-language selector using the shared WorkIntel styled select control. */
export default function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
  const { locale, setLocale, locales, t } = useLocalization()
  return <Label className={`wi-language-switcher${compact ? ' is-compact' : ''}`} title={t('common.language')}><Languages size={14}/><Select aria-label={t('common.language')} value={locale} onChange={event => void setLocale(event.target.value as typeof locale)}>{locales.filter(item => coreLocales.includes(item.code)).map(item => <Option key={item.code} value={item.code}>{item.label}</Option>)}</Select></Label>
}
