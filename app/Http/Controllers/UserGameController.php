<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Models\UserGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Laravel\Facades\Image;

class UserGameController extends Controller
{
    public function index(Request $request, User $user): View
    {
        $collection = $user->gameCollection;
        $isOwner = auth()->check() && (auth()->id() === $user->id || auth()->user()->isAdmin());

        // Default filter to 'playing'
        $statusFilter = $request->query('status');
        $wishlistFilter = $request->boolean('wishlist');
        $sortBy = $request->query('sort', 'date_added');

        // Check privacy for non-owners
        if (! $isOwner && $collection) {
            $filterToCheck = $wishlistFilter ? 'wishlist' : $statusFilter;
            if ($filterToCheck && ! $collection->isStatusPublic($filterToCheck)) {
                abort(403, 'This section is private.');
            }
        }

        // Build query
        $query = UserGame::where('user_id', $user->id)
            ->with(['game' => fn ($q) => $q->with(['platforms', 'genres'])]);

        if ($wishlistFilter) {
            $query->wishlisted();
        } elseif ($statusFilter && in_array($statusFilter, ['playing', 'played', 'backlog'])) {
            $query->withStatus($statusFilter);
        } else {
            // Default: show 'playing'
            $statusFilter = 'playing';
            $query->playing();
        }

        // Apply sorting
        $query = match ($sortBy) {
            'alpha' => $query->join('games', 'user_games.game_id', '=', 'games.id')
                ->orderBy('games.name')
                ->select('user_games.*'),
            'release_date' => $query->join('games', 'user_games.game_id', '=', 'games.id')
                ->orderByDesc('games.first_release_date')
                ->select('user_games.*'),
            'time_played' => $query->orderByDesc('time_played'),
            'rating' => $query->orderByDesc('rating'),
            'manual' => $query->orderBy('sort_order'),
            default => $query->orderByDesc('added_at'), // date_added
        };

        $games = $query->get();

        // Stats
        $stats = [
            'total' => UserGame::where('user_id', $user->id)->count(),
            'playing' => UserGame::where('user_id', $user->id)->playing()->count(),
            'played' => UserGame::where('user_id', $user->id)->played()->count(),
            'backlog' => UserGame::where('user_id', $user->id)->backlog()->count(),
            'wishlist' => UserGame::where('user_id', $user->id)->wishlisted()->count(),
            'total_hours' => (float) UserGame::where('user_id', $user->id)->sum('time_played'),
        ];

        $viewMode = session('game_view_mode', 'grid');

        return view('user-games.index', compact(
            'user',
            'collection',
            'games',
            'stats',
            'statusFilter',
            'wishlistFilter',
            'sortBy',
            'isOwner',
            'viewMode',
        ));
    }

    public function settings(User $user): View
    {
        $collection = $user->getOrCreateGameCollection();

        return view('user-games.settings', compact('user', 'collection'));
    }

    public function updateSettings(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'privacy_playing' => ['nullable'],
            'privacy_played' => ['nullable'],
            'privacy_backlog' => ['nullable'],
            'privacy_wishlist' => ['nullable'],
        ]);

        $collection = $user->getOrCreateGameCollection();

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'privacy_playing' => $request->has('privacy_playing'),
            'privacy_played' => $request->has('privacy_played'),
            'privacy_backlog' => $request->has('privacy_backlog'),
            'privacy_wishlist' => $request->has('privacy_wishlist'),
        ];

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old image
            if ($collection->cover_image_path) {
                Storage::disk('public')->delete($collection->cover_image_path);
            }

            $path = $request->file('cover_image')->store('user-collections/'.$user->id, 'public');
            $data['cover_image_path'] = $path;
        }

        $collection->update($data);

        return redirect()->route('user.games', $user->username)
            ->with('success', 'Collection settings updated.');
    }

    public function store(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $request->validate([
            'game_id' => ['required'],
            'status' => ['nullable', 'string', 'in:playing,played,backlog'],
            'is_wishlisted' => ['nullable', 'boolean'],
        ]);

        // Find or fetch game
        $game = Game::where('igdb_id', $request->game_id)->first()
            ?? Game::find($request->game_id);

        if (! $game) {
            $igdbService = app(\App\Services\IgdbService::class);
            $game = Game::fetchFromIgdbIfMissing((int) $request->game_id, $igdbService);

            if (! $game) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Game not found.'], 404);
                }

                return redirect()->back()->with('error', 'Game not found.');
            }
        }

        // Check if already in collection
        $existing = UserGame::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->first();

        if ($existing) {
            if ($request->wantsJson()) {
                return response()->json(['info' => 'Game already in collection.']);
            }

            return redirect()->back()->with('info', 'Game already in collection.');
        }

        // Ensure collection exists
        $user->getOrCreateGameCollection();

        $maxOrder = UserGame::where('user_id', $user->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->max('sort_order') ?? 0;

        UserGame::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'status' => $request->status,
            'is_wishlisted' => $request->boolean('is_wishlisted'),
            'sort_order' => $maxOrder + 1,
            'added_at' => now(),
            'status_changed_at' => $request->status ? now() : null,
            'wishlisted_at' => $request->boolean('is_wishlisted') ? now() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Game added to collection.']);
        }

        return redirect()->back()->with('success', 'Game added to collection.');
    }

    public function update(Request $request, User $user, UserGame $userGame): RedirectResponse|JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:playing,played,backlog'],
            'is_wishlisted' => ['nullable', 'boolean'],
            'time_played' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $data = $request->only(['status', 'time_played', 'rating']);

        if ($request->has('status') && $request->status !== $userGame->status?->value) {
            $data['status_changed_at'] = now();
        }

        if ($request->has('is_wishlisted')) {
            $wishlisted = $request->boolean('is_wishlisted');
            $data['is_wishlisted'] = $wishlisted;
            if ($wishlisted && ! $userGame->is_wishlisted) {
                $data['wishlisted_at'] = now();
            }
        }

        $userGame->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Game updated.');
    }

    public function destroy(Request $request, User $user, UserGame $userGame): RedirectResponse|JsonResponse
    {
        $userGame->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Game removed.']);
        }

        return redirect()->back()->with('success', 'Game removed from collection.');
    }

    public function reorder(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->items as $item) {
            UserGame::where('id', $item['id'])
                ->where('user_id', $user->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}
