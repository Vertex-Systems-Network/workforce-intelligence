import { Ellipsis, RotateCcw, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { useConfirmAction, Badge, Button, DataGrid, type DataGridColumn, Dropdown, EmptyState, Page, PageHeader } from '../design-system'

type TrashItem={type:string;type_label:string;id:number;name:string;description?:string|null;deleted_at?:string|null;can_restore:boolean}
type Response={data:TrashItem[];types:{key:string;label:string}[]}

/** Renders recoverable workspace data separately from immutable financial and audit ledgers. */
export default function TrashCenter(){
 const confirmAction=useConfirmAction()
 const {session}=useAuth(),workspaceId=session?.user.activeWorkspaceId??0
 const [response,setResponse]=useState<Response>({data:[],types:[]}),[loading,setLoading]=useState(true)
 /** Loads all supported trashed records allowed by the current role. */
 const load=async()=>{if(!workspaceId)return;setLoading(true);try{setResponse(await apiRequest<Response>('/api/v1/trash',{workspaceId,silent:true}))}finally{setLoading(false)}}
 useEffect(()=>{void load()},[workspaceId])
 /** Restores one trashed record after backend dependency and entitlement checks. */
 const restore=async(item:TrashItem)=>{await apiRequest(`/api/v1/trash/${item.type}/${item.id}/restore`,{method:'POST',workspaceId});await load()}
 /** Permanently deletes one trashed record only after explicit irreversible confirmation. */
 const purge=async(item:TrashItem)=>{if(!await confirmAction({title:'Permanently delete item?',description:`Permanently delete “${item.name}”? This action cannot be undone.`,confirmLabel:'Delete permanently',danger:true}))return;await apiRequest(`/api/v1/trash/${item.type}/${item.id}`,{method:'DELETE',workspaceId});await load()}
 const columns=useMemo<DataGridColumn<TrashItem>[]>(()=>[
  {id:'name',header:'Record',sortable:true,searchValue:item=>`${item.name} ${item.description??''}`,sortValue:item=>item.name,cell:item=><div><strong>{item.name}</strong>{item.description&&<div className="ui-card-description">{item.description}</div>}</div>},
  {id:'type',header:'Type',filter:{type:'select',label:'Type',options:response.types.map(type=>({value:type.key,label:type.label}))},filterValue:item=>item.type,cell:item=><Badge tone="neutral">{item.type_label}</Badge>},
  {id:'deleted',header:'Deleted',sortable:true,sortValue:item=>item.deleted_at??'',filter:{type:'dateRange',label:'Deleted date'},filterValue:item=>item.deleted_at??'',cell:item=>item.deleted_at?new Date(item.deleted_at).toLocaleString():'—'},
  {id:'actions',header:'',hideable:false,cell:item=><Dropdown trigger={<Button variant="ghost" size="sm" iconOnly aria-label={`Actions for ${item.name}`}><Ellipsis size={15}/></Button>} items={[{label:'Restore',icon:<RotateCcw size={14}/>,onClick:()=>void restore(item)},{separator:true},{label:'Delete permanently',icon:<Trash2 size={14}/>,danger:true,onClick:()=>void purge(item)}]}/>},
 ],[response.types])
 return <Page><PageHeader title="Trash Center" description="Recoverable workspace data. Payroll, payment, audit and other immutable ledgers are intentionally never placed here."/><DataGrid rows={response.data} columns={columns} rowKey={item=>`${item.type}-${item.id}`} loading={loading} persistKey="trash-center" defaultSort={{id:'deleted',direction:'desc'}} empty={<EmptyState icon={<Trash2 size={30}/>} title="Trash is empty" text="Items moved to Trash from supported screens will remain recoverable here until permanently deleted."/>}/></Page>
}
