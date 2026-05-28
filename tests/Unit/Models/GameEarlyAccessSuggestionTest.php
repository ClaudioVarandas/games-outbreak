<?php

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns null when the game has no early access release date', function () {
    $game = Game::factory()->create();
    $game->load(['releaseDates.platform', 'releaseDates.status']);

    expect($game->earlyAccessSuggestion())->toBeNull();
});

it('builds a label and date from the earliest early access release date', function () {
    $game = Game::factory()->create();
    $ea = ReleaseDateStatus::firstOrCreate(['igdb_id' => 3], ['name' => 'Early Access', 'abbreviation' => 'EA']);
    $pc = Platform::firstOrCreate(['igdb_id' => 6], ['name' => 'PC (Microsoft Windows)']);

    GameReleaseDate::create([
        'game_id' => $game->id,
        'platform_id' => $pc->id,
        'status_id' => $ea->id,
        'date' => '2024-12-11',
        'year' => 2024,
        'human_readable' => '11 Dec 2024',
        'is_manual' => false,
    ]);

    $game->load(['releaseDates.platform', 'releaseDates.status']);
    $suggestion = $game->earlyAccessSuggestion();

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['date'])->toBe('2024-12-11')
        ->and($suggestion['label'])->toContain('PC')
        ->and($suggestion['label'])->toContain('11 Dec 2024');
});
