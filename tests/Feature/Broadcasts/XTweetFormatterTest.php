<?php

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Services\Broadcasts\Formatters\XTweetFormatter;
use App\Services\WeeklyChoicesCollector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-26 21:00:00', 'Europe/Lisbon'));

    $this->formatter = new XTweetFormatter;

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->collector = app(WeeklyChoicesCollector::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('fits within the 280 character budget with many long-titled games', function () {
    for ($i = 0; $i < 20; $i++) {
        $game = Game::factory()->create([
            'name' => 'A Very Long Game Title Number '.$i.' Of Some Kind',
            'slug' => 'game-'.$i,
        ]);
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-04-27 00:00:00')->addMinutes($i)->toDateTimeString(),
            'platforms' => json_encode([PlatformEnum::PS5->value, PlatformEnum::PC->value, PlatformEnum::XBOX_SX->value]),
        ]);
    }

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect(mb_strlen($output))->toBeLessThanOrEqual(280);
    expect($output)->toContain('🎮 This week on Games Outbreak');
    expect($output)->toContain('curated releases:');
    expect($output)->toContain('more');
    expect($output)->toContain('Full list → ');
});

it('returns empty string for an empty payload', function () {
    GameList::query()->delete();

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect($output)->toBe('');
});

it('renders all games when they fit the budget', function () {
    $titles = ['Alpha', 'Bravo', 'Charlie'];
    foreach ($titles as $i => $title) {
        $game = Game::factory()->create(['name' => $title, 'slug' => strtolower($title)]);
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-04-27 00:00:00')->addMinutes($i)->toDateTimeString(),
            'platforms' => json_encode([PlatformEnum::PC->value]),
        ]);
    }

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect($output)->toContain('• Alpha — PC');
    expect($output)->toContain('• Bravo — PC');
    expect($output)->toContain('• Charlie — PC');
    expect($output)->not->toContain('more');
});
