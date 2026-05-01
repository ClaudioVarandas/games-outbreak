<?php

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Services\Broadcasts\Formatters\MonthlyTelegramMessageFormatter;
use App\Services\MonthlyChoicesCollector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-23 09:00:00', 'UTC'));

    $this->formatter = new MonthlyTelegramMessageFormatter;

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->collector = app(MonthlyChoicesCollector::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders MarkdownV2 output with header, per-game link, and CTA', function () {
    $split = Game::factory()->create(['name' => 'Split Fiction', 'slug' => 'split-fiction']);
    $this->list->games()->attach($split->id, [
        'release_date' => '2026-05-12 00:00:00',
        'platforms' => json_encode([PlatformEnum::PS5->value, PlatformEnum::PC->value]),
    ]);

    $output = $this->formatter->format($this->collector->forUpcomingMonth());

    expect($output)->toContain("*🎮 Games Outbreak — Next Month's Choices*");
    expect($output)->not->toContain('PREVIEW');
    expect($output)->toContain('_May 2026_');
    expect($output)->toContain('[Split Fiction](');
    expect($output)->toContain('— May 12 · PS5/PC');
    expect($output)->toContain('[See the full list →]');
});

it('injects PREVIEW marker into header when payload is preview', function () {
    $game = Game::factory()->create(['name' => 'Sneak Peek', 'slug' => 'sneak-peek']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-05-05 00:00:00']);

    $output = $this->formatter->format(
        $this->collector->forUpcomingMonth(null, isPreview: true),
    );

    expect($output)->toContain("*🎮 Games Outbreak — PREVIEW — Next Month's Choices*");
    expect($output)->toContain('_May 2026_');
});

it('escapes MarkdownV2 specials in game titles', function () {
    $game = Game::factory()->create(['name' => 'Half-Life 3 (beta)', 'slug' => 'half-life-3-beta']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-05-05 00:00:00']);

    $output = $this->formatter->format($this->collector->forUpcomingMonth());

    expect($output)->toContain('Half\\-Life 3 \\(beta\\)');
});

it('returns empty string for an empty payload', function () {
    GameList::query()->delete();

    $output = $this->formatter->format($this->collector->forUpcomingMonth());

    expect($output)->toBe('');
});
