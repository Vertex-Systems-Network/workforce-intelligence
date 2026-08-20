import { FormEvent, useEffect, useState } from 'react';
import { Award, BookOpen, Brain, ClipboardCheck, Plus, RefreshCw, Target } from 'lucide-react';
import { apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Alert, Badge, Box, Button, Card, CardBody, CardHeader, DataGrid, ErrorState, Field, FormDialog, Grid, Inline, Input, Option, Page, PageHeader, Select, Stack, StatCard, Tabs, Text, Textarea, type DataGridColumn, } from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
type Person = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
    };
    department?: {
        name: string;
    } | null;
};
type Goal = {
    id: number;
    member_id: number;
    title: string;
    status: string;
    progress_percent: number;
    due_date: string | null;
    member?: Person;
};
type Review = {
    id: number;
    status: string;
    self_rating: string | null;
    manager_rating: string | null;
    overall_rating: string | null;
    member: Person;
    cycle: {
        name: string;
    };
};
type Cycle = {
    id: number;
    name: string;
    status: string;
    start_date: string;
    end_date: string;
    reviews_count: number;
};
type One = {
    id: number;
    status: string;
    scheduled_at: string;
    member: Person;
    manager: Person;
};
type Skill = {
    id: number;
    name: string;
    category: string | null;
    max_proficiency: number;
};
type MemberSkill = {
    id: number;
    proficiency: number;
    target_proficiency: number | null;
    skill: Skill;
    member: Person;
};
type Course = {
    id: number;
    name: string;
    provider: string | null;
};
type Enrollment = {
    id: number;
    status: string;
    expires_on: string | null;
    course: Course;
    member: Person;
};
type Plan = {
    id: number;
    title: string;
    objective: string;
    status: string;
    target_date: string | null;
    member: Person;
    items: Array<{
        id: number;
        title: string;
        status: string;
    }>;
};
type Recognition = {
    id: number;
    title: string;
    message: string;
    points: number;
    recognized_at: string;
    recipient: Person;
    sender: Person;
};
type Survey = {
    id: number;
    title: string;
    status: string;
    anonymous: boolean;
    questions: Array<{
        id: number;
        question: string;
        question_type: string;
    }>;
};
type CompCycle = {
    id: number;
    name: string;
    status: string;
    budget_amount: string | null;
    currency: string;
    items: Array<{
        id: number;
        status: string;
        proposed_amount: string | null;
        proposed_title: string | null;
        member: Person;
    }>;
};
type Payload = {
    people: Person[];
    goals: Goal[];
    review_cycles: Cycle[];
    reviews: Review[];
    one_on_ones: One[];
    skills: Skill[];
    member_skills: MemberSkill[];
    courses: Course[];
    enrollments: Enrollment[];
    development_plans: Plan[];
    recognitions: Recognition[];
    surveys: Survey[];
    compensation_cycles?: CompCycle[];
    can_manage: boolean;
    can_manage_reviews: boolean;
    can_manage_skills: boolean;
    can_manage_learning: boolean;
    can_manage_surveys: boolean;
    can_manage_compensation: boolean;
    current_member_id: number;
};
type PerformanceTab = 'goals' | 'reviews' | 'growth' | 'learning' | 'recognition' | 'surveys' | 'comp';
/** Returns a readable employee name. */
const person = (p?: Person) => p ? `${p.user.first_name} ${p.user.last_name}` : '—';
/** Maps workflow status to a consistent badge tone. */
const tone = (s: string): 'success' | 'warning' | 'danger' | 'neutral' => ['completed', 'approved', 'active'].includes(s) ? 'success' : ['pending', 'in_progress', 'proposed'].includes(s) ? 'warning' : ['rejected', 'canceled', 'at_risk'].includes(s) ? 'danger' : 'neutral';
/** Provides the performance, development, learning and recognition workspace. */
export default function Performance() {
    const { session } = useAuth();
    const workspaceId = session?.user.activeWorkspaceId;
    const [data, setData] = useState<Payload | null>(null);
    const [tab, setTab] = useState<PerformanceTab>('goals');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [goalOpen, setGoalOpen] = useState(false);
    const [recOpen, setRecOpen] = useState(false);
    const [cycleOpen, setCycleOpen] = useState(false);
    const [reviewOpen, setReviewOpen] = useState(false);
    const [selectedReview, setSelectedReview] = useState<Review | null>(null);
    const [reviewForm, setReviewForm] = useState({ rating: '4', summary: '' });
    const [surveyOpen, setSurveyOpen] = useState(false);
    const [selectedSurvey, setSelectedSurvey] = useState<Survey | null>(null);
    const [surveyAnswers, setSurveyAnswers] = useState<Record<number, string>>({});
    const [goal, setGoal] = useState({ member_id: '', title: '', description: '', due_date: '', category: 'individual' });
    const [rec, setRec] = useState({ recipient_member_id: '', title: '', message: '', points: '0' });
    const [cycle, setCycle] = useState({ name: 'H2 Performance Review', review_type: 'semiannual', start_date: new Date().toISOString().slice(0, 10), end_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10) });
    /** Loads the role-scoped performance overview. */
    const load = async () => { if (!workspaceId)
        return; setLoading(true); try {
        setData(await apiRequest<Payload>('/api/v1/performance/overview', { workspaceId }));
        setError('');
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load performance.');
    }
    finally {
        setLoading(false);
    } };
    useEffect(() => { void load(); }, [workspaceId]);
    /** Creates one goal and refreshes the workspace. */
    const saveGoal = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/performance/goals', { method: 'POST', workspaceId, body: JSON.stringify({ ...goal, member_id: goal.member_id ? Number(goal.member_id) : undefined, due_date: goal.due_date || null }) });
        setGoalOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create goal.');
    }
    finally {
        setSaving(false);
    } };
    /** Updates goal progress with an auditable note. */
    const updateGoal = async (row: Goal, p: number) => { await apiRequest(`/api/v1/performance/goals/${row.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ progress_percent: p, note: `Progress updated to ${p}%` }) }); await load(); };
    /** Sends peer recognition. */
    const saveRec = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/performance/recognitions', { method: 'POST', workspaceId, body: JSON.stringify({ ...rec, recipient_member_id: Number(rec.recipient_member_id), points: Number(rec.points) }) });
        setRecOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not send recognition.');
    }
    finally {
        setSaving(false);
    } };
    /** Creates a review cycle. */
    const saveCycle = async (e: FormEvent) => { e.preventDefault(); setSaving(true); try {
        await apiRequest('/api/v1/performance/review-cycles', { method: 'POST', workspaceId, body: JSON.stringify(cycle) });
        setCycleOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not create review cycle.');
    }
    finally {
        setSaving(false);
    } };
    /** Launches a draft review cycle. */
    const launch = async (c: Cycle) => { try {
        await apiRequest(`/api/v1/performance/review-cycles/${c.id}/launch`, { method: 'POST', workspaceId });
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not launch review cycle.');
    } };
    /** Submits either the employee self-review or manager review. */
    const submitReview = async (e: FormEvent) => { e.preventDefault(); if (!selectedReview)
        return; setSaving(true); try {
        const reviewer_type = selectedReview.member.id === data?.current_member_id ? 'self' : 'manager';
        await apiRequest(`/api/v1/performance/reviews/${selectedReview.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ reviewer_type, rating: Number(reviewForm.rating), summary: reviewForm.summary }) });
        setReviewOpen(false);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not submit review.');
    }
    finally {
        setSaving(false);
    } };
    /** Submits a pulse-survey response. */
    const respondSurvey = async (e: FormEvent) => { e.preventDefault(); if (!selectedSurvey)
        return; setSaving(true); try {
        await apiRequest(`/api/v1/performance/surveys/${selectedSurvey.id}/respond`, { method: 'POST', workspaceId, body: JSON.stringify({ responses: selectedSurvey.questions.map(q => ({ question_id: q.id, ...(q.question_type === 'rating' ? { rating: Number(surveyAnswers[q.id] || 4) } : { response: surveyAnswers[q.id] || '' }) })) }) });
        setSurveyOpen(false);
        setSelectedSurvey(null);
        await load();
    }
    catch (e) {
        setError(e instanceof Error ? e.message : 'Could not submit survey.');
    }
    finally {
        setSaving(false);
    } };
    if (loading && !data)
        return <PageLoadingState />;
    if (!data)
        return <Page><ErrorState title="Performance unavailable" text={error || 'Performance data could not be loaded.'} retry={load}/></Page>;
    const goalColumns: DataGridColumn<Goal>[] = [
        { id: 'employee', header: 'Employee', value: r => person(r.member), sortable: true },
        { id: 'goal', header: 'Goal', value: r => r.title, sortable: true },
        { id: 'progress', header: 'Progress', value: r => r.progress_percent, cell: r => `${r.progress_percent}%`, sortable: true },
        { id: 'due', header: 'Due', value: r => r.due_date || '', cell: r => r.due_date?.slice(0, 10) || '—', sortable: true, filter: { type: 'dateRange' } },
        { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge>, sortable: true, filter: { type: 'select', options: [{ label: 'Active', value: 'active' }, { label: 'At risk', value: 'at_risk' }, { label: 'Completed', value: 'completed' }] } },
        { id: 'actions', header: 'Progress update', cell: r => (r.member_id === data.current_member_id || data.can_manage) ? <Inline gap={4}>{[25, 50, 75, 100].map(p => <Button key={p} variant="ghost" size="sm" onClick={() => void updateGoal(r, p)}>{p}%</Button>)}</Inline> : '—' },
    ];
    const reviewColumns: DataGridColumn<Review>[] = [
        { id: 'employee', header: 'Employee', value: r => person(r.member), sortable: true },
        { id: 'cycle', header: 'Cycle', value: r => r.cycle.name, sortable: true },
        { id: 'self', header: 'Self', value: r => r.self_rating || '', cell: r => r.self_rating || '—', sortable: true },
        { id: 'manager', header: 'Manager', value: r => r.manager_rating || '', cell: r => r.manager_rating || '—', sortable: true },
        { id: 'overall', header: 'Overall', value: r => r.overall_rating || '', cell: r => r.overall_rating || '—', sortable: true },
        { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge>, sortable: true },
        { id: 'actions', header: 'Action', cell: r => ((r.member.id === data.current_member_id && !r.self_rating) || (data.can_manage_reviews && !r.manager_rating)) ? <Button size="sm" variant="ghost" onClick={() => { setSelectedReview(r); setReviewForm({ rating: '4', summary: '' }); setReviewOpen(true); }}>Review</Button> : '—' },
    ];
    const learningColumns: DataGridColumn<Enrollment>[] = [
        { id: 'employee', header: 'Employee', value: r => person(r.member), sortable: true },
        { id: 'course', header: 'Course', value: r => r.course.name, sortable: true },
        { id: 'status', header: 'Status', value: r => r.status, cell: r => <Badge tone={tone(r.status)}>{r.status}</Badge>, sortable: true, filter: { type: 'select', options: [{ label: 'Not started', value: 'not_started' }, { label: 'In progress', value: 'in_progress' }, { label: 'Completed', value: 'completed' }] } },
        { id: 'expiry', header: 'Expiry', value: r => r.expires_on || '', cell: r => r.expires_on?.slice(0, 10) || '—', sortable: true, filter: { type: 'dateRange' } },
    ];
    return <Page>
  <PageHeader title="Performance & Growth" description="Goals, reviews, 1:1s, skills, learning, recognition and development" actions={<Button variant="outline" size="sm" onClick={() => void load()}><RefreshCw size={13}/>Refresh</Button>}/>
  {error && <Alert tone="danger">{error}</Alert>}
  <Grid columns="repeat(4,minmax(0,1fr))" gap={10} m="14px 0"><StatCard label="Active goals" value={data.goals.filter(g => g.status === 'active' || g.status === 'at_risk').length} icon={<Target size={16}/>}/><StatCard label="Reviews" value={data.reviews.length} icon={<ClipboardCheck size={16}/>}/><StatCard label="Skills tracked" value={data.member_skills.length} icon={<Brain size={16}/>}/><StatCard label="Learning" value={data.enrollments.filter(e => e.status !== 'completed').length} icon={<BookOpen size={16}/>}/></Grid>
  <Tabs value={tab} onChange={setTab} tabs={[{ value: 'goals', label: 'Goals' }, { value: 'reviews', label: 'Reviews & 1:1s' }, { value: 'growth', label: 'Skills & Development' }, { value: 'learning', label: 'Learning' }, { value: 'recognition', label: 'Recognition' }, { value: 'surveys', label: 'Pulse Surveys' }, ...(data.can_manage_compensation ? [{ value: 'comp' as const, label: 'Compensation' }] : [])]}/>

  {tab === 'goals' && <Card mt={12}><CardHeader title={data.can_manage ? 'Goals' : 'My Goals'} description="Progress is stored with update history." action={<Button size="sm" onClick={() => { setGoal({ ...goal, member_id: String(data.current_member_id) }); setGoalOpen(true); }}><Plus size={13}/>Goal</Button>}/><CardBody><DataGrid rows={data.goals} columns={goalColumns} rowKey={r => r.id} persistKey="performance.goals" searchable searchPlaceholder="Search goals or employees" defaultSort={{ id: 'due', direction: 'asc' }} empty="No goals yet." ariaLabel="Performance goals"/></CardBody></Card>}
  {tab === 'reviews' && <Stack gap={12} mt={12}>{data.can_manage_reviews && <Card><CardHeader title="Review Cycles" description="Launch a cycle to create employee review records." action={<Button size="sm" onClick={() => setCycleOpen(true)}><Plus size={13}/>Cycle</Button>}/><CardBody>{data.review_cycles.map(c => <div key={c.id} className="schedule-list-row"><div><strong>{c.name}</strong><small>{c.start_date.slice(0, 10)} → {c.end_date.slice(0, 10)} · {c.reviews_count} reviews</small></div><Inline gap={6} align="center"><Badge tone={tone(c.status)}>{c.status}</Badge>{c.status === 'draft' && <Button size="sm" onClick={() => void launch(c)}>Launch</Button>}</Inline></div>)}</CardBody></Card>}<Card><CardHeader title="Reviews"/><CardBody><DataGrid rows={data.reviews} columns={reviewColumns} rowKey={r => r.id} persistKey="performance.reviews" searchable searchPlaceholder="Search reviews" defaultSort={{ id: 'employee', direction: 'asc' }} empty="No reviews in scope." ariaLabel="Performance reviews"/></CardBody></Card><Card><CardHeader title="1:1 Meetings"/><CardBody>{data.one_on_ones.map(o => <div key={o.id} className="schedule-list-row"><div><strong>{person(o.member)} ↔ {person(o.manager)}</strong><small>{new Date(o.scheduled_at).toLocaleString()}</small></div><Badge tone={tone(o.status)}>{o.status}</Badge></div>)}</CardBody></Card></Stack>}
  {tab === 'growth' && <Grid columns="1fr 1fr" gap={12} mt={12}><Card><CardHeader title="Skills Matrix"/><CardBody>{data.member_skills.map(s => <div key={s.id} className="schedule-list-row"><div><strong>{person(s.member)} · {s.skill.name}</strong><small>{s.skill.category || 'General'}</small></div><Badge>{s.proficiency}/{s.skill.max_proficiency}{s.target_proficiency ? ` → ${s.target_proficiency}` : ''}</Badge></div>)}</CardBody></Card><Card><CardHeader title="Development Plans"/><CardBody>{data.development_plans.map(p => <Box key={p.id} p="9px 0" borderBottom="1px solid var(--border-muted)"><strong>{p.title}</strong><div className="ui-card-description">{person(p.member)} · target {p.target_date?.slice(0, 10) || 'open'}</div><div className="ui-card-description">{p.objective}</div></Box>)}</CardBody></Card></Grid>}
  {tab === 'learning' && <Card mt={12}><CardHeader title="Learning & Certifications"/><CardBody><DataGrid rows={data.enrollments} columns={learningColumns} rowKey={r => r.id} persistKey="performance.learning" searchable searchPlaceholder="Search learning records" defaultSort={{ id: 'expiry', direction: 'asc' }} empty="No learning enrollments." ariaLabel="Learning and certification records"/></CardBody></Card>}
  {tab === 'recognition' && <Card mt={12}><CardHeader title="Recognition" description="Visible appreciation is separate from performance ratings." action={<Button size="sm" onClick={() => { setRec({ ...rec, recipient_member_id: String(data.people.find(p => p.id !== data.current_member_id)?.id || '') }); setRecOpen(true); }}><Award size={13}/>Recognize</Button>}/><CardBody>{data.recognitions.map(r => <Box key={r.id} p={10} borderBottom="1px solid var(--border-muted)"><Inline justify="space-between"><strong>{r.title}</strong><Badge tone="accent">{r.points} pts</Badge></Inline><div className="ui-card-description">{person(r.sender)} → {person(r.recipient)}</div><Text as="p" size={12} mt={4}>{r.message}</Text></Box>)}</CardBody></Card>}
  {tab === 'surveys' && <Card mt={12}><CardHeader title="Pulse Surveys" description="Anonymous surveys never store member_id with the response."/><CardBody>{data.surveys.map(s => <div key={s.id} className="schedule-list-row"><div><strong>{s.title}</strong><small>{s.questions.length} questions · {s.anonymous ? 'anonymous' : 'identified'}</small></div><Inline gap={6} align="center"><Badge tone={tone(s.status)}>{s.status}</Badge>{s.status === 'active' && <Button size="sm" variant="ghost" onClick={() => { setSelectedSurvey(s); setSurveyAnswers({}); setSurveyOpen(true); }}>Respond</Button>}</Inline></div>)}</CardBody></Card>}
  {tab === 'comp' && data.can_manage_compensation && <Card mt={12}><CardHeader title="Compensation & Promotion Reviews" description="Proposals are review records; they do not silently mutate payroll compensation."/><CardBody>{(data.compensation_cycles || []).map(c => <Box key={c.id} mb={14}><Inline justify="space-between"><strong>{c.name}</strong><span>{c.currency} {c.budget_amount || '—'}</span></Inline>{c.items.map(i => <div key={i.id} className="schedule-list-row"><div><strong>{person(i.member)}</strong><small>{i.proposed_title || 'No title change'} · {i.proposed_amount || 'No amount proposed'}</small></div><Badge tone={tone(i.status)}>{i.status}</Badge></div>)}</Box>)}</CardBody></Card>}

  <FormDialog open={goalOpen} onClose={() => !saving && setGoalOpen(false)} title="Create goal" description="Create a measurable goal with an optional due date." formId="performance-goal-form" onSubmit={saveGoal} submitLabel="Create" loading={saving}>{data.can_manage && <Field label="Employee"><Select value={goal.member_id} onChange={e => setGoal({ ...goal, member_id: e.target.value })}>{data.people.map(p => <Option key={p.id} value={p.id}>{person(p)}</Option>)}</Select></Field>}<Field label="Title"><Input value={goal.title} onChange={e => setGoal({ ...goal, title: e.target.value })} required/></Field><Field label="Description"><Textarea value={goal.description} onChange={e => setGoal({ ...goal, description: e.target.value })}/></Field><Field label="Due date"><Input type="date" value={goal.due_date} onChange={e => setGoal({ ...goal, due_date: e.target.value })}/></Field></FormDialog>
  <FormDialog open={recOpen} onClose={() => !saving && setRecOpen(false)} title="Recognize a colleague" description="Send visible peer recognition without changing performance ratings." formId="performance-recognition-form" onSubmit={saveRec} submitLabel="Send" loading={saving}><Field label="Recipient"><Select value={rec.recipient_member_id} onChange={e => setRec({ ...rec, recipient_member_id: e.target.value })}>{data.people.filter(p => p.id !== data.current_member_id).map(p => <Option key={p.id} value={p.id}>{person(p)}</Option>)}</Select></Field><Field label="Title"><Input value={rec.title} onChange={e => setRec({ ...rec, title: e.target.value })} required/></Field><Field label="Message"><Textarea value={rec.message} onChange={e => setRec({ ...rec, message: e.target.value })} required/></Field><Field label="Points"><Input type="number" min="0" value={rec.points} onChange={e => setRec({ ...rec, points: e.target.value })}/></Field></FormDialog>
  <FormDialog open={cycleOpen} onClose={() => !saving && setCycleOpen(false)} title="Create review cycle" description="Define the review period before launching employee reviews." formId="performance-cycle-form" onSubmit={saveCycle} submitLabel="Create" loading={saving}><Field label="Name"><Input value={cycle.name} onChange={e => setCycle({ ...cycle, name: e.target.value })} required/></Field><Grid columns="1fr 1fr" gap={8}><Field label="Starts"><Input type="date" value={cycle.start_date} onChange={e => setCycle({ ...cycle, start_date: e.target.value })} required/></Field><Field label="Ends"><Input type="date" min={cycle.start_date} value={cycle.end_date} onChange={e => setCycle({ ...cycle, end_date: e.target.value })} required/></Field></Grid></FormDialog>
  <FormDialog open={reviewOpen} onClose={() => !saving && setReviewOpen(false)} title="Submit review" description={selectedReview ? `Review for ${person(selectedReview.member)} · ${selectedReview.cycle.name}` : ''} formId="performance-review-form" onSubmit={submitReview} submitLabel="Submit" loading={saving}><Field label="Rating (1–5)"><Input type="number" min="1" max="5" step="0.1" value={reviewForm.rating} onChange={e => setReviewForm({ ...reviewForm, rating: e.target.value })} required/></Field><Field label="Summary"><Textarea rows={5} value={reviewForm.summary} onChange={e => setReviewForm({ ...reviewForm, summary: e.target.value })}/></Field></FormDialog>
  <FormDialog open={surveyOpen} onClose={() => !saving && setSurveyOpen(false)} title={selectedSurvey?.title || 'Pulse survey'} description={selectedSurvey?.anonymous ? 'This survey is anonymous.' : 'Your response is linked to your employee record.'} formId="performance-survey-form" onSubmit={respondSurvey} submitLabel="Submit" loading={saving}>{selectedSurvey?.questions.map(q => <Field key={q.id} label={q.question}>{q.question_type === 'rating' ? <Input type="number" min="1" max="5" value={surveyAnswers[q.id] || '4'} onChange={e => setSurveyAnswers(v => ({ ...v, [q.id]: e.target.value }))}/> : <Textarea value={surveyAnswers[q.id] || ''} onChange={e => setSurveyAnswers(v => ({ ...v, [q.id]: e.target.value }))}/>}</Field>)}</FormDialog>
 </Page>;
}
