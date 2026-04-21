<?php

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Services\Broadcasts\Formatters\TelegramMessageFormatter;
use App\Services\WeeklyChoicesCollector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-26 21:00:00', 'Europe/Lisbon'));

    $this->formatter = new TelegramMessageFormatter;

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->collector = app(WeeklyChoicesCollector::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders MarkdownV2 output with header, per-game link, and CTA', function () {
    $split = Game::factory()->create(['name' => 'Split Fiction', 'slug' => 'split-fiction']);
    $this->list->games()->attach($split->id, [
        'release_date' => '2026-04-29 00:00:00',
        'platforms' => json_encode([PlatformEnum::PS5->value, PlatformEnum::PC->value]),
    ]);

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect($output)->toContain("*🎮 Games Outbreak — This Week's Choices*");
    expect($output)->toContain('_Week of Apr 27 – May 3_');
    expect($output)->toContain('[Split Fiction](');
    expect($output)->toContain('— Apr 29 · PS5/PC');
    expect($output)->toContain('[See the full list →]');
});

it('escapes MarkdownV2 specials in game titles', function () {
    $game = Game::factory()->create(['name' => 'Half-Life 3 (beta)', 'slug' => 'half-life-3-beta']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-29 00:00:00']);

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect($output)->toContain('Half\\-Life 3 \\(beta\\)');
});

it('returns empty string for an empty payload', function () {
    GameList::query()->delete();

    $output = $this->formatter->format($this->collector->forUpcomingWeek());

    expect($output)->toBe('');
});
