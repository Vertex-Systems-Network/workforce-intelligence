import { Avatar, Box, Checkbox, ChoiceList, ChoiceRow, Field, Text } from '../../design-system';
import { type Member, type Task, type TaskTag, memberLabel } from '../../task-engine/types';
export type Person = Member & {
    first_name?: string;
    last_name?: string;
    department?: string | null;
};
export type TaskForm = {
    project_id: string;
    parent_id: string;
    owner_member_id: string;
    title: string;
    description_html: string;
    status_id: string;
    priority: Task['priority'];
    estimated_hours: string;
    start_at: string;
    due_at: string;
    billable: boolean;
    client_visible: boolean;
    assignee_ids: number[];
    observer_ids: number[];
    tag_ids: number[];
};
export const emptyForm: TaskForm = { project_id: '', parent_id: '', owner_member_id: '', title: '', description_html: '', status_id: '', priority: 'medium', estimated_hours: '', start_at: '', due_at: '', billable: true, client_visible: false, assignee_ids: [], observer_ids: [], tag_ids: [] };
/** Handles the tone for priority operation for the WorkIntel client. */ export const toneForPriority = (priority: string): 'neutral' | 'warning' | 'danger' => priority === 'critical' ? 'danger' : priority === 'high' ? 'warning' : 'neutral';
/** Formats format minutes data for display. */ export const formatMinutes = (minutes: number | null) => minutes == null ? '—' : minutes >= 60 ? `${Math.floor(minutes / 60)}h${minutes % 60 ? ` ${minutes % 60}m` : ''}` : `${minutes}m`;
/** Handles the person name operation for the WorkIntel client. */ export const personName = (person: Person) => person.name || [person.first_name, person.last_name].filter(Boolean).join(' ') || memberLabel(person);
/** Handles the html date operation for the WorkIntel client. */ export const htmlDate = (value: string | null) => value ? value.slice(0, 16) : '';
/** Handles the toggle id operation for the WorkIntel client. */ export const toggleId = (ids: number[], id: number) => ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id];
/** Handles the multi member picker operation for the WorkIntel client. */ export function MultiMemberPicker({ label, people, selected, onChange, disabled = false }: {
    label: string;
    people: Person[];
    selected: number[];
    onChange: (ids: number[]) => void;
    disabled?: boolean;
}) {
    return <Field label={label}><ChoiceList columns={2}>{people.map(person => { const active = selected.includes(person.id); return <ChoiceRow key={person.id} selected={active}><Checkbox checked={active} disabled={disabled} onChange={() => onChange(toggleId(selected, person.id))}/><Avatar name={personName(person)} size="sm"/><span>{personName(person)}</span></ChoiceRow>; })}{!people.length && <Text color="var(--text-3)">No members available in your scope.</Text>}</ChoiceList></Field>;
}
/** Handles the tag picker operation for the WorkIntel client. */ export function TagPicker({ tags, selected, onChange }: {
    tags: TaskTag[];
    selected: number[];
    onChange: (ids: number[]) => void;
}) {
    return <Field label="Tags"><ChoiceList columns={3}>{tags.map(tag => { const active = selected.includes(tag.id); return <ChoiceRow key={tag.id} selected={active}><Checkbox checked={active} onChange={() => onChange(toggleId(selected, tag.id))}/><Box as="i" bg={tag.color} width={8} height={8} radius="50%"/>{tag.name}</ChoiceRow>; })}{!tags.length && <Text color="var(--text-3)">No task tags yet.</Text>}</ChoiceList></Field>;
}
