<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminGenreController extends Controller
{
    public function index(): View
    {
        $genres = Genre::query()
            ->where('is_pending_review', false)
            ->ordered()
            ->get()
            ->map(function ($genre) {
                $genre->indie_list_count = $this->getListTypeUsageCount($genre, 'indie_games');
                $genre->monthly_list_count = $this->getListTypeUsageCount($genre, 'monthly');
                $genre->usage_count = $genre->getUsageCount();

                return $genre;
            });

        $pendingGenres = Genre::pendingReview()->ordered()->get();

        return view('admin.genres.index', compact('genres', 'pendingGenres'));
    }

    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return back()->with('success', 'Genre created successfully.');
    }

    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return back()->with('success', 'Genre updated successfully.');
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        if (! $genre->canBeDeleted()) {
            return back()->with('error', 'Cannot delete this genre. It is either a system genre or is currently in use.');
        }

        $genre->delete();

        return back()->with('success', 'Genre deleted successfully.');
    }

    public function approve(Genre $genre): RedirectResponse
    {
        $genre->update(['is_pending_review' => false, 'is_visible' => true]);

        return back()->with('success', 'Genre approved.');
    }

    public function reject(Genre $genre): RedirectResponse
    {
        if ($genre->is_pending_review) {
            $genre->delete();
        }

        return back()->with('success', 'Genre rejected and removed.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $order = $request->validate(['order' => 'required|array'])['order'];

        foreach ($order as $index => $genreId) {
            Genre::where('id', $genreId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_genre_id' => 'required|exists:genres,id',
            'target_genre_id' => 'required|exists:genres,id|different:source_genre_id',
        ]);

        $source = Genre::findOrFail($data['source_genre_id']);
        $target = Genre::findOrFail($data['target_genre_id']);

        if ($source->isProtected()) {
            return back()->with('error', 'Cannot merge a protected genre.');
        }

        DB::transaction(function () use ($source, $target) {
            DB::table('game_list_game')
                ->whereJsonContains('genre_ids', $source->id)
                ->get()
                ->each(function ($row) use ($source, $target) {
                    $genreIds = json_decode($row->genre_ids, true) ?? [];
                    $genreIds = array_map(fn ($id) => $id === $source->id ? $target->id : $id, $genreIds);
                    $genreIds = array_unique($genreIds);

                    DB::table('game_list_game')
                        ->where('id', $row->id)
                        ->update(['genre_ids' => json_encode(array_values($genreIds))]);
                });

            DB::table('game_list_game')
                ->where('primary_genre_id', $source->id)
                ->update(['primary_genre_id' => $target->id]);

            $source->delete();
        });

        return back()->with('success', "Genre '{$source->name}' merged into '{$target->name}'.");
    }

    public function toggleVisibility(Genre $genre): RedirectResponse
    {
        if ($genre->isProtected()) {
            return back()->with('error', 'Cannot hide a protected system genre.');
        }

        $genre->update(['is_visible' => ! $genre->is_visible]);

        $status = $genre->is_visible ? 'visible' : 'hidden';

        return back()->with('success', "Genre is now {$status}.");
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        $genres = Genre::visible()
            ->where('is_pending_review', false)
            ->when($query, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->ordered()
            ->limit(20)
            ->get(['id', 'name', 'slug']);

        return response()->json($genres);
    }

    public function bulkRemove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'genre_id' => 'required|exists:genres,id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        $genre = Genre::findOrFail($data['genre_id']);
        $list = \App\Models\GameList::findOrFail($data['list_id']);

        $affectedRows = 0;

        DB::table('game_list_game')
            ->where('game_list_id', $list->id)
            ->whereJsonContains('genre_ids', $genre->id)
            ->get()
            ->each(function ($row) use ($genre, &$affectedRows) {
                $genreIds = json_decode($row->genre_ids, true) ?? [];
                $genreIds = array_filter($genreIds, fn ($id) => $id !== $genre->id);

                $primaryGenreId = $row->primary_genre_id === $genre->id ? null : $row->primary_genre_id;

                DB::table('game_list_game')
                    ->where('id', $row->id)
                    ->update([
                        'genre_ids' => json_encode(array_values($genreIds)),
                        'primary_genre_id' => $primaryGenreId,
                    ]);

                $affectedRows++;
            });

        return back()->with('success', "Genre removed from {$affectedRows} games in '{$list->name}'.");
    }

    public function bulkReplace(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_genre_id' => 'required|exists:genres,id',
            'target_genre_id' => 'required|exists:genres,id|different:source_genre_id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        $source = Genre::findOrFail($data['source_genre_id']);
        $target = Genre::findOrFail($data['target_genre_id']);
        $list = \App\Models\GameList::findOrFail($data['list_id']);

        $affectedRows = 0;

        DB::table('game_list_game')
            ->where('game_list_id', $list->id)
            ->whereJsonContains('genre_ids', $source->id)
            ->get()
            ->each(function ($row) use ($source, $target, &$affectedRows) {
                $genreIds = json_decode($row->genre_ids, true) ?? [];
                $genreIds = array_map(fn ($id) => $id === $source->id ? $target->id : $id, $genreIds);
                $genreIds = array_unique($genreIds);

                $primaryGenreId = $row->primary_genre_id === $source->id ? $target->id : $row->primary_genre_id;

                DB::table('game_list_game')
                    ->where('id', $row->id)
                    ->update([
                        'genre_ids' => json_encode(array_values($genreIds)),
                        'primary_genre_id' => $primaryGenreId,
                    ]);

                $affectedRows++;
            });

        return back()->with('success', "Replaced '{$source->name}' with '{$target->name}' in {$affectedRows} games.");
    }

    public function assignGames(Request $request, Genre $genre): RedirectResponse
    {
        $data = $request->validate([
            'game_ids' => 'required|array',
            'game_ids.*' => 'exists:games,id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        $assignedCount = 0;

        foreach ($data['game_ids'] as $gameId) {
            $pivot = DB::table('game_list_game')
                ->where('game_list_id', $data['list_id'])
                ->where('game_id', $gameId)
                ->first();

            if ($pivot) {
                $genreIds = json_decode($pivot->genre_ids, true) ?? [];
                if (! in_array($genre->id, $genreIds) && count($genreIds) < 3) {
                    $genreIds[] = $genre->id;
                    DB::table('game_list_game')
                        ->where('id', $pivot->id)
                        ->update(['genre_ids' => json_encode($genreIds)]);
                    $assignedCount++;
                }
            }
        }

        return back()->with('success', "Genre assigned to {$assignedCount} games.");
    }

    private function getListTypeUsageCount(Genre $genre, string $listType): int
    {
        return DB::table('game_list_game')
            ->join('game_lists', 'game_list_game.game_list_id', '=', 'game_lists.id')
            ->where('game_lists.list_type', $listType)
            ->where(function ($q) use ($genre) {
                $q->where('primary_genre_id', $genre->id)
                    ->orWhereJsonContains('genre_ids', $genre->id);
            })
            ->count();
    }
}
