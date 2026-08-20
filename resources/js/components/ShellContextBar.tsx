import { ChevronRight, CircleHelp, Search, Star } from 'lucide-react'
import type { Page } from './Sidebar'
import { Button, Inline, Pressable, Stack, Text, Tooltip } from '../design-system'
import { pageTranslationKey } from '../navigation'
import { localizedPageDescription, pageShell, workspaceModule, workspaceModuleForPage, type WorkspaceModuleId } from '../moduleCatalog'
import { useLocalization } from '../i18n/LocalizationContext'

/** Keep module ownership, purpose, favorites and discovery visible across inconsistent legacy page headers. */
export default function ShellContextBar({page,activeModule,onOpenCommand,onOpenHelp,onOpenModule,isFavorite=false,onToggleFavorite}:{page:Page;activeModule?:WorkspaceModuleId|null;onOpenCommand:()=>void;onOpenHelp:()=>void;onOpenModule?:(id:WorkspaceModuleId)=>void;isFavorite?:boolean;onToggleFavorite?:()=>void}){
  const {t,text}=useLocalization()
  const module=activeModule?workspaceModule(activeModule):workspaceModuleForPage(page)
  const meta=pageShell[page]
  const areaLabel=module?text(module.label):text('Account & Support')
  const pageLabel=activeModule?text('Module home'):t(pageTranslationKey[page])
  const description=activeModule&&module?text(module.description):localizedPageDescription(page,t,text)
  return <section className="ui-shell-context" aria-label={`${areaLabel}: ${pageLabel}`}>
    <Stack gap={3} minWidth={0}>
      <Inline gap={6} align="center" className="ui-shell-context__crumbs">
        {module&&onOpenModule?<Pressable type="button" className="ui-shell-context__module-link" onClick={()=>onOpenModule(module.id)}>{areaLabel}</Pressable>:<Text size={11} color="var(--text-3)">{areaLabel}</Text>}<ChevronRight size={11} aria-hidden="true"/><Text size={11} weight={650}>{pageLabel}</Text>
      </Inline>
      <Text size={12} color="var(--text-2)" className="ui-shell-context__description">{description}</Text>
    </Stack>
    <Inline gap={4} align="center" className="ui-shell-context__actions">
      {!activeModule&&onToggleFavorite&&<Tooltip content={isFavorite?'Remove from favorites':'Add to favorites'}><Button variant="ghost" size="sm" aria-pressed={isFavorite} onClick={onToggleFavorite}><Star size={14} fill={isFavorite?'currentColor':'none'}/><span className="ui-shell-context__favorite-label">{isFavorite?'Favorited':'Favorite'}</span></Button></Tooltip>}
      <Button variant="ghost" size="sm" className="ui-shell-context__find" onClick={onOpenCommand}><Search size={14}/><span className="ui-shell-context__action-label">{t('help.find_anything')}</span></Button><Button variant="ghost" size="sm" className="ui-shell-context__help" onClick={onOpenHelp}><CircleHelp size={14}/><span className="ui-shell-context__action-label">{t('help.help')}</span></Button>
    </Inline>
  </section>
}
