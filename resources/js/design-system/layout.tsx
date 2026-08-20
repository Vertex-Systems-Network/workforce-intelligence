import type { CSSProperties, HTMLAttributes, LabelHTMLAttributes, ReactNode } from 'react';

/** Join design-system class names without leaking falsey tokens. */
const cx = (...values: Array<string | false | null | undefined>) => values.filter(Boolean).join(' ');

export type LayoutLength = number | string
/** Shared spacing and sizing props implemented only inside WorkIntel composition primitives. */
export interface LayoutSpacingProps {
  m?: LayoutLength; mt?: LayoutLength; mb?: LayoutLength; ml?: LayoutLength; mr?: LayoutLength
  p?: LayoutLength; pt?: LayoutLength; pb?: LayoutLength; pl?: LayoutLength; pr?: LayoutLength
  flex?: CSSProperties['flex']; minWidth?: LayoutLength; maxWidth?: LayoutLength; width?: LayoutLength
  height?: LayoutLength; minHeight?: LayoutLength; maxHeight?: LayoutLength
}

/** Shared visual composition props keep page code declarative while all CSS implementation remains inside the design system. */
export interface VisualProps extends LayoutSpacingProps {
  display?: CSSProperties['display']; position?: CSSProperties['position']; inset?: LayoutLength
  top?: LayoutLength; right?: LayoutLength; bottom?: LayoutLength; left?: LayoutLength; zIndex?: CSSProperties['zIndex']
  bg?: CSSProperties['background']; border?: CSSProperties['border']; borderTop?: CSSProperties['borderTop']; borderRight?: CSSProperties['borderRight']; borderBottom?: CSSProperties['borderBottom']; borderLeft?: CSSProperties['borderLeft']; borderColor?: CSSProperties['borderColor']; borderInlineEnd?: CSSProperties['borderInlineEnd']; radius?: CSSProperties['borderRadius']
  overflow?: CSSProperties['overflow']; overflowX?: CSSProperties['overflowX']; overflowY?: CSSProperties['overflowY']; overflowWrap?: CSSProperties['overflowWrap']; textOverflow?: CSSProperties['textOverflow']; wordBreak?: CSSProperties['wordBreak']; objectFit?: CSSProperties['objectFit']
  gridColumns?: CSSProperties['gridTemplateColumns']; gridRows?: CSSProperties['gridTemplateRows']; gridColumn?: CSSProperties['gridColumn']; aspectRatio?: CSSProperties['aspectRatio']; transform?: CSSProperties['transform']; filter?: CSSProperties['filter']
  cursor?: CSSProperties['cursor']; opacity?: CSSProperties['opacity']; boxShadow?: CSSProperties['boxShadow']; transition?: CSSProperties['transition']
  gap?: LayoutLength; rowGap?: LayoutLength; columnGap?: LayoutLength; align?: CSSProperties['alignItems']; justify?: CSSProperties['justifyContent']; wrap?: CSSProperties['flexWrap']; direction?: CSSProperties['flexDirection']; shrink?: CSSProperties['flexShrink']; grow?: CSSProperties['flexGrow']; basis?: CSSProperties['flexBasis']; placeItems?: CSSProperties['placeItems']
  color?: CSSProperties['color']; size?: LayoutLength; weight?: CSSProperties['fontWeight']; lineHeight?: CSSProperties['lineHeight']; textAlign?: CSSProperties['textAlign']; whiteSpace?: CSSProperties['whiteSpace']; textTransform?: CSSProperties['textTransform']; letterSpacing?: CSSProperties['letterSpacing']; fontFamily?: CSSProperties['fontFamily']; font?: CSSProperties['font']
}

/** Convert declarative WorkIntel visual props into the only inline visual implementation feature code needs. */
function visualStyle({m,mt,mb,ml,mr,p,pt,pb,pl,pr,flex,minWidth,maxWidth,width,height,minHeight,maxHeight,display,position,inset,top,right,bottom,left,zIndex,bg,border,borderTop,borderRight,borderBottom,borderLeft,borderColor,borderInlineEnd,radius,overflow,overflowX,overflowY,overflowWrap,textOverflow,wordBreak,objectFit,gridColumns,gridRows,gridColumn,aspectRatio,transform,filter,cursor,opacity,boxShadow,transition,gap,rowGap,columnGap,align,justify,wrap,direction,shrink,grow,basis,placeItems,color,size,weight,lineHeight,textAlign,whiteSpace,textTransform,letterSpacing,fontFamily,font}:VisualProps):CSSProperties {
  return {margin:m,marginTop:mt,marginBottom:mb,marginLeft:ml,marginRight:mr,padding:p,paddingTop:pt,paddingBottom:pb,paddingLeft:pl,paddingRight:pr,flex,minWidth,maxWidth,width,height,minHeight,maxHeight,display,position,inset,top,right,bottom,left,zIndex,background:bg,border,borderTop,borderRight,borderBottom,borderLeft,borderColor,borderInlineEnd,borderRadius:radius,overflow,overflowX,overflowY,overflowWrap,textOverflow,wordBreak,objectFit,gridTemplateColumns:gridColumns,gridTemplateRows:gridRows,gridColumn,aspectRatio,transform,filter,cursor,opacity,boxShadow,transition,gap,rowGap,columnGap,alignItems:align,justifyContent:justify,flexWrap:wrap,flexDirection:direction,flexShrink:shrink,flexGrow:grow,flexBasis:basis,placeItems,color,fontSize:size,fontWeight:weight,lineHeight,textAlign,whiteSpace,textTransform,letterSpacing,fontFamily,font}
}

