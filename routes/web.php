<?php

use App\Http\Controllers\AdminListController;
use App\Http\Controllers\AdminNewsController;
use App\Http\Controllers\GameListController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\HighlightsController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\IndieGamesController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserListController;
use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsureNewsFeatureEnabled;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('homepage');
Route::get('/releases/{type}', [HomepageController::class, 'releases'])->name('releases')
    ->where('type', 'monthly|seasoned');
Route::get('/indie-games', [IndieGamesController::class, 'index'])->name('indie-games');
Route::get('/highlights', [HighlightsController::class, 'index'])->name('highlights');
Route::redirect('/monthly-releases', '/releases/monthly', 301);
Route::redirect('/releases/indie-games', '/indie-games', 301);
Route::get('/upcoming', [GamesController::class, 'upcoming'])->name('upcoming');
Route::get('/most-wanted', [GamesController::class, 'mostWanted'])->name('most-wanted');
Route::get('/game/{game:slug}', [GamesController::class, 'show'])->name('game.show');
Route::get('/game/igdb/{igdbId}', [GamesController::class, 'showByIgdbId'])->where('igdbId', '[0-9]+')->name('game.show.igdb');

Route::get('/api/search', [GamesController::class, 'search'])->middleware('prevent-caching')->name('api.search');
Route::get('/api/game/{game:slug}/similar', [GamesController::class, 'similarGames'])->middleware('prevent-caching')->name('api.game.similar');

Route::get('/search', [GamesController::class, 'searchResults'])->middleware('prevent-caching')->name('search');

Route::get('/game/{game:slug}/similar-games-html', [GamesController::class, 'similarGamesHtml'])->middleware('prevent-caching')->name('game.similar.html');

// ============================================================================
// News Routes (Public)
// ============================================================================

Route::middleware([EnsureNewsFeatureEnabled::class])
    ->prefix('news')
    ->name('news.')
    ->group(function () {
        Route::get('/', [NewsController::class, 'index'])->name('index');
        Route::get('/{news:slug}', [NewsController::class, 'show'])->name('show');
    });

// Public list view (read-only)
Route::get('/list/{type}/{slug}', [GameListController::class, 'showBySlug'])->name('lists.show');

// ============================================================================
// NEW: User Profile Routes (/u/{username})
// ============================================================================

// Dual-mode routes (public viewing, management for owner/admin)
// Owner-only routes (create, edit, delete, game management) - Must come first for specificity
Route::middleware(['auth', 'user.ownership', 'prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        // Custom lists CRUD (specific routes before wildcard {slug})
        Route::get('/lists/create', [UserListController::class, 'createList'])->name('lists.create');
        Route::post('/lists', [UserListController::class, 'storeList'])->name('lists.store');
        Route::patch('/lists/{slug}', [UserListController::class, 'updateList'])->name('lists.update');
        Route::delete('/lists/{slug}', [UserListController::class, 'destroyList'])->name('lists.destroy');

        // Game management (works for backlog, wishlist, and custom lists)
        Route::post('/{type}/games', [UserListController::class, 'addGame'])->name('games.add');
        Route::delete('/{type}/games/{game:id}', [UserListController::class, 'removeGame'])->name('games.remove');
        Route::patch('/{type}/games/reorder', [UserListController::class, 'reorderGames'])->name('games.reorder');
    });

// Public viewing routes (no auth required) - After owner routes for proper precedence
Route::middleware(['prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        Route::get('/backlog', [UserListController::class, 'backlog'])->name('backlog');
        Route::get('/wishlist', [UserListController::class, 'wishlist'])->name('wishlist');
        Route::get('/lists', [UserListController::class, 'myLists'])->name('lists');
        Route::get('/lists/{slug}', [UserListController::class, 'showList'])->name('lists.show');
    });

// View mode toggle (global, not user-specific)
Route::post('/toggle-view-mode', [UserListController::class, 'toggleViewMode'])
    ->middleware('auth')
    ->name('user.lists.toggle-view');

// ============================================================================
// NEW: Admin Routes (/admin)
// ============================================================================

