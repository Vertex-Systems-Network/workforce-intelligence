import { useEffect, useMemo, useState } from 'react';
import { DndContext, DragEndEvent, DragOverlay, KeyboardSensor, PointerSensor, closestCorners, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { CalendarDays, CheckSquare2, GripVertical, UserRound } from 'lucide-react';
import { Avatar, Badge, Pressable, Box } from '../design-system';
import { Task, TaskStatus, memberLabel } from '../task-engine/types';
import './task-board.css';
type MovePayload = {
    statusId: number;
    previousTaskId: number | null;
    nextTaskId: number | null;
};
type Props = {
    tasks: Task[];
    statuses: TaskStatus[];
    canManage: boolean;
    onOpen: (task: Task) => void;
    onMove: (task: Task, payload: MovePayload) => Promise<void>;
};
/** Handles the task dnd id operation for the WorkIntel client. */ const taskDndId = (id: number) => `task:${id}`;
/** Handles the status dnd id operation for the WorkIntel client. */ const statusDndId = (id: number) => `status:${id}`;
/** Handles the parse id operation for the WorkIntel client. */ const parseId = (raw: string | number) => { const value = String(raw); const [kind, id] = value.split(':'); return { kind, id: Number(id) }; };
/** Handles the sortable card operation for the WorkIntel client. */ function SortableCard({ task, canManage, onOpen }: {
    task: Task;
    canManage: boolean;
    onOpen: (task: Task) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: taskDndId(task.id), disabled: !canManage, data: { kind: 'task', taskId: task.id, statusId: task.task_status_id } });
    const checklistTotal = task.checklist_total ?? 0;
    const checklistDone = task.checklist_completed ?? 0;
    const style = { transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? .45 : 1 };
    return <article ref={setNodeRef} style={style} className="task-board-card" onDoubleClick={() => onOpen(task)}>
    <div className="task-board-card__top">
      {canManage ? <Pressable type="button" className="task-board-card__grip" aria-label={`Move ${task.title}`} {...attributes} {...listeners}><GripVertical size={15}/></Pressable> : <span />}
      <Badge tone={task.priority === 'critical' ? 'danger' : task.priority === 'high' ? 'warning' : 'neutral'}>{task.priority}</Badge>
    </div>
    <Pressable type="button" className="task-board-card__title" onClick={() => onOpen(task)}>{task.title}</Pressable>
    <div className="task-board-card__project">{task.project?.name ?? 'No project'}</div>
    {!!task.tags.length && <div className="task-board-card__tags">{task.tags.slice(0, 3).map(tag => <span key={tag.id} className="task-tag-chip"><Box as="i" bg={tag.color}/>{tag.name}</span>)}</div>}
    <div className="task-board-card__meta">
      {task.due_at && <span><CalendarDays size={12}/>{new Date(task.due_at).toLocaleDateString()}</span>}
      {checklistTotal > 0 && <span><CheckSquare2 size={12}/>{checklistDone}/{checklistTotal}</span>}
      {!!task.assignees.length && <span title={task.assignees.map(memberLabel).join(', ')}><UserRound size={12}/>{task.assignees.length}</span>}
    </div>
    {!!task.assignees.length && <div className="task-board-card__avatars">{task.assignees.slice(0, 4).map(member => <Avatar key={member.id} name={memberLabel(member)} size="sm"/>)}</div>}
  </article>;
}
/** Handles the column operation for the WorkIntel client. */ function Column({ status, tasks, canManage, onOpen }: {
    status: TaskStatus;
    tasks: Task[];
    canManage: boolean;
    onOpen: (task: Task) => void;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: statusDndId(status.id), data: { kind: 'status', statusId: status.id } });
    return <section className={`task-board-column${isOver ? ' is-over' : ''}`}>
    <header className="task-board-column__header"><Box as="span" className="task-board-column__dot" bg={status.color}/><strong>{status.name}</strong><span>{tasks.length}</span></header>
    <div ref={setNodeRef} className="task-board-column__body">
      <SortableContext items={tasks.map(t => taskDndId(t.id))} strategy={verticalListSortingStrategy}>
        {tasks.map(task => <SortableCard key={task.id} task={task} canManage={canManage} onOpen={onOpen}/>)}
      </SortableContext>
      {!tasks.length && <div className="task-board-column__empty">Drop tasks here</div>}
    </div>
  </section>;
}
/** Handles the task board operation for the WorkIntel client. */ export default function TaskBoard({ tasks, statuses, canManage, onOpen, onMove }: Props) {
    const [localTasks, setLocalTasks] = useState(tasks);
    const [activeId, setActiveId] = useState<number | null>(null);
    useEffect(() => setLocalTasks(tasks), [tasks]);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const activeTask = activeId ? localTasks.find(task => task.id === activeId) ?? null : null;
    const byStatus = useMemo(() => new Map(statuses.map(status => [status.id, localTasks.filter(t => t.task_status_id === status.id).sort((a, b) => a.position - b.position)])), [localTasks, statuses]);
    /** Handles the on drag end operation for the WorkIntel client. */ const onDragEnd = async (event: DragEndEvent) => {
        setActiveId(null);
        if (!canManage || !event.over)
            return;
        const active = parseId(event.active.id);
        const over = parseId(event.over.id);
        if (active.kind !== 'task' || !active.id)
            return;
        const task = localTasks.find(row => row.id === active.id);
        if (!task)
            return;
        const targetStatusId = over.kind === 'status' ? over.id : localTasks.find(row => row.id === over.id)?.task_status_id;
        if (!targetStatusId)
            return;
        const targetTasks = localTasks.filter(row => row.id !== task.id && row.task_status_id === targetStatusId).sort((a, b) => a.position - b.position);
        let insertIndex = targetTasks.length;
        if (over.kind === 'task') {
            const index = targetTasks.findIndex(row => row.id === over.id);
            if (index >= 0)
                insertIndex = index;
        }
        const ordered = [...targetTasks];
        ordered.splice(insertIndex, 0, { ...task, task_status_id: targetStatusId });
        const previous = ordered[insertIndex - 1] ?? null;
        const next = ordered[insertIndex + 1] ?? null;
        const before = localTasks;
        setLocalTasks(localTasks.map(row => row.id === task.id ? { ...row, task_status_id: targetStatusId, workflow_status: statuses.find(s => s.id === targetStatusId) ?? row.workflow_status } : row));
        try {
            await onMove(task, { statusId: targetStatusId, previousTaskId: previous?.id ?? null, nextTaskId: next?.id ?? null });
        }
        catch {
            setLocalTasks(before);
        }
    };
    return <DndContext sensors={sensors} collisionDetection={closestCorners} onDragStart={event => {
            const parsed = parseId(event.active.id);
            if (parsed.kind === 'task')
                setActiveId(parsed.id);
        }} onDragCancel={() => setActiveId(null)} onDragEnd={event => void onDragEnd(event)}>
    <div className="task-board" role="region" aria-label="Task workflow board">
      {statuses.map(status => <Column key={status.id} status={status} tasks={byStatus.get(status.id) ?? []} canManage={canManage} onOpen={onOpen}/>)}
    </div>
    <DragOverlay>{activeTask ? <div className="task-board-card task-board-card--overlay"><strong>{activeTask.title}</strong><div>{activeTask.project?.name}</div></div> : null}</DragOverlay>
  </DndContext>;
}
