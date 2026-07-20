<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportCheckRequest;
use App\Http\Requests\Api\ImportListItemsRequest;
use App\Models\Game;
use App\Models\GameList;
use App\Services\GameListImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GameListImportController extends Controller
{
    /**
     * Report whether each item already exists locally and on which system lists.
     */
    public function check(ImportCheckRequest $request): JsonResponse
    {
        $targetListSlug = $request->input('list_slug');
        $stagingListSlug = $targetListSlug !== null
            ? GameList::where('slug', $targetListSlug)->where('is_system', true)->first()
                ?->importStagingList()->value('slug')
            : null;

        $results = collect($request->validated('items'))->map(function (array $item) use ($targetListSlug, $stagingListSlug): array {
            $game = null;

            if (! empty($item['igdb_id'])) {
                $game = Game::where('igdb_id', $item['igdb_id'])->first();
            }

            $game ??= Game::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($item['name']))])->first();

            if (! $game) {
                return [
                    'name' => $item['name'],
                    'exists' => false,
                ];
            }

            $lists = $game->gameLists()
                ->where('is_system', true)
                ->get()
                ->map(fn (GameList $list): array => [
                    'slug' => $list->slug,
                    'name' => $list->name,
                    'list_type' => $list->list_type->value,
                ])
                ->values();

            return [
                'name' => $item['name'],
                'exists' => true,
                'igdb_id' => $game->igdb_id,
                'game_slug' => $game->slug,
                'game_name' => $game->name,
                'lists' => $lists,
                'on_target_list' => $targetListSlug !== null && $lists->contains(
                    fn (array $list): bool => $list['slug'] === $targetListSlug
                ),
                'on_staging_list' => $stagingListSlug !== null && $lists->contains(
                    fn (array $list): bool => $list['slug'] === $stagingListSlug
                ),
            ];
        });

        return response()->json(['results' => $results->values()]);
    }

    /**
     * Attach researched games to the target's hidden staging list (creating them
     * from IGDB when missing). Games only reach the real list via admin promote.
     */
    public function listItems(ImportListItemsRequest $request, GameListImportService $importService): JsonResponse
    {
        $target = GameList::where('slug', $request->validated('list_slug'))
            ->where('is_system', true)
            ->first();

        if (! $target || ! ($target->isYearly() || $target->isSeasoned())) {
            return response()->json(['error' => 'Target list not found or not a yearly/seasoned system list.'], 422);
        }

        $list = $importService->stagingListFor($target);

        $results = collect($request->validated('items'))->map(function (array $item) use ($list, $importService): array {
            $isTba = (bool) ($item['is_tba'] ?? false);

            try {
                $result = $importService->attachGame($list, (int) $item['igdb_id'], [
                    'release_date' => $isTba ? null : ($item['release_date'] ?? null),
                    'platforms' => $item['platforms'] ?? [],
                    'is_tba' => $isTba,
                    'release_year' => $item['release_year'] ?? null,
                ]);
            } catch (ValidationException $e) {
                return [
                    'igdb_id' => $item['igdb_id'],
                    'status' => 'failed',
                    'error' => collect($e->errors())->flatten()->implode(' '),
                ];
            } catch (\Throwable $e) {
                report($e);

                return [
                    'igdb_id' => $item['igdb_id'],
                    'status' => 'failed',
                    'error' => 'Unexpected error while importing this game.',
                ];
            }

            return [
                'igdb_id' => $item['igdb_id'],
                'status' => $result->status->value,
                'game_slug' => $result->game?->slug,
                'game_name' => $result->game?->name,
                'confidence' => $item['confidence'] ?? null,
                'note' => $item['note'] ?? null,
            ];
        });

        \Log::info('List import staged', [
            'target_list' => $target->slug,
            'staging_list' => $list->slug,
            'items' => $results->countBy('status'),
        ]);

        return response()->json([
            'list_slug' => $target->slug,
            'staging_list_slug' => $list->slug,
            'review_url' => route('admin.system-lists.edit', ['import', $list->slug]),
            'results' => $results->values(),
        ]);
    }
}
