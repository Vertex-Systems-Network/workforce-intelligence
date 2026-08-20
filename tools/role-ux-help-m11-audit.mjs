import fs from 'node:fs'
/** Read one repository source file for dependency-free M11 verification. */
const read=file=>fs.readFileSync(file,'utf8'),failures=[]
const localeHelp=read('resources/js/i18n/locales/help.ts')
const shell=read('resources/js/WorkforceApp.tsx'),context=read('resources/js/components/ShellContextBar.tsx'),help=read('resources/js/components/HelpCenter.tsx'),start=read('resources/js/components/RoleStartHere.tsx'),firstRun=read('resources/js/components/FirstRunGuide.tsx'),catalog=read('resources/js/help/roleHelpCatalog.ts'),locale=read('resources/js/i18n/catalog.ts')+'\n'+localeHelp,experience=read('resources/js/help/roleExperience.ts'),prefs=read('app/Http/Controllers/Api/V1/UserPagePreferenceController.php'),moduleHome=read('resources/js/components/ModuleHome.tsx'),overview=read('resources/js/pages/Overview.tsx'),design=read('resources/js/design-system/index.tsx'),css=read('resources/js/design-system/toolkit.css'),e2e=read('tests/e2e/authenticated-platform.spec.mjs')
/** Record one missing M11 source marker without aborting the remaining checks. */
const need=(src,token,label)=>{if(!src.includes(token))failures.push(`${label}: ${token}`)}
for(const token of ['HelpCenter','helpOpen','F1','workintel:open-help','FirstRunGuide'])need(shell,token,'global help shell')
if(!/e\.key\s*===\s*['"]\/['"]/.test(shell))failures.push("global help shell: slash shortcut")
for(const token of ['onOpenHelp',"t('help.help')","t('help.find_anything')"])need(context,token,'context bar')
for(const token of ["t('help.this_page')","t('help.start_here')","t('help.role_handbook')","t('help.search_aria')","workintel:open-help"])need(help,token,'help center')
for(const token of ['roleGuideForWorkspace','canAccessPage','isPageVisibleInNavigation','inferredGuideKey'])need(catalog,token,'role guide')
for(const token of ['role-help-v1','onboarding_completed','workintel:role-experience-changed','toggleTask','reset'])need(experience,token,'progress persistence')
for(const token of ['settings.onboarding_completed','settings.help_dismissed','settings.checklist_version'])need(prefs,token,'preference validation')
for(const token of ['help.center','help.welcome_title','help.module_step_one','help.open_context'])need(locale,token,'M11 localization')
for(const language of ['Yardım Merkezi','Центр помощи','مدد مرکز','مركز المساعدة'])need(locale,language,'core locale copy')
for(const token of ['help.welcome_title','workintel:open-help','first-run'])need(firstRun,token,'first run')
need(design,'contextualHelp','shared empty-state help')
for(const token of ['ui-first-run-guide','[dir="rtl"] .ui-help-directional-icon','prefers-reduced-motion:reduce'])need(css,token,'RTL/mobile accessibility')
for(const token of ['M11 Help Center keyboard and RTL flow','F1','getByRole(\'dialog\''])need(e2e,token,'browser certification')
need(moduleHome,'RoleStartHere','module home start-here')
need(overview,'RoleStartHere','overview start-here')
if(help.includes('window.prompt(')||start.includes('window.prompt(')||firstRun.includes('window.prompt('))failures.push('M11 surfaces must not use browser-native prompts.')
if(!fs.existsSync('docs/manuals/M11_OWNER_ADMIN_ROLE_MANUAL.md')||!fs.existsSync('docs/manuals/M11_MANAGER_TEAM_LEAD_ROLE_MANUAL.md')||!fs.existsSync('docs/manuals/M11_HR_ROLE_MANUAL.md')||!fs.existsSync('docs/manuals/M11_PAYROLL_ROLE_MANUAL.md')||!fs.existsSync('docs/manuals/M11_EMPLOYEE_ROLE_MANUAL.md'))failures.push('Role manual set incomplete.')
if(failures.length){for(const failure of failures)console.error('FAIL:',failure);process.exit(1)}
console.log('M11 Role UX + Help + Onboarding audit: PASS')
