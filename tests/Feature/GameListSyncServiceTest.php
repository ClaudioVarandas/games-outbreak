<?php

use App\Models\GameList;
use App\Models\User;
use App\Services\GameListSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function syncService(): GameListSyncService
{
    return app(GameListSyncService::class);
}

beforeEach(function () {
    User::factory()->create(); // ensures user_id = 1 exists for system list ownership
});

it('finds nothing when no yearly list exists for the year', function () {
    expect(syncService()->findYearlyList(2031))->toBeNull();
});

it('creates a yearly system list with the expected attributes', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);

    expect($list->list_type->value)->toBe('yearly')
        ->and($list->is_system)->toBeTrue()
        ->and($list->is_public)->toBeTrue()
        ->and($list->is_active)->toBeTrue()
        ->and($list->name)->toBe('Game Releases 2031')
        ->and($list->slug)->toBe('game-releases-2031')
        ->and($list->start_at->format('Y-m-d'))->toBe('2031-01-01')
        ->and($list->end_at->format('Y-m-d'))->toBe('2031-12-31');
});

it('returns the existing yearly list instead of creating a duplicate', function () {
    $first = syncService()->firstOrCreateYearlyList(2031);
    $second = syncService()->firstOrCreateYearlyList(2031);

    expect($second->id)->toBe($first->id)
        ->and(GameList::yearly()->whereYear('start_at', 2031)->count())->toBe(1);
});
