<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncPcPortsRequest;
use App\Models\PcPort;
use App\Services\PcPortSyncService;
use Illuminate\Http\JsonResponse;

class PcPortController extends Controller
{
    public function index(): JsonResponse
    {
        $ports = PcPort::orderBy('slug')
            ->get()
            ->map(fn (PcPort $port): array => [
                'slug' => $port->slug,
                'game_title' => $port->game_title,
                'category' => $port->category->value,
                'status' => $port->status->value,
                'last_checked_at' => $port->last_checked_at?->toDateString(),
                'has_cover' => (bool) $port->cover_path,
            ]);

        return response()->json(['ports' => $ports]);
    }

    public function sync(SyncPcPortsRequest $request, PcPortSyncService $syncService): JsonResponse
    {
        $summary = $syncService->sync($request->validated('ports'), $request->boolean('prune'));

        return response()->json([
            'page_url' => route('ports'),
            'summary' => $summary,
        ]);
    }
}
