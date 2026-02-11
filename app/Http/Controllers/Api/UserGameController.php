<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\UserGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserGameController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'status' => ['nullable', 'string', 'in:playing,played,backlog'],
            'is_wishlisted' => ['nullable', 'boolean'],
        ]);

        $user = auth()->user();

        $existing = UserGame::where('user_id', $user->id)
            ->where('game_id', $request->game_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Game already in collection.',
                'user_game' => $this->formatUserGame($existing),
            ]);
        }

        // Ensure collection exists (lazy creation)
        $user->getOrCreateGameCollection();

        $maxOrder = UserGame::where('user_id', $user->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->max('sort_order') ?? 0;

        $userGame = UserGame::create([
            'user_id' => $user->id,
            'game_id' => $request->game_id,
            'status' => $request->status,
            'is_wishlisted' => $request->boolean('is_wishlisted'),
            'sort_order' => $maxOrder + 1,
            'added_at' => now(),
            'status_changed_at' => $request->status ? now() : null,
            'wishlisted_at' => $request->boolean('is_wishlisted') ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Game added to collection.',
            'user_game' => $this->formatUserGame($userGame),
        ], 201);
    }

    public function update(Request $request, UserGame $userGame): JsonResponse
    {
        if (auth()->id() !== $userGame->user_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:playing,played,backlog'],
            'is_wishlisted' => ['nullable', 'boolean'],
            'time_played' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $data = [];

        if ($request->has('status')) {
            $newStatus = $request->status;
            if ($newStatus !== $userGame->status?->value) {
                $data['status'] = $newStatus;
                $data['status_changed_at'] = now();
            }
        }

        if ($request->has('is_wishlisted')) {
            $wishlisted = $request->boolean('is_wishlisted');
            if ($wishlisted !== $userGame->is_wishlisted) {
                $data['is_wishlisted'] = $wishlisted;
                $data['wishlisted_at'] = $wishlisted ? now() : $userGame->wishlisted_at;
            }
        }

        if ($request->has('time_played')) {
            $data['time_played'] = $request->time_played;
        }

        if ($request->has('rating')) {
            $data['rating'] = $request->rating;
        }

        if (! empty($data)) {
            $userGame->update($data);
        }

        return response()->json([
            'success' => true,
            'user_game' => $this->formatUserGame($userGame->fresh()),
        ]);
    }

    public function destroy(UserGame $userGame): JsonResponse
    {
        if (auth()->id() !== $userGame->user_id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $userGame->delete();

        return response()->json([
            'success' => true,
            'message' => 'Game removed from collection.',
        ]);
    }

    public function status(Game $game): JsonResponse
    {
        $user = auth()->user();

        $userGame = UserGame::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->first();

        if (! $userGame) {
            return response()->json(['user_game' => null]);
        }

        return response()->json([
            'user_game' => $this->formatUserGame($userGame),
        ]);
    }

    protected function formatUserGame(UserGame $userGame): array
    {
        return [
            'id' => $userGame->id,
            'game_id' => $userGame->game_id,
            'status' => $userGame->status?->value,
            'status_label' => $userGame->status?->label(),
            'is_wishlisted' => $userGame->is_wishlisted,
            'time_played' => $userGame->time_played,
            'time_played_formatted' => $userGame->getFormattedTimePlayed(),
            'rating' => $userGame->rating,
            'sort_order' => $userGame->sort_order,
            'added_at' => $userGame->added_at?->toISOString(),
        ];
    }
}
