<?php

use App\Http\Controllers\AdminListController;
use App\Http\Controllers\GameListController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\UserListController;
use App\Http\Middleware\EnsureAdminUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('homepage');
Route::get('/releases/{type}', [HomepageController::class, 'releases'])->name('releases')
    ->where('type', 'monthly|indie-games|seasoned');
Route::redirect('/monthly-releases', '/releases/monthly', 301);
Route::redirect('/indie-games', '/releases/indie-games', 301);
Route::get('/upcoming', [GamesController::class, 'upcoming'])->name('upcoming');
Route::get('/most-wanted', [GamesController::class, 'mostWanted'])->name('most-wanted');
Route::get('/game/{game:igdb_id}', [GamesController::class, 'show'])->name('game.show');

Route::get('/api/search', [GamesController::class, 'search'])->middleware('prevent-caching')->name('api.search');
Route::get('/api/game/{game:igdb_id}/similar', [GamesController::class, 'similarGames'])->middleware('prevent-caching')->name('api.game.similar');

Route::get('/search', [GamesController::class, 'searchResults'])->middleware('prevent-caching')->name('search');

Route::get('/game/{game:igdb_id}/similar-games-html', [GamesController::class, 'similarGamesHtml'])->middleware('prevent-caching')->name('game.similar.html');

// Public list view (read-only)
Route::get('/list/{type}/{slug}', [GameListController::class, 'showBySlug'])->name('lists.show');

// ============================================================================
// NEW: User Profile Routes (/u/{username})
// ============================================================================

// Dual-mode routes (public viewing, management for owner/admin)
Route::middleware(['prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        Route::get('/backlog', [UserListController::class, 'backlog'])->name('backlog');
        Route::get('/wishlist', [UserListController::class, 'wishlist'])->name('wishlist');
        Route::get('/my-lists', [UserListController::class, 'myLists'])->name('my-lists');
        Route::get('/regular', [UserListController::class, 'regularLists'])->name('regular');
    });

// Owner-only routes (create, edit, delete, game management)
Route::middleware(['auth', 'user.ownership', 'prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        // Regular lists CRUD
        Route::get('/regular/create', [UserListController::class, 'createRegular'])->name('regular.create');
        Route::post('/regular', [UserListController::class, 'storeRegular'])->name('regular.store');
        Route::get('/regular/{slug}/edit', [UserListController::class, 'editRegular'])->name('regular.edit');
        Route::patch('/regular/{slug}', [UserListController::class, 'updateRegular'])->name('regular.update');
        Route::delete('/regular/{slug}', [UserListController::class, 'destroyRegular'])->name('regular.destroy');

        // Game management (works for backlog, wishlist, and regular lists)
        Route::post('/{type}/games', [UserListController::class, 'addGame'])->name('games.add');
        Route::delete('/{type}/games/{game}', [UserListController::class, 'removeGame'])->name('games.remove');
        Route::patch('/{type}/games/reorder', [UserListController::class, 'reorderGames'])->name('games.reorder');
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
        Route::delete('/system-lists/{type}/{slug}/games/{game}', [AdminListController::class, 'removeGame'])->name('system-lists.games.remove');
        Route::patch('/system-lists/{type}/{slug}/games/reorder', [AdminListController::class, 'reorderGames'])->name('system-lists.games.reorder');

        // All users' lists overview
        Route::get('/user-lists', [AdminListController::class, 'userLists'])->name('user-lists');
    });

// ============================================================================
// Redirects for old routes (backward compatibility)
// ============================================================================

Route::middleware('auth')->group(function () {
    Route::get('/backlog', function () {
        return redirect()->route('user.lists.backlog', ['user' => auth()->user()->username]);
    });

    Route::get('/wishlist', function () {
        return redirect()->route('user.lists.wishlist', ['user' => auth()->user()->username]);
    });

    Route::get('/lists', function () {
        return redirect()->route('user.lists.my-lists', ['user' => auth()->user()->username]);
    });

    Route::get('/lists/create', function () {
        return redirect()->route('user.lists.regular.create', ['user' => auth()->user()->username]);
    });
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

require __DIR__ . '/auth.php';
