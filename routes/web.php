<?php

use App\Http\Controllers\GameListController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GamesController;
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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'prevent-caching'])
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Backlog and Wishlist Pages
        Route::get('/backlog', [GameListController::class, 'backlog'])->name('backlog');
        Route::get('/wishlist', [GameListController::class, 'wishlist'])->name('wishlist');

        // User Lists CRUD
        Route::get('/lists', [GameListController::class, 'index'])->name('lists.index');
        Route::get('/lists/create', [GameListController::class, 'create'])->name('lists.create');
        Route::post('/lists', [GameListController::class, 'store'])->name('lists.store');
        Route::get('/list/{type}/{slug}/edit', [GameListController::class, 'edit'])->name('lists.edit');
        Route::patch('/list/{type}/{slug}', [GameListController::class, 'update'])->name('lists.update');
        Route::delete('/list/{type}/{slug}', [GameListController::class, 'destroy'])->name('lists.destroy');
        Route::post('/list/{type}/{slug}/games', [GameListController::class, 'addGame'])->name('lists.games.add');
        Route::delete('/list/{type}/{slug}/games/{game}', [GameListController::class, 'removeGame'])->name('lists.games.remove');
    });

// Admin System Lists Management
Route::middleware(['auth', EnsureAdminUser::class, 'prevent-caching'])
    ->prefix('admin/lists/system')
    ->name('admin.system-lists.')
    ->group(function () {
        Route::get('/', [GameListController::class, 'systemIndex'])->name('index');
        Route::get('/create', [GameListController::class, 'createSystem'])->name('create');
        Route::post('/', [GameListController::class, 'storeSystem'])->name('store');
        Route::patch('/{gameList}/activate', [GameListController::class, 'toggleActive'])->name('toggle');
    });

require __DIR__ . '/auth.php';
