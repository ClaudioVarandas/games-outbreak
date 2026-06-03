<?php

use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(); // ensures user_id = 1 exists for system list ownership
});

it('creates a yearly system list via the command', function () {
    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])
        ->expectsOutputToContain('Created: Game Releases 2031')
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2031)->where('is_system', true)->exists())->toBeTrue();
});

it('refuses to create a duplicate yearly list', function () {
    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])->assertSuccessful();

    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])->assertFailed();

    expect(GameList::yearly()->whereYear('start_at', 2031)->count())->toBe(1);
});
