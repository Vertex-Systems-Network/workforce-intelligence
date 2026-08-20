/** Describes one editable Document Studio V6 block, including page containers and nested logic/layout data. */
export type DocumentBlock = {
  id:string
  type:string
  text?:string
  html?:string
  label?:string
  value?:string
  prefix?:string
  suffix?:string
  level?:number
  source?:string
  alias?:string
  expression?:string
  decimals?:number
  height?:number
  width?:number
  align?:'left'|'center'|'right'|'justify'|'start'
  margin_y?:number
  tone?:'neutral'|'info'|'success'|'warning'|'danger'
  color?:string
  alt?:string
  caption?:string
  media_asset_id?:number
  role?:string
  component_id?:number
  max_items?:number
  max_rows?:number
  show_header?:boolean
  condition?:{path?:string;operator?:string;value?:unknown}
  children?:DocumentBlock[]
  page_id?:string
  page_master_id?:number
  page_settings?:Record<string,any>
  header_settings?:Record<string,any>
  footer_settings?:Record<string,any>
  watermark_settings?:Record<string,any>
  items?:Array<{label:string;value:string}>
  columns?:Array<{label?:string;key?:string;align?:'left'|'center'|'right';width?:number;format?:'text'|'number'|'currency'|'date'|'percent';children?:DocumentBlock[]}>
  [key:string]:unknown
}


/** Describes one mutable V6 autosave that is separate from immutable template versions. */
export type DocumentTemplateDraft = {uuid:string;revision:number;content_schema:DocumentBlock[];settings:Record<string,any>;metadata:Record<string,any>;updated_at?:string|null;updated_by_member_id?:number|null}

/** Describes one server-side Document Studio V6 preflight finding. */
export type DocumentPreflightIssue = {severity:'error'|'warning';code:string;message:string;blockId?:string|null;pageId?:string|null}
export type DocumentPreflight = {issues:DocumentPreflightIssue[];errors:number;warnings:number;stats:{page_count:number;block_count:number;has_header:boolean;has_footer:boolean}}


/** Describes one reusable workspace brand kit for Document Studio V6. */
export type DocumentBrandKit = {id:number;uuid:string;name:string;primary_color:string;secondary_color:string;accent_color:string;font_family:string;heading_font_family:string;logo_media_asset_id?:number|null;settings?:Record<string,unknown>|null;is_default:boolean;updated_at?:string}

/** Describes one reusable page/header/footer/watermark master. */
export type DocumentPageMaster = {id:number;uuid:string;name:string;page_settings:Record<string,any>;header_settings?:Record<string,any>|null;footer_settings?:Record<string,any>|null;watermark_settings?:Record<string,any>|null;is_default:boolean;updated_at?:string}

/** Describes one persistent queued Document Studio batch-generation job. */
export type DocumentBatchJob = {id:number;uuid:string;client_request_id?:string|null;document_template_id:number;source_type?:string|null;status:'queued'|'running'|'completed'|'partial'|'failed';requested_count:number;processed_count:number;generated_count:number;failed_count:number;attempt_count?:number;results?:Array<Record<string,unknown>>|null;last_error?:string|null;started_at?:string|null;heartbeat_at?:string|null;completed_at?:string|null;created_at?:string;template?:{id:number;name:string}|null}

/** Describes advanced V6 resources loaded outside the base document overview. */
export type DocumentV6Resources = {brand_kits:DocumentBrandKit[];page_masters:DocumentPageMaster[];batch_jobs:DocumentBatchJob[]}

/** Describes one immutable Document Studio template version. */
export type DocumentVersion = {id:number;version:number;change_note?:string|null;created_at:string}

/** Describes one editable workspace document template. */
export type DocumentTemplate = {
  id:number;uuid:string;name:string;slug:string;document_type:string;language:string;status:string;is_default:boolean
  paper_size:'A4'|'Letter';orientation:'portrait'|'landscape';primary_color:string;secondary_color:string;font_family?:string|null
  content_schema:DocumentBlock[];settings:Record<string,any>|null;current_version:number;legal_entity_id:number|null
  legal_entity?:{id:number;name:string}|null;versions?:DocumentVersion[]
}

/** Describes one reusable block collection in Document Studio V4. */
export type DocumentComponent = {id:number;name:string;category?:string|null;content_schema:DocumentBlock[];settings?:Record<string,unknown>|null;version?:number;updated_at?:string}

/** Describes one secure public document share link without exposing its token hash. */
export type DocumentShare = {id:number;uuid:string;access_mode:'view'|'download';max_views?:number|null;view_count:number;expires_at?:string|null;last_viewed_at?:string|null;revoked_at?:string|null;created_at?:string}

/** Describes one internal or external electronic-signature request. */
export type DocumentSignature = {id:number;uuid:string;signer_member_id?:number|null;signer_name?:string|null;signer_email?:string|null;role_label?:string|null;status:string;signature_method?:string|null;typed_name?:string|null;expires_at?:string|null;signed_at?:string|null;declined_at?:string|null;created_at?:string}

/** Describes one immutable review/approval workflow event. */
export type DocumentReviewEvent = {id:number;event:string;note?:string|null;metadata?:Record<string,unknown>|null;created_at:string;actor_member_id?:number|null}

/** Describes one block- or document-scoped collaboration comment. */
export type DocumentComment = {id:number;block_id?:string|null;body:string;resolved_at?:string|null;created_at:string;author?:{id:number;user?:{first_name?:string;last_name?:string}|null}|null}

/** Describes one generated document and its V4 governance summary. */
export type GeneratedDocument = {
  id:number;uuid:string;document_type:string;filename:string;size_bytes:number;source_type?:string|null;source_id?:number|null
  language?:string;status:string;workflow_status?:string;render_driver?:string|null;render_metadata?:Record<string,unknown>|null
  generated_at:string;approved_at?:string|null;signed_at?:string|null;locked_at?:string|null;share_links_count?:number;signature_requests_count?:number
  template?:{id:number;name:string}|null;share_links?:DocumentShare[];signature_requests?:DocumentSignature[];review_events?:DocumentReviewEvent[];comments?:DocumentComment[]
}

/** Describes the complete Document Studio overview payload. */
export type DocumentOverview = {
  templates:DocumentTemplate[];generated:GeneratedDocument[];components?:DocumentComponent[]
  catalog:{types:Record<string,string>;blocks:Record<string,string>;variables:Record<string,string[]>;locales:Array<{code:string;label:string;direction:string;intl:string;core:boolean}>}
  legal_entities:Array<{id:number;name:string}>
  permissions:{manage:boolean;templates_manage:boolean;generate:boolean;share:boolean;sign:boolean;approve:boolean;components_manage:boolean}
  rendering:{pdf_driver:string;chromium_available:boolean;code_adapter_available:boolean}
}
