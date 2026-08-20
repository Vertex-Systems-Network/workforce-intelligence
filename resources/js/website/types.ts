/** Describes one public Website Studio media item after server-side path protection. */
export type WebsiteMedia={id:number;uuid:string;name:string;mime_type:string;alt_text?:string|null;caption?:string|null;width?:number|null;height?:number|null;url:string}
/** Describes one versioned Website Studio section. */
export type WebsiteSection={id:string;type:string;settings:Record<string,any>}
/** Describes one immutable page schema edited or published by Website Studio. */
export type WebsiteSchema={schema_version:number;sections:WebsiteSection[]}
/** Describes one public website form definition without private submission data. */
export type WebsitePublicForm={uuid:string;name:string;fields:Array<{id:string;type:string;label:string;required?:boolean;options?:string[]}>;settings?:Record<string,any>;success_message?:string|null}
/** Describes the published site and page payload served to public visitors. */
export type PublicWebsitePayload={
 site:{uuid:string;workspace_slug?:string;name:string;default_language:string;supported_languages:string[];theme:Record<string,any>;header_config:Record<string,any>;footer_config:Record<string,any>;seo_defaults:Record<string,any>}
 page:{uuid:string;type:string;language:string;title:string;slug:string;seo_title?:string|null;seo_description?:string|null;og_image?:WebsiteMedia|null;schema:WebsiteSchema}
 navigation:Array<{label:string;path:string}>
 forms:Record<string,WebsitePublicForm>
}
