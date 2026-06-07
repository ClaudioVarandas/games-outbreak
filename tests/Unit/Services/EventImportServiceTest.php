<?php

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use App\Services\EventImportService;
use Carbon\Carbon;

function makeEventImportService(): EventImportService
{
    return app(EventImportService::class);
}

function sampleIgdbEvent(array $overrides = []): array
{
    return array_replace([
        'id' => 137,
        'name' => 'Summer Game Fest 2026',
        'description' => 'The big showcase.',
        'slug' => 'summer-game-fest-2026-igdb-owned',
        'start_time' => 1749500000,
        'end_time' => 1749510000,
        'time_zone' => 'America/Los_Angeles',
        'live_stream_url' => 'https://www.youtube.com/watch?v=ABC123',
        'event_networks' => [
            ['url' => 'https://twitter.com/sgf', 'network_type' => ['name' => 'Twitter']],
            ['url' => 'https://twitch.tv/sgf', 'network_type' => ['name' => 'Twitch']],
        ],
        'games' => [1, 2, 3],
        'videos' => [['video_id' => 'XYZ789']],
    ], $overrides);
}

it('maps core IGDB event fields to game list attributes', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['igdb_event_id'])->toBe(137)
        ->and($attrs['name'])->toBe('Summer Game Fest 2026')
        ->and($attrs['list_type'])->toBe(ListTypeEnum::EVENTS)
        ->and($attrs['is_system'])->toBeTrue()
        ->and($attrs['is_active'])->toBeTrue()
        ->and($attrs['user_id'])->toBeNull();
});

it('does not store the youtube channel url in the mapped attributes (set at persist time)', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['event_data'])->not->toHaveKey('youtube_channel_url');
});

it('does not map a top-level description (frontend uses event_data.about only)', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs)->not->toHaveKey('description')
        ->and($attrs['event_data']['about'])->toBe('The big showcase.');
});

it('generates our own slug from the name and ignores the IGDB slug', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['slug'])->toBe('summer-game-fest-2026')
        ->and($attrs['slug'])->not->toBe('summer-game-fest-2026-igdb-owned');
});

it('maps start and end unix timestamps to datetime attributes', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['start_at'])->toBeInstanceOf(Carbon::class)
        ->and($attrs['start_at']->timestamp)->toBe(1749500000)
        ->and($attrs['end_at']->timestamp)->toBe(1749510000);
});

it('stores event metadata in event_data using the existing helper keys', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['event_data']['event_timezone'])->toBe('America/Los_Angeles')
        ->and($attrs['event_data']['about'])->toBe('The big showcase.')
        ->and($attrs['event_data']['video_url'])->toBe('https://www.youtube.com/watch?v=ABC123')
        ->and($attrs['event_data']['social_links'])->toBe([
            ['label' => 'Twitter', 'url' => 'https://twitter.com/sgf'],
            ['label' => 'Twitch', 'url' => 'https://twitch.tv/sgf'],
        ]);
});

it('stores an event_time that getEventTime() resolves to the correct instant', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    $list = new GameList(['event_data' => $attrs['event_data']]);

    expect($list->getEventTime()->timestamp)->toBe(1749500000);
});

it('stores the IGDB slug in event_data for the external link', function () {
    $attrs = makeEventImportService()->mapEventToAttributes(sampleIgdbEvent());

    expect($attrs['event_data']['igdb_slug'])->toBe('summer-game-fest-2026-igdb-owned');
});

it('falls back to the first video when there is no live stream url', function () {
    $event = sampleIgdbEvent(['live_stream_url' => null]);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['event_data']['video_url'])->toBe('https://www.youtube.com/watch?v=XYZ789');
});

it('defaults the timezone to UTC when IGDB omits it', function () {
    $event = sampleIgdbEvent();
    unset($event['time_zone']);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['event_data']['event_timezone'])->toBe('UTC');
});

it('returns null dates and event_time when timestamps are missing', function () {
    $event = sampleIgdbEvent();
    unset($event['start_time'], $event['end_time']);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['start_at'])->toBeNull()
        ->and($attrs['end_at'])->toBeNull()
        ->and($attrs['event_data']['event_time'])->toBeNull();
});

it('returns an empty social_links array when there are no event networks', function () {
    $event = sampleIgdbEvent(['event_networks' => []]);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['event_data']['social_links'])->toBe([]);
});

it('labels a network as Website when the network type name is missing', function () {
    $event = sampleIgdbEvent(['event_networks' => [
        ['url' => 'https://example.com'],
    ]]);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['event_data']['social_links'])->toBe([
        ['label' => 'Website', 'url' => 'https://example.com'],
    ]);
});

it('uses a fallback name when IGDB omits the event name', function () {
    $event = sampleIgdbEvent();
    unset($event['name']);

    $attrs = makeEventImportService()->mapEventToAttributes($event);

    expect($attrs['name'])->toBe('Untitled Event')
        ->and($attrs['slug'])->toBe('untitled-event');
});
