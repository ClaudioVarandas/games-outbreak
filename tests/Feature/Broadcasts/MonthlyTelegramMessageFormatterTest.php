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

it('uses the "This Month\'s Choices" header when the payload is current', function () {
    $game = Game::factory()->create(['name' => 'May Day Game', 'slug' => 'may-day-game']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-15 00:00:00']);

    $output = $this->formatter->format($this->collector->forCurrentMonth());

    expect($output)->toContain("*🎮 Games Outbreak — This Month's Choices*");
    expect($output)->not->toContain("Next Month's Choices");
    expect($output)->toContain('_April 2026_');
});

it('combines PREVIEW with This Month\'s Choices when both flags are set', function () {
    $game = Game::factory()->create(['name' => 'Mid-Month Sneak', 'slug' => 'mid-month-sneak']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-20 00:00:00']);

    $output = $this->formatter->format($this->collector->forCurrentMonth(null, isPreview: true));

    expect($output)->toContain("*🎮 Games Outbreak — PREVIEW — This Month's Choices*");
});

it('formatMessages returns a single message when the list fits', function () {
    Game::factory()->count(5)->create()->each(function (Game $game, int $i) {
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-05-01 00:00:00')->addDay($i)->toDateTimeString(),
        ]);
    });

    $messages = $this->formatter->formatMessages($this->collector->forUpcomingMonth());

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('[See the full list →]');
    expect($messages[0])->not->toContain('Part 1/');
});

it('formatMessages chunks long lists across multiple messages with parts label', function () {
    $games = Game::factory()->count(60)->create();
    foreach ($games as $i => $game) {
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-05-01 00:00:00')->addMinutes($i)->toDateTimeString(),
        ]);
    }

    $messages = $this->formatter->formatMessages(
        $this->collector->forUpcomingMonth(),
        maxChars: 1500,
    );

    expect(count($messages))->toBeGreaterThan(1);

    foreach ($messages as $message) {
        expect(strlen($message))->toBeLessThanOrEqual(1500);
        expect($message)->toContain("*🎮 Games Outbreak — Next Month's Choices*");
        expect($message)->toContain('Part ');
    }

    expect($messages[count($messages) - 1])->toContain('[See the full list →]');
    foreach (array_slice($messages, 0, -1) as $message) {
        expect($message)->not->toContain('[See the full list →]');
    }
});
