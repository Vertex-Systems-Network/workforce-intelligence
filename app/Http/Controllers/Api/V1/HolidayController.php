<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provides holiday controller behavior within the WorkIntel application. */ class HolidayController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $year = (int) ($request->query('year') ?: now($workspace->timezone)->year);
        $holidays = Holiday::query()->where('workspace_id', $workspace->id)->whereYear('date', $year)->orderBy('date')->get();
        return response()->json(['data' => $holidays, 'year' => $year]);
    }

    /** Creates and persists the requested resource. */ public function store(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->rules($request);
        $holiday = Holiday::create(['workspace_id' => $workspace->id, ...$data]);
        return response()->json(['data' => $holiday], 201);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($holiday->workspace_id === $workspace->id, 404);
        $holiday->update($this->rules($request));
        return response()->json(['data' => $holiday->fresh()]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($holiday->workspace_id === $workspace->id, 404);
        $holiday->delete();
        return response()->json(['message' => 'Holiday deleted.']);
    }

    /** Defines validation rules for the incoming request. */ private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['public', 'company', 'optional'])],
            'paid' => ['required', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }
}
