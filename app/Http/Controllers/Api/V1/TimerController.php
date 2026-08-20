<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TimeSource;
use App\Enums\TimerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timer\StartTimerRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\TimeSession;
use App\Services\Access\WorkScopeService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides timer controller behavior within the WorkIntel application. */ class TimerController extends Controller
{
    /** Handles the current operation for the current WorkIntel workflow. */ public function current(Request $request): JsonResponse
    {
        $member = $request->attributes->get('workspaceMember');

        $session = TimeSession::query()
            ->with(['project:id,name', 'task:id,title', 'events:id,time_session_id,event_type,occurred_at'])
            ->where('member_id', $member->id)
            ->whereIn('status', [TimerStatus::Running->value, TimerStatus::Paused->value])
            ->latest('started_at')
            ->first();

        return response()->json(['timer' => $session]);
    }

    /** Handles the start operation for the current WorkIntel workflow. */ public function start(StartTimerRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validated();

        $alreadyRunning = TimeSession::query()
            ->where('member_id', $member->id)
            ->whereIn('status', [TimerStatus::Running->value, TimerStatus::Paused->value])
            ->exists();

        if ($alreadyRunning) {
            throw ValidationException::withMessages(['timer' => ['Stop the current timer before starting another one.']]);
        }

        $project = null;
        $task = null;

        if (! empty($data['project_id'])) {
            $project = Project::query()->where('workspace_id', $workspace->id)->findOrFail($data['project_id']);
        }

        if (! empty($data['task_id'])) {
            $task = Task::query()->where('workspace_id', $workspace->id)->findOrFail($data['task_id']);
            if ($project && $task->project_id !== $project->id) {
                throw ValidationException::withMessages([
                    'task_id' => ['The selected task does not belong to the selected project.'],
                ]);
            }
            $project ??= $task->project;
        }

        if ($project) abort_unless(app(WorkScopeService::class)->canViewProject($member, $project), 403, 'This project is outside your allowed work scope.');
        if ($task) abort_unless(app(WorkScopeService::class)->canViewTask($member, $task), 403, 'This task is outside your allowed work scope.');

        $session = DB::transaction(function () use ($workspace, $member, $data, $project, $task) {
            $session = TimeSession::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'member_id' => $member->id,
                'project_id' => $project?->id,
                'task_id' => $task?->id,
                'started_at' => now(),
                'status' => TimerStatus::Running,
                'source' => TimeSource::Web,
                'billable' => $data['billable'] ?? true,
                'note' => $data['note'] ?? null,
            ]);

            $session->events()->create([
                'event_type' => 'timer.started',
                'occurred_at' => now(),
            ]);

            return $session;
        });

        return response()->json(['timer' => $session->load(['project:id,name', 'task:id,title', 'events:id,time_session_id,event_type,occurred_at'])], 201);
    }

    /** Handles the pause operation for the current WorkIntel workflow. */ public function pause(Request $request, TimeSession $session): JsonResponse
    {
        $this->ensureOwnSession($request, $session);
        abort_unless($session->status === TimerStatus::Running, 422, 'Only a running timer can be paused.');

        $session->update(['status' => TimerStatus::Paused]);
        $session->events()->create(['event_type' => 'timer.paused', 'occurred_at' => now()]);

        return response()->json(['timer' => $session->fresh()->load(['project:id,name', 'task:id,title', 'events:id,time_session_id,event_type,occurred_at'])]);
    }

    /** Handles the resume operation for the current WorkIntel workflow. */ public function resume(Request $request, TimeSession $session): JsonResponse
    {
        $this->ensureOwnSession($request, $session);
        abort_unless($session->status === TimerStatus::Paused, 422, 'Only a paused timer can be resumed.');

        $session->update(['status' => TimerStatus::Running]);
        $session->events()->create(['event_type' => 'timer.resumed', 'occurred_at' => now()]);

        return response()->json(['timer' => $session->fresh()->load(['project:id,name', 'task:id,title', 'events:id,time_session_id,event_type,occurred_at'])]);
    }

    /** Handles the stop operation for the current WorkIntel workflow. */ public function stop(Request $request, TimeSession $session): JsonResponse
    {
        $this->ensureOwnSession($request, $session);
        abort_if($session->status === TimerStatus::Stopped, 422, 'This timer is already stopped.');

        $entry = DB::transaction(function () use ($session) {
            $stoppedAt = now();
            $durationSeconds = $this->trackedSeconds($session, $stoppedAt);

            $session->update([
                'status' => TimerStatus::Stopped,
                'stopped_at' => $stoppedAt,
            ]);

            $session->events()->create([
                'event_type' => 'timer.stopped',
                'occurred_at' => $stoppedAt,
            ]);

            return TimeEntry::create([
                'workspace_id' => $session->workspace_id,
                'member_id' => $session->member_id,
                'project_id' => $session->project_id,
                'task_id' => $session->task_id,
                'time_session_id' => $session->id,
                'date' => $session->started_at->toDateString(),
                'started_at' => $session->started_at,
                'ended_at' => $stoppedAt,
                'duration_seconds' => $durationSeconds,
                'billable' => $session->billable,
                'source' => $session->source->value,
                'approval_status' => 'draft',
                'note' => $session->note,
            ]);
        });

        return response()->json(['entry' => $entry]);
    }

    /** Handles the tracked seconds operation for the current WorkIntel workflow. */ private function trackedSeconds(TimeSession $session, CarbonInterface $stoppedAt): int
    {
        $elapsed = max(0, (int) $session->started_at->diffInSeconds($stoppedAt, true));
        $events = $session->events()->orderBy('occurred_at')->get();
        $pausedAt = null;
        $pausedSeconds = 0;

        foreach ($events as $event) {
            if ($event->event_type === 'timer.paused') {
                $pausedAt = $event->occurred_at;
                continue;
            }

            if ($event->event_type === 'timer.resumed' && $pausedAt) {
                $pausedSeconds += (int) $pausedAt->diffInSeconds($event->occurred_at, true);
                $pausedAt = null;
            }
        }

        // A user may stop the timer while it is still paused.
        if ($pausedAt) {
            $pausedSeconds += (int) $pausedAt->diffInSeconds($stoppedAt);
        }

        return max(0, $elapsed - $pausedSeconds);
    }

    /** Handles the ensure own session operation for the current WorkIntel workflow. */ private function ensureOwnSession(Request $request, TimeSession $session): void
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');

        abort_unless(
            $session->workspace_id === $workspace->id && $session->member_id === $member->id,
            404
        );
    }
}