const visualPropKeys=new Set(['m','mt','mb','ml','mr','p','pt','pb','pl','pr','flex','minWidth','maxWidth','width','height','minHeight','maxHeight','display','position','inset','top','right','bottom','left','zIndex','bg','border','borderTop','borderRight','borderBottom','borderLeft','borderColor','borderInlineEnd','radius','overflow','overflowX','overflowY','overflowWrap','textOverflow','wordBreak','objectFit','gridColumns','gridRows','gridColumn','aspectRatio','transform','filter','cursor','opacity','boxShadow','transition','gap','rowGap','columnGap','align','justify','wrap','direction','shrink','grow','basis','placeItems','color','size','weight','lineHeight','textAlign','whiteSpace','textTransform','letterSpacing','fontFamily','font'])
/** Separate design-system visual props from DOM props so declarative styling never leaks unknown attributes. */
function splitVisualProps<T extends Record<string,unknown>>(props:T):[VisualProps,Record<string,unknown>]{const visual:Record<string,unknown>={};const rest:Record<string,unknown>={};for(const [key,value] of Object.entries(props)){(visualPropKeys.has(key)?visual:rest)[key]=value}return [visual as VisualProps,rest]}

type BoxTag='div'|'section'|'header'|'nav'|'main'|'aside'|'article'|'span'|'p'|'h1'|'h2'|'h3'|'h4'|'strong'|'small'|'code'|'pre'|'i'
/** Render a neutral polymorphic layout box while keeping visual implementation inside the design system. */
export function Box({as='div',children,className='',style,...props}:HTMLAttributes<HTMLElement>&VisualProps&{as?:BoxTag}){
  const [visual,rest]=splitVisualProps(props)
  const Component=as as any
  return <Component className={cx('ui-box',className)} style={{...visualStyle(visual),...style}} {...rest}>{children}</Component>
}

/** Handles vertical flow with design-system-owned spacing and alignment. */
export function Stack({gap=12,children,className='',style,...props}:HTMLAttributes<HTMLDivElement>&VisualProps&{children?:ReactNode}){
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-stack',className)} style={{...visualStyle({...visual,display:visual.display??'flex',gap,direction:'column'}),...style}} {...rest}>{children}</div>
}
/** Handles horizontal flow with design-system-owned spacing, wrapping and distribution. */
export function Inline({gap=8,children,className='',style,...props}:HTMLAttributes<HTMLDivElement>&VisualProps&{children?:ReactNode}){
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-inline',className)} style={{...visualStyle({...visual,display:visual.display??'flex',gap}),...style}} {...rest}>{children}</div>
}
/** Render responsive or fixed CSS-grid composition without page-owned style objects. */
export function Grid({columns='1fr',gap=12,children,className='',style,...props}:HTMLAttributes<HTMLDivElement>&VisualProps&{columns?:string;children?:ReactNode}){
  const [visual,rest]=splitVisualProps(props)
  return <div className={cx('ui-grid',className)} style={{...visualStyle({...visual,display:visual.display??'grid',gap,gridColumns:columns}),...style}} {...rest}>{children}</div>
}
/** Render a bounded multi-choice collection with consistent responsive columns and scrolling. */
export function ChoiceList({children,columns=2,maxHeight='md',className='',...props}:HTMLAttributes<HTMLDivElement>&{columns?:1|2|3|4;maxHeight?:'sm'|'md'|'lg';children?:ReactNode}){
  return <div className={cx('ui-choice-list',`ui-choice-list--cols-${columns}`,`ui-choice-list--${maxHeight}`,className)} {...props}>{children}</div>
}
/** Render one checkbox/radio choice row without page-owned border, spacing or cursor styles. */
export function ChoiceRow({children,className='',selected=false,...props}:LabelHTMLAttributes<HTMLLabelElement>&{selected?:boolean;children?:ReactNode}){
  return <label className={cx('ui-choice-row',selected&&'is-selected',className)} {...props}>{children}</label>
}
/** Render common typography while keeping visual styling in the design-system primitive. */
export function Text({as='span',children,className='',style,...props}:HTMLAttributes<HTMLElement>&VisualProps&{as?:'span'|'p'|'strong'|'small'|'h1'|'h2'|'h3'|'h4'|'code'}){
  const [visual,rest]=splitVisualProps(props)
  const common={className:cx('ui-text',className),style:{...visualStyle(visual),...style},...rest}
  if(as==='p')return <p {...common}>{children}</p>
  if(as==='strong')return <strong {...common}>{children}</strong>
  if(as==='small')return <small {...common}>{children}</small>
  if(as==='h1')return <h1 {...common}>{children}</h1>
  if(as==='h2')return <h2 {...common}>{children}</h2>
  if(as==='h3')return <h3 {...common}>{children}</h3>
  if(as==='h4')return <h4 {...common}>{children}</h4>
  if(as==='code')return <code {...common}>{children}</code>
  return <span {...common}>{children}</span>
}
