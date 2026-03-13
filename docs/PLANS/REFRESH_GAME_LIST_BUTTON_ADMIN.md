Plan: Refresh Game List Button on /admin/system-lists

Context

The /admin/system-lists page shows game list cards (Yearly, Seasoned, Events sections). Each card has Edit and Delete buttons. The RefreshGameListGames artisan command refreshes IGDB data
for all games in a list, but it can only run from the CLI. This feature adds a third "Refresh" button to each card so admins can trigger a background IGDB sync from the UI.

The command's logic must be extracted into a service so it can be shared between the command (CLI) and a new queued job (web-triggered). The button uses Alpine.js for an optimistic loading
state (spinner stays until page reload, since the job is async).

 ---
Files to Create

1. app/Services/GameListRefreshService.php

Extract the per-game refresh loop from RefreshGameListGames::handle() (lines 47–263). Remove all console output ($this->info, $this->warn, $this->error, progress bar). Replace with Log::
calls.

public function __construct(public IgdbService $igdb) {}

public function refreshList(GameList $gameList, bool $force = false): void

The method body:
- Filters $gameList->games by last_igdb_sync_at unless $force
- Iterates, skips games with no igdb_id (Log::warning)
- Fetches from IGDB API, updates game, syncs all relations
- Try/catch wraps each game, Log::error on failure

2. app/Jobs/RefreshGameListGamesJob.php

Follow RefreshGameData.php exactly:

class RefreshGameListGamesJob implements ShouldQueue
{
use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

     public function __construct(
         public int $gameListId,
         public bool $force = false
     ) {
         $this->onQueue('low');
     }

     public function handle(GameListRefreshService $refreshService): void
     {
         $gameList = GameList::with('games')->find($this->gameListId);
         if (! $gameList) { Log::warning(...); return; }
         $refreshService->refreshList($gameList, $this->force);
     }

     public function failed(\Throwable $exception): void { Log::error(...); }
}

3. tests/Feature/AdminRefreshGameListTest.php

Create via php artisan make:test --pest AdminRefreshGameListTest.

Test cases:
- Guest POST returns redirect (auth guard)
- Non-admin returns 403
- Admin can dispatch refresh (returns {"success": true}, Queue::assertPushed with correct gameListId)
- Works for yearly, seasoned, and events list types
- Returns 404 for invalid type slug
- Returns 404 for non-existent list slug
- Queue::fake() used in all queue-related tests
- Service skips games with no igdb_id (no HTTP call made, Http::assertNothingSent())
- Service skips recently synced games when force=false
- Service refreshes all games when force=true

 ---
Files to Modify

4. app/Console/Commands/RefreshGameListGames.php

Slim down handle() to delegate to the service. Remove IgdbService injection, inject GameListRefreshService instead. Keep the console output wrapping (info/warn before and after calling the
service).

public function handle(GameListRefreshService $refreshService): int
{
$gameList = GameList::with('games')->find($this->argument('game_list_id'));
if (! $gameList) { $this->error(...); return self::FAILURE; }
$this->info("Refreshing: {$gameList->name} ({$gameList->games->count()} games)");
$refreshService->refreshList($gameList, $this->option('force'));
$this->info('Done!');
return self::SUCCESS;
}

5. app/Http/Controllers/AdminListController.php

Add refreshGameList() method (method DI - used only here):

public function refreshGameList(string $type, string $slug): JsonResponse
{
$listType = ListTypeEnum::fromSlug($type);
abort_if($listType === null, 404);

     $list = GameList::where('slug', $slug)
         ->where('list_type', $listType)
         ->where('is_system', true)
         ->firstOrFail();

     RefreshGameListGamesJob::dispatch($list->id);

     return response()->json(['success' => true, 'message' => "Refresh queued for \"{$list->name}\"."]);
}

Add use App\Jobs\RefreshGameListGamesJob; and use Illuminate\Http\JsonResponse; to imports.

6. routes/web.php

Add within the admin system-lists route group (near the other system-lists routes):

Route::post('/system-lists/{type}/{slug}/refresh', [AdminListController::class, 'refreshGameList'])->name('admin.system-lists.refresh');

7. resources/views/admin/system-lists/index.blade.php

3 places to update (yearly ~line 53, seasoned ~line 148, events ~line 229):

Add x-data="{ refreshing: false }" to each card's outer div.

Add refresh button before the Edit button in each <div class="flex gap-2 justify-end">:

<button
@click="refreshing = true; fetch('{{ route('admin.system-lists.refresh', [$list->list_type->toSlug(), $list->slug]) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN':
document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })"
:disabled="refreshing"
class="p-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
title="Refresh from IGDB">
<svg class="w-5 h-5" :class="{ 'animate-spin': refreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357
2H15"></path>
</svg>
</button>

Button order: Refresh (green) → Edit (orange) → Delete (red)

 ---
Verification

1. Run php artisan test --compact tests/Feature/AdminRefreshGameListTest.php - all tests pass
2. Run vendor/bin/pint --dirty - no formatting issues
3. Manually: visit /admin/system-lists, click refresh on a list, button disables and icon spins, check queue worker processes the job (php artisan queue:work --queue=low), verify
   last_igdb_sync_at updates on games in the list
