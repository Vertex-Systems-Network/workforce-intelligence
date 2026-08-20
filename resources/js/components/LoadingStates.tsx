import { useEffect, useState } from 'react';
import { LoaderCircle } from 'lucide-react';
import { subscribeRequestActivity } from '../api/client';
import { Box } from '../design-system';
/** Handles the request progress operation for the WorkIntel client. */ export function RequestProgress() {
    const [pending, setPending] = useState(0);
    const [visible, setVisible] = useState(false);
    useEffect(() => subscribeRequestActivity(setPending), []);
    useEffect(() => {
        if (pending > 0) {
            const timer = window.setTimeout(() => setVisible(true), 120);
            return () => window.clearTimeout(timer);
        }
        const timer = window.setTimeout(() => setVisible(false), 180);
        return () => window.clearTimeout(timer);
    }, [pending]);
    return <div className={`request-progress${visible ? ' is-visible' : ''}`} aria-hidden="true"><span /></div>;
}
/** Handles the app boot loader operation for the WorkIntel client. */ export function AppBootLoader({ label = 'Loading workspace…' }: {
    label?: string;
}) {
    return <div className="app-boot-loader">
    <div className="app-boot-loader__mark"><LoaderCircle size={22}/></div>
    <div className="app-boot-loader__label">{label}</div>
    <div className="app-boot-loader__bar"><span /></div>
  </div>;
}
/** Handles the page loading state operation for the WorkIntel client. */ export function PageLoadingState({ title, description }: {
    title?: string;
    description?: string;
} = {}) {
    return <div className="page-loader" aria-label={title ?? 'Loading page'}>
    <div className="page-loader__header">
      <div>{title ? <><Box size={16} weight={650} color="var(--text)">{title}</Box>{description && <Box className="ui-card-description" mt={4}>{description}</Box>}</> : <><div className="skeleton page-loader__title"/><div className="skeleton page-loader__subtitle"/></>}</div>
      <div className="skeleton page-loader__action"/>
    </div>
    <div className="page-loader__stats">{Array.from({ length: 4 }).map((_, index) => <div key={index} className="ui-stat-card"><div className="skeleton page-loader__line"/><div className="skeleton page-loader__metric"/><div className="skeleton page-loader__line short"/></div>)}</div>
    <div className="page-loader__content"><div className="ui-card"><div className="ui-card__body"><div className="skeleton page-loader__chart"/></div></div><div className="ui-card"><div className="ui-card__body">{Array.from({ length: 6 }).map((_, index) => <div key={index} className="skeleton page-loader__row"/>)}</div></div></div>
  </div>;
}
/** Renders a compact table-shaped skeleton that preserves final row geometry. */
export function TableLoadingState({ rows = 6, columns = 5 }: {
    rows?: number;
    columns?: number;
}) {
    return <div className="view-skeleton view-skeleton--table"><div className="view-skeleton__toolbar"><span /><span /><span /></div>{Array.from({ length: rows }).map((_, row) => <div key={row} className="view-skeleton__table-row">{Array.from({ length: columns }).map((__, column) => <span key={column}/>)}</div>)}</div>;
}
/** Renders a card-grid skeleton for media, templates and other gallery screens. */
export function CardGridLoadingState({ cards = 8 }: {
    cards?: number;
}) {
    return <div className="view-skeleton view-skeleton--cards">{Array.from({ length: cards }).map((_, index) => <div key={index}><span className="view-skeleton__media"/><span /><span className="short"/></div>)}</div>;
}
/** Renders a profile-shaped skeleton with avatar, identity and form sections. */
export function ProfileLoadingState() {
    return <div className="view-skeleton view-skeleton--profile"><div className="view-skeleton__avatar"/><div className="view-skeleton__profile-lines"><span /><span className="short"/></div><div className="view-skeleton__form">{Array.from({ length: 6 }).map((_, index) => <span key={index}/>)}</div></div>;
}
/** Renders the Media Library sidebar, toolbar and grid shape while data loads. */
export function MediaLibraryLoadingState() {
    return <div className="view-skeleton view-skeleton--media-library"><aside>{Array.from({ length: 6 }).map((_, index) => <span key={index}/>)}</aside><section><div className="view-skeleton__toolbar"><span /><span /><span /></div><CardGridLoadingState cards={10}/></section></div>;
}
/** Renders a kanban/board loading state with multiple stable columns. */
export function BoardLoadingState({ columns = 4 }: {
    columns?: number;
}) {
    return <div className="view-skeleton view-skeleton--board">{Array.from({ length: columns }).map((_, column) => <div key={column}><span className="view-skeleton__board-title"/>{Array.from({ length: 3 }).map((__, card) => <span key={card} className="view-skeleton__board-card"/>)}</div>)}</div>;
}
/** Renders a form loading state with section headings and responsive field placeholders. */
export function FormLoadingState({ fields = 8 }: {
    fields?: number;
}) {
    return <div className="view-skeleton view-skeleton--form"><span className="view-skeleton__form-title"/><div>{Array.from({ length: fields }).map((_, index) => <span key={index}/>)}</div></div>;
}
