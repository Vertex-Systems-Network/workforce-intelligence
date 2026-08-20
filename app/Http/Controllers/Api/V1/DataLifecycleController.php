<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Lifecycle\DataLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides the centralized Trash Center API for recoverable workspace data. */
class DataLifecycleController extends Controller
{
    /** Injects the shared recoverable-data lifecycle service. */
    public function __construct(private readonly DataLifecycleService $lifecycle) {}

    /** Returns trashed resources visible to the requesting member. */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $type = $request->query('type');
        return response()->json(['data' => $this->lifecycle->trashItems($workspace, $request->attributes->get('workspaceMember'), $type ?: null), 'types' => $this->lifecycle->supportedTypes()]);
    }

    /** Moves one supported active resource to Trash. */
    public function trash(Request $request, string $type, int $id): JsonResponse
    {
        $item = $this->lifecycle->trash($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $type, $id);
        return response()->json(['data' => $item, 'message' => $item['type_label'].' moved to Trash.']);
    }

    /** Restores one supported resource from Trash. */
    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->attributes->get('workspaceMember')->hasPermission('trash.restore'), 403);
        $item = $this->lifecycle->restore($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $type, $id);
        return response()->json(['data' => $item, 'message' => $item['type_label'].' restored.']);
    }

    /** Permanently deletes one trashed resource after all dependency checks pass. */
    public function purge(Request $request, string $type, int $id): JsonResponse
    {
        $this->lifecycle->purge($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $type, $id);
        return response()->json(['message' => 'Trashed record permanently deleted.']);
    }
}
