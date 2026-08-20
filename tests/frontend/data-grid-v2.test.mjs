import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root=path.resolve(import.meta.dirname,'../..')
/** Read one WorkIntel source file for dependency-free DataGrid V2 contracts. */
function read(relative){return fs.readFileSync(path.join(root,relative),'utf8')}

test('DataGrid V2 uses TanStack stable React table primitives',()=>{
  const pkg=JSON.parse(read('package.json'))
  const ui=read('resources/js/design-system/index.tsx')
  assert.equal(pkg.dependencies['@tanstack/react-table'],'^8.21.3')
  for(const token of ['useReactTable','getCoreRowModel','getFilteredRowModel','getSortedRowModel','getPaginationRowModel','flexRender'])assert.ok(ui.includes(token),token)
})

test('DataGrid V2 exposes filtering pagination visibility selection refresh and saved views',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  for(const token of ['ColumnFiltersState','RowSelectionState','VisibilityState',"t('common.saved_views')","t('common.rows_per_page')","t('common.reset_table')",'onRefresh','bulkActions','manualPagination','manualSorting','manualFiltering','dataGridQueryParams'])assert.ok(ui.includes(token),token)
  assert.ok(ui.includes("dateRange"))
  assert.ok(ui.includes('grid.${persistKey'))
})

test('high-use screens are migrated to shared DataGrid V2',()=>{
  for(const file of ['People.tsx','Clients.tsx','Projects.tsx','Tasks.tsx']){
    const source=read(`resources/js/pages/${file}`)
    assert.ok(source.includes('DataGrid'),file)
    assert.ok(source.includes('persistKey='),file)
  }
})

test('grid preferences are workspace and user persisted by the existing preference API',()=>{
  const controller=read('app/Http/Controllers/Api/V1/UserPagePreferenceController.php')
  for(const token of ['settings.data_grid','settings.data_grid.sorting','settings.data_grid.filters','settings.data_grid.visibility','settings.data_grid.savedViews'])assert.ok(controller.includes(token),token)
})


test('server-side query helper whitelists sort and filter identifiers',()=>{
  const helper=read('app/Support/DataGridRequest.php')
  for(const token of ['sortKeys','filterKeys','applySearch','applySorting','dateRange','last_page'])assert.ok(helper.includes(token),token)
  assert.ok(helper.includes("in_array($id, $sortKeys, true)"))
})
