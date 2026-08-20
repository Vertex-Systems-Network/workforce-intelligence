/** Describes one WorkIntel Media Library asset returned by the API. */
export type MediaAsset = {
  id:number;uuid:string;name:string;original_name:string;category:'image'|'video'|'audio'|'document'|'other';mime_type?:string|null;extension?:string|null;size_bytes:number
  width?:number|null;height?:number|null;focal_x?:number|null;focal_y?:number|null;alt_text?:string|null;caption?:string|null
  copyright_owner?:string|null;license_type?:string|null;license_reference?:string|null;license_expires_at?:string|null;usage_restrictions?:string|null;rights_review_at?:string|null;rights_status:'clear'|'expired'|'expiring'|'review'|'unclassified'
  visibility:'private'|'public';status:string;folder?:{id:number;name:string}|null;tags:{id:number;name:string;color?:string|null}[];collections:{id:number;name:string}[];is_favorite:boolean
  usages_count:number;versions_count:number;renditions_count:number;created_at?:string|null;updated_at?:string|null;deleted_at?:string|null;content_url:string;download_url:string;public_url?:string|null
}
/** Describes one Media Library folder. */
export type MediaFolder = {id:number;parent_id?:number|null;name:string;slug:string;assets_count:number;children_count:number}
/** Describes one Media Library tag. */
export type MediaTag = {id:number;name:string;color?:string|null;assets_count:number}
/** Describes one reusable DAM collection and its explicit sharing configuration. */
export type MediaCollection = {id:number;name:string;description?:string|null;visibility:'workspace'|'restricted';shared_member_ids:number[];assets_count:number;created_at?:string|null;updated_at?:string|null}
/** Describes one active asset usage record. */
export type MediaUsage = {id:number;resource_type:string;resource_id?:number|null;field?:string|null;label?:string|null;created_at?:string|null;updated_at?:string|null}
/** Describes one immutable DAM metadata/binary version. */
export type MediaVersion = {id:number;version_number:number;name:string;alt_text?:string|null;caption?:string|null;copyright_owner?:string|null;license_type?:string|null;license_reference?:string|null;license_expires_at?:string|null;usage_restrictions?:string|null;rights_review_at?:string|null;focal_x?:number|null;focal_y?:number|null;tags:string[];folder?:{id:number;name:string}|null;binary_available:boolean;binary_status?:string|null;size_bytes?:number|null;mime_type?:string|null;checksum_sha256?:string|null;download_url?:string|null;reason?:string|null;creator?:string|null;created_at?:string|null}
/** Describes one generated private image rendition. */
export type MediaRendition = {id:number;width:number;height:number;fit:'contain'|'cover';format:'jpeg'|'png'|'webp';quality:number;mime_type:string;size_bytes:number;status:string;content_url:string;created_at?:string|null}
