import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')

/** Read one logical frontend source surface, including focused modules extracted from large page barrels. */
export function readSource(relativePath) {
  /** Read one physical source file from the project root. */
  const read = file => fs.readFileSync(path.join(root, file), 'utf8')
  const source = read(relativePath)
  if (relativePath === 'resources/js/i18n/pageCopy.ts') {
    const domains = ['core', 'workforce', 'business', 'studios', 'collaboration', 'help']
    return source + '\n' + read('resources/js/i18n/page-copy/core-phrases.ts') + '\n' + domains.map(domain => read(`resources/js/i18n/page-copy/${domain}.ts`)).join('\n')
  }
  if (relativePath === 'resources/js/i18n/catalog.ts') {
    const domains = ['core', 'workforce', 'business', 'studios', 'collaboration', 'help']
    return source + '\n' + domains.map(domain => read(`resources/js/i18n/locales/${domain}.ts`)).join('\n')
  }
  if (relativePath === 'resources/js/pages/Chat.tsx') {
    return source + '\n' + read('resources/js/components/chat/ChatPanels.tsx') + '\n' + read('resources/js/components/chat/chatUtils.ts') + '\n' + read('resources/js/components/chat/chatTypes.ts')
  }
  if (relativePath === 'resources/js/pages/Documents.tsx') {
    return source + '\n' + read('resources/js/documents/studio/DocumentStudioSupport.tsx')
  }
  if (relativePath === 'resources/js/pages/WebsiteStudio.tsx') {
    return source + '\n' + read('resources/js/website/studio/WebsiteStudioSupport.tsx')
  }

  if (relativePath === 'resources/js/pages/Tasks.tsx') {
    return source + '\n' + read('resources/js/pages/tasks/support.tsx') + '\n' + read('resources/js/pages/tasks/WorkflowManager.tsx')
  }
  if (relativePath === 'resources/js/pages/Reports.tsx') {
    return source + '\n' + read('resources/js/pages/reports/support.ts')
  }
  if (relativePath === 'resources/js/pages/Clients.tsx') {
    return source + '\n' + read('resources/js/pages/clients/support.ts')
  }
  if (relativePath === 'resources/js/pages/Payroll.tsx') {
    return source + '\n' + read('resources/js/pages/payroll/support.ts')
  }
  if (relativePath === 'resources/js/pages/Automations.tsx') {
    return source + '\n' + read('resources/js/pages/automations/support.ts') + '\n' + read('resources/js/pages/automations/ActionEditor.tsx')
  }
  if (relativePath === 'resources/js/pages/Enterprise.tsx') {
    return source + '\n' + read('resources/js/pages/enterprise/support.ts')
  }
  if (relativePath === 'resources/js/design-system/index.tsx') {
    return source + '\n' + read('resources/js/design-system/layout.tsx')
  }
  return source
}
