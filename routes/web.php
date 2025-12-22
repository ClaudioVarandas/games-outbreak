<?php

use App\Http\Controllers\GameListController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GamesController;
use App\Http\Middleware\EnsureAdminUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('homepage');
Route::get('/monthly-releases', [HomepageController::class, 'monthlyReleases'])->name('monthly-releases');


Route::get('/upcoming', [GamesController::class, 'upcoming'])
    ->name('upcoming');

Route::get('/most-wanted', [GamesController::class, 'mostWanted'])
    ->name('most-wanted');

Route::get('/game/{game:igdb_id}', [GamesController::class, 'show'])
    ->name('game.show');

Route::get('/api/search', [GamesController::class, 'search'])
    ->name('api.search');

// System list public route
Route::get('/list/{slug}', [GameListController::class, 'showBySlug'])
    ->name('system-list.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // User Lists
        Route::get('/lists', [GameListController::class, 'index'])->name('lists.index');
        Route::get('/lists/create', [GameListController::class, 'create'])->name('lists.create');
        Route::post('/lists', [GameListController::class, 'store'])->name('lists.store');
        Route::get('/lists/{gameList}', [GameListController::class, 'show'])->name('lists.show');
        Route::get('/lists/{gameList}/edit', [GameListController::class, 'edit'])->name('lists.edit');
        Route::patch('/lists/{gameList}', [GameListController::class, 'update'])->name('lists.update');
        Route::delete('/lists/{gameList}', [GameListController::class, 'destroy'])->name('lists.destroy');
        Route::post('/lists/{gameList}/games', [GameListController::class, 'addGame'])->name('lists.games.add');
        Route::delete('/lists/{gameList}/games/{game}', [GameListController::class, 'removeGame'])->name('lists.games.remove');
    });

// Admin System Lists Management
Route::middleware(['auth', EnsureAdminUser::class])
    ->prefix('admin/lists/system')
    ->name('admin.system-lists.')
    ->group(function () {
        Route::get('/', [GameListController::class, 'systemIndex'])->name('index');
        Route::get('/create', [GameListController::class, 'createSystem'])->name('create');
        Route::post('/', [GameListController::class, 'storeSystem'])->name('store');
        Route::patch('/{gameList}/activate', [GameListController::class, 'toggleActive'])->name('toggle');
    });

require __DIR__ . '/auth.php';
