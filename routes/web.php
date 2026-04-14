<?php

use App\Enums\NewsLocaleEnum;
use App\Http\Controllers\Admin\News\NewsArticleController as AdminNewsArticleController;
use App\Http\Controllers\Admin\News\NewsArticleImageUploadController as AdminNewsArticleImageUploadController;
use App\Http\Controllers\Admin\News\NewsArticleRemoveFeaturedImageController as AdminNewsArticleRemoveFeaturedImageController;
use App\Http\Controllers\Admin\News\NewsImportController as AdminNewsImportController;
use App\Http\Controllers\AdminGenreController;
use App\Http\Controllers\AdminListController;
use App\Http\Controllers\Api\UserGameController as ApiUserGameController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\GameListController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\NewsArticleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReleasesController;
use App\Http\Controllers\UserGameController;
use App\Http\Controllers\UserListController;
use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\EnsureNewsFeatureEnabled;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('homepage');

// Releases routes - seasoned must come before {year} to avoid conflict
Route::get('/releases/seasoned', [HomepageController::class, 'releases'])->name('releases.seasoned')
    ->defaults('type', 'seasoned');
Route::get('/releases', function () {
    return redirect()->route('releases.year', now()->year);
})->name('releases');
Route::get('/releases/{year}', [ReleasesController::class, 'index'])
    ->where('year', '[0-9]{4}')
    ->name('releases.year');
