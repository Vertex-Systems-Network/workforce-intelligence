<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides client controller behavior within the WorkIntel application. */ class ClientController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $clients = Client::query()
            ->withCount(['projects', 'portalAccounts', 'invoices', 'reports', 'projects as active_projects_count' => fn ($query) => $query->where('status', 'active')])
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $clients]);
    }

    /** Creates and persists the requested resource. */ public function store(ClientRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        app(EntitlementService::class)->assertWithinLimit($workspace, 'clients', $workspace->clients()->where('status', '!=', 'archived')->count());

        $client = Client::create([
            'workspace_id' => $workspace->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $client->loadCount(['projects'])], 201);
    }

    /** Updates update data for the requested resource. */ public function update(ClientRequest $request, Client $client): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceClient($workspace->id, $client);
        $data = $request->validated();

        if ($client->status === 'archived' && ($data['status'] ?? null) !== 'archived') {
            app(EntitlementService::class)->assertWithinLimit($workspace, 'clients', $workspace->clients()->where('status', '!=', 'archived')->count());
        }

        $client->update($data);

        return response()->json(['data' => $client->fresh()->loadCount(['projects'])]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Client $client): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceClient($workspace->id, $client);

        $client->update(['status' => 'archived']);

        return response()->json(['message' => 'Client archived.']);
    }

    /** Handles the ensure workspace client operation for the current WorkIntel workflow. */ private function ensureWorkspaceClient(int $workspaceId, Client $client): void
    {
        abort_unless($client->workspace_id === $workspaceId, 404);
    }
}