Route::middleware(['auth', EnsureAdminUser::class, 'prevent-caching'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Admin's own lists overview
        Route::get('/my-lists', [AdminListController::class, 'myLists'])->name('my-lists');

        // All system lists overview
        Route::get('/system-lists', [AdminListController::class, 'systemLists'])->name('system-lists');

        // System lists management
        Route::get('/system-lists/create', [AdminListController::class, 'createSystemList'])->name('system-lists.create');
        Route::post('/system-lists', [AdminListController::class, 'storeSystemList'])->name('system-lists.store');
        Route::get('/system-lists/{type}/{slug}/edit', [AdminListController::class, 'editSystemList'])->name('system-lists.edit');
        Route::patch('/system-lists/{type}/{slug}', [AdminListController::class, 'updateSystemList'])->name('system-lists.update');
        Route::patch('/system-lists/{type}/{slug}/toggle', [AdminListController::class, 'toggleSystemListActive'])->name('system-lists.toggle');
        Route::delete('/system-lists/{type}/{slug}', [AdminListController::class, 'destroySystemList'])->name('system-lists.destroy');

        // System list game management
        Route::post('/system-lists/{type}/{slug}/games', [AdminListController::class, 'addGame'])->name('system-lists.games.add');
        Route::delete('/system-lists/{type}/{slug}/games/{game:id}', [AdminListController::class, 'removeGame'])->name('system-lists.games.remove');
        Route::patch('/system-lists/{type}/{slug}/games/reorder', [AdminListController::class, 'reorderGames'])->name('system-lists.games.reorder');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/platform-group', [AdminListController::class, 'updateGamePlatformGroup'])->name('system-lists.games.platform-group');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/highlight', [AdminListController::class, 'toggleGameHighlight'])->name('system-lists.games.toggle-highlight');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/indie', [AdminListController::class, 'toggleGameIndie'])->name('system-lists.games.toggle-indie');
        Route::get('/system-lists/{type}/{slug}/games/{game:id}/genres', [AdminListController::class, 'getGameGenres'])->name('system-lists.games.genres');

        // All users' lists overview
        Route::get('/user-lists', [AdminListController::class, 'userLists'])->name('user-lists');

        // News management
        Route::middleware([EnsureNewsFeatureEnabled::class])
            ->prefix('news')
            ->name('news.')
            ->group(function () {
                Route::get('/', [AdminNewsController::class, 'index'])->name('index');
                Route::get('/create', [AdminNewsController::class, 'create'])->name('create');
                Route::post('/', [AdminNewsController::class, 'store'])->name('store');
                Route::get('/{news}/edit', [AdminNewsController::class, 'edit'])->name('edit');
                Route::patch('/{news}', [AdminNewsController::class, 'update'])->name('update');
                Route::delete('/{news}', [AdminNewsController::class, 'destroy'])->name('destroy');
                Route::post('/import-url', [AdminNewsController::class, 'importFromUrl'])->name('import-url');
            });
    });

// ============================================================================
// Redirects for old routes (backward compatibility)
// ============================================================================

Route::middleware('auth')->group(function () {
    Route::get('/backlog', function () {
        return redirect()->route('user.lists.backlog', ['user' => auth()->user()->username], 301);
    })->name('legacy.backlog');

    Route::get('/wishlist', function () {
        return redirect()->route('user.lists.wishlist', ['user' => auth()->user()->username], 301);
    })->name('legacy.wishlist');

    Route::get('/lists', function () {
        return redirect()->route('user.lists.lists', ['user' => auth()->user()->username], 301);
    })->name('legacy.lists');

    Route::get('/lists/create', function () {
        return redirect()->route('user.lists.lists.create', ['user' => auth()->user()->username]);
    })->name('legacy.lists.create');
});

// Redirect old /u/{user}/regular to new /lists
Route::get('/u/{user:username}/regular', function (User $user) {
    return redirect("/u/{$user->username}/lists", 301);
});

// Redirect old /u/{user}/my-lists to new /lists
Route::get('/u/{user:username}/my-lists', function (User $user) {
    return redirect("/u/{$user->username}/lists", 301);
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'prevent-caching'])
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

require __DIR__.'/auth.php';