Route::get('/releases/{year}/{month}', [ReleasesController::class, 'index'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}'])
    ->name('releases.year.month');

// Legacy redirects for old routes
Route::redirect('/monthly-releases', '/releases', 301);
Route::redirect('/releases/monthly', '/releases', 301);
Route::redirect('/releases/indie-games', '/releases', 301);
Route::redirect('/indie-games', '/releases', 301);
Route::redirect('/highlights', '/releases', 301);

Route::get('/events', EventsController::class)->name('events');
Route::get('/upcoming', [GamesController::class, 'upcoming'])->name('upcoming');
Route::get('/most-wanted', [GamesController::class, 'mostWanted'])->name('most-wanted');
Route::get('/game/{game:slug}', [GamesController::class, 'show'])->name('game.show');
Route::get('/game/igdb/{igdbId}', [GamesController::class, 'showByIgdbId'])->where('igdbId', '[0-9]+')->name('game.show.igdb');

Route::get('/api/search', [GamesController::class, 'search'])->middleware('prevent-caching')->name('api.search');
Route::get('/api/game/{game:slug}/similar', [GamesController::class, 'similarGames'])->middleware('prevent-caching')->name('api.game.similar');

Route::get('/search', [GamesController::class, 'searchResults'])->middleware('prevent-caching')->name('search');

Route::get('/game/{game:slug}/similar-games-html', [GamesController::class, 'similarGamesHtml'])->middleware('prevent-caching')->name('game.similar.html');

// ============================================================================
// News Routes (Public — localized)
// ============================================================================

// EN news routes
Route::middleware([EnsureNewsFeatureEnabled::class, 'set-news-locale'])
    ->prefix('en/news')
    ->name('news-articles.en.')
    ->group(function () {
        Route::get('/', fn () => app(NewsArticleController::class)->index('en'))->name('index');
        Route::get('/{slug}', fn (string $slug) => app(NewsArticleController::class)->show('en', $slug))->name('show');
    });

// PT news routes (pt-pt/noticias, pt-br/noticias)
Route::middleware([EnsureNewsFeatureEnabled::class, 'set-news-locale'])
    ->prefix('{localePrefix}/noticias')
    ->where(['localePrefix' => 'pt-pt|pt-br'])
    ->name('news-articles.')
    ->group(function () {
        Route::get('/', [NewsArticleController::class, 'index'])->name('index');
        Route::get('/{slug}', [NewsArticleController::class, 'show'])->name('show');
    });

// Default redirect — /news → saved locale, then browser locale, then app.locale
Route::middleware([EnsureNewsFeatureEnabled::class])
    ->get('/news', function (Request $request) {
        $savedSlug = session('news_locale');

        if ($savedSlug) {
            try {
                return redirect(NewsLocaleEnum::fromPrefix($savedSlug)->indexUrl());
            } catch (Throwable) {
                // stale session value — fall through
            }
        }

        return redirect(
            NewsLocaleEnum::fromBrowserLocale($request->header('Accept-Language'))->indexUrl()
        );
    })
    ->name('news-articles.default');

// Public list view (read-only)
Route::get('/list/{type}/{slug}', [GameListController::class, 'showBySlug'])->name('lists.show');

// ============================================================================
// User Game Collection API (AJAX from game cards/popovers)
// ============================================================================

Route::middleware(['auth', 'prevent-caching'])
    ->prefix('api/user-games')
    ->name('api.user-games.')
    ->group(function () {
        Route::post('/', [ApiUserGameController::class, 'store'])->name('store');
        Route::patch('/{userGame}', [ApiUserGameController::class, 'update'])->name('update');
        Route::delete('/{userGame}', [ApiUserGameController::class, 'destroy'])->name('destroy');
        Route::get('/status/{game:id}', [ApiUserGameController::class, 'status'])->name('status');
    });

// ============================================================================
// My Games Collection Routes (/u/{username}/games)
// ============================================================================

// Owner-only routes
Route::middleware(['auth', 'user.ownership', 'prevent-caching'])
    ->prefix('u/{user:username}/games')
    ->name('user.games.')
    ->group(function () {
        Route::get('/settings', [UserGameController::class, 'settings'])->name('settings');
        Route::patch('/settings', [UserGameController::class, 'updateSettings'])->name('settings.update');
        Route::post('/', [UserGameController::class, 'store'])->name('store');
        Route::patch('/reorder', [UserGameController::class, 'reorder'])->name('reorder');
        Route::patch('/{userGame}', [UserGameController::class, 'update'])->name('update');
        Route::delete('/{userGame}', [UserGameController::class, 'destroy'])->name('destroy');
    });

// Public viewing route
Route::middleware(['prevent-caching'])
    ->prefix('u/{user:username}')
    ->group(function () {
        Route::get('/games', [UserGameController::class, 'index'])->name('user.games');
    });

// ============================================================================
// Legacy User List Routes (/u/{username})
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
        Route::post('/system-lists/{type}/{slug}/refresh', [AdminListController::class, 'refreshGameList'])->name('system-lists.refresh');

        // System list game management
        Route::post('/system-lists/{type}/{slug}/games', [AdminListController::class, 'addGame'])->name('system-lists.games.add');
        Route::delete('/system-lists/{type}/{slug}/games/{game:id}', [AdminListController::class, 'removeGame'])->name('system-lists.games.remove');
        Route::patch('/system-lists/{type}/{slug}/games/reorder', [AdminListController::class, 'reorderGames'])->name('system-lists.games.reorder');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/platform-group', [AdminListController::class, 'updateGamePlatformGroup'])->name('system-lists.games.platform-group');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/highlight', [AdminListController::class, 'toggleGameHighlight'])->name('system-lists.games.toggle-highlight');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/indie', [AdminListController::class, 'toggleGameIndie'])->name('system-lists.games.toggle-indie');
        Route::get('/system-lists/{type}/{slug}/games/{game:id}/genres', [AdminListController::class, 'getGameGenres'])->name('system-lists.games.genres');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/genres', [AdminListController::class, 'updateGameGenres'])->name('system-lists.games.update-genres');
        Route::patch('/system-lists/{type}/{slug}/games/{game:id}/pivot', [AdminListController::class, 'updateGamePivotData'])->name('system-lists.games.update-pivot');

        // All users' lists overview
        Route::get('/user-lists', [AdminListController::class, 'userLists'])->name('user-lists');

        // Genre management
        Route::prefix('genres')
            ->name('genres.')
            ->group(function () {
                Route::get('/', [AdminGenreController::class, 'index'])->name('index');
                Route::post('/', [AdminGenreController::class, 'store'])->name('store');
                Route::patch('/reorder', [AdminGenreController::class, 'reorder'])->name('reorder');
                Route::post('/merge', [AdminGenreController::class, 'merge'])->name('merge');
                Route::post('/bulk-remove', [AdminGenreController::class, 'bulkRemove'])->name('bulk-remove');
                Route::post('/bulk-replace', [AdminGenreController::class, 'bulkReplace'])->name('bulk-replace');
                Route::patch('/{genre}', [AdminGenreController::class, 'update'])->name('update');
                Route::delete('/{genre}', [AdminGenreController::class, 'destroy'])->name('destroy');
                Route::patch('/{genre}/approve', [AdminGenreController::class, 'approve'])->name('approve');
                Route::delete('/{genre}/reject', [AdminGenreController::class, 'reject'])->name('reject');
                Route::patch('/{genre}/toggle-visibility', [AdminGenreController::class, 'toggleVisibility'])->name('toggle-visibility');
                Route::post('/{genre}/assign-games', [AdminGenreController::class, 'assignGames'])->name('assign-games');
            });

        // Genre API (for Tom Select search)
        Route::get('/api/genres/search', [AdminGenreController::class, 'search'])->name('api.genres.search');

        // News import pipeline
        Route::middleware([EnsureNewsFeatureEnabled::class])
            ->prefix('news-imports')
            ->name('news-imports.')
            ->group(function () {
                Route::get('/', [AdminNewsImportController::class, 'index'])->name('index');
                Route::get('/create', [AdminNewsImportController::class, 'create'])->name('create');
                Route::post('/', [AdminNewsImportController::class, 'store'])->name('store');
                Route::get('/{newsImport}', [AdminNewsImportController::class, 'show'])->name('show');
            });

        // News articles (pipeline output)
        Route::middleware([EnsureNewsFeatureEnabled::class])
            ->prefix('news-articles')
            ->name('news-articles.')
            ->group(function () {
                Route::get('/', [AdminNewsArticleController::class, 'index'])->name('index');
                Route::get('/{newsArticle}/edit', [AdminNewsArticleController::class, 'edit'])->name('edit');
                Route::post('/{newsArticle}/upload-image', AdminNewsArticleImageUploadController::class)->name('upload-image');
                Route::delete('/{newsArticle}/featured-image', AdminNewsArticleRemoveFeaturedImageController::class)->name('featured-image.destroy');
                Route::patch('/{newsArticle}', [AdminNewsArticleController::class, 'update'])->name('update');
                Route::post('/{newsArticle}/publish', [AdminNewsArticleController::class, 'publish'])->name('publish');
                Route::post('/{newsArticle}/schedule', [AdminNewsArticleController::class, 'schedule'])->name('schedule');
                Route::delete('/{newsArticle}', [AdminNewsArticleController::class, 'destroy'])->name('destroy');
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

Route::get('/health', static fn () => response()->json(['status' => 'ok']));

require __DIR__.'/auth.php';
