<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/*' => Http::response([], 200),
        'store.steampowered.com/*' => Http::response([], 200),
    ]);
});

it('can create events list via admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post('/admin/system-lists', [
            'name' => 'Summer Games Fest 2026',
            'description' => 'Games announced at Summer Games Fest',
            'list_type' => 'events',
            'is_active' => true,
            'is_public' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('game_lists', [
        'name' => 'Summer Games Fest 2026',
        'list_type' => 'events',
        'is_system' => true,
        'is_active' => true,
        'is_public' => true,
    ]);
});

it('shows events lists in system lists index', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->events()->system()->create([
        'name' => 'Test Event 1',
        'slug' => 'test-event-1',
    ]);

    GameList::factory()->events()->system()->create([
        'name' => 'Test Event 2',
        'slug' => 'test-event-2',
    ]);

    $response = $this->actingAs($admin)->get('/admin/system-lists');

    $response->assertStatus(200);
    $response->assertSee('Events Lists');
    $response->assertSee('Test Event 1');
    $response->assertSee('Test Event 2');
});

it('can add game to events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-games-fest-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/events/summer-games-fest-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['success' => true]);

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('can remove game from events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-games-fest-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->delete("/admin/system-lists/events/summer-games-fest-2026/games/{$game->id}");

    $response->assertJson(['success' => true]);

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeFalse();
});

it('can edit events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Old Event Name',
        'slug' => 'old-event-name',
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/old-event-name', [
            'name' => 'New Event Name',
            'description' => 'Updated description',
        ]);

    $response->assertRedirect();

    $list->refresh();
    expect($list->name)->toBe('New Event Name');
    expect($list->description)->toBe('Updated description');
});

it('can delete events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Event to Delete',
        'slug' => 'event-to-delete',
    ]);

    $response = $this->actingAs($admin)
        ->delete('/admin/system-lists/events/event-to-delete');

    $response->assertRedirect('/admin/system-lists');

    $this->assertDatabaseMissing('game_lists', [
        'slug' => 'event-to-delete',
    ]);
});

it('can toggle events list active status', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'test-event',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->patch('/admin/system-lists/events/test-event/toggle');

    $response->assertJson(['success' => true, 'is_active' => false]);

    $list->refresh();
    expect($list->is_active)->toBeFalse();
});

it('events list type is recognized as system list type', function () {
    expect(ListTypeEnum::EVENTS->isSystemListType())->toBeTrue();
});

it('events list scope works correctly', function () {
    GameList::factory()->events()->system()->create(['name' => 'Event List']);
    GameList::factory()->yearly()->system()->create(['name' => 'Yearly List']);

    $eventsLists = GameList::events()->get();

    expect($eventsLists)->toHaveCount(1);
    expect($eventsLists->first()->name)->toBe('Event List');
});

it('isEvents helper method works correctly', function () {
    $eventsList = GameList::factory()->events()->system()->create();
    $yearlyList = GameList::factory()->yearly()->system()->create();

    expect($eventsList->isEvents())->toBeTrue();
    expect($yearlyList->isEvents())->toBeFalse();
});

it('prevents non-admin from creating events list', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->post('/admin/system-lists', [
            'name' => 'Unauthorized Event',
            'list_type' => 'events',
        ]);

    $response->assertStatus(403);
});

// Event Data Tests

it('can save event data when updating events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Test Event',
        'slug' => 'test-event',
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/test-event', [
            'name' => 'Test Event',
            'event_time' => '2026-01-15T19:00',
            'event_timezone' => 'America/New_York',
            'event_about' => 'This is a test event description.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'social_twitter' => 'https://x.com/testevent',
            'social_youtube' => 'https://youtube.com/@testevent',
            'social_twitch' => 'https://twitch.tv/testevent',
            'social_discord' => 'https://discord.gg/testevent',
        ]);

    $response->assertRedirect();

    $list->refresh();
    expect($list->event_data)->toBeArray();
    expect($list->event_data['event_time'])->toBe('2026-01-15T19:00');
    expect($list->event_data['event_timezone'])->toBe('America/New_York');
    expect($list->event_data['about'])->toBe('This is a test event description.');
    expect($list->event_data['video_url'])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    expect($list->event_data['social_links']['twitter'])->toBe('https://x.com/testevent');
    expect($list->event_data['social_links']['youtube'])->toBe('https://youtube.com/@testevent');
    expect($list->event_data['social_links']['twitch'])->toBe('https://twitch.tv/testevent');
    expect($list->event_data['social_links']['discord'])->toBe('https://discord.gg/testevent');
});

it('getEventTime returns Carbon instance with correct timezone', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'event_time' => '2026-01-15T19:00',
            'event_timezone' => 'America/New_York',
        ],
    ]);

    $eventTime = $list->getEventTime();

    expect($eventTime)->toBeInstanceOf(Carbon::class);
    expect($eventTime->format('Y-m-d H:i'))->toBe('2026-01-15 19:00');
    expect($eventTime->timezone->getName())->toBe('America/New_York');
});

it('getEventTime returns null when no event time set', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => null,
    ]);

    expect($list->getEventTime())->toBeNull();
});

it('getEventTimezone returns timezone string', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'event_timezone' => 'Europe/London',
        ],
    ]);

    expect($list->getEventTimezone())->toBe('Europe/London');
});

it('getEventAbout returns about text', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'about' => 'This is the event description.',
        ],
    ]);

    expect($list->getEventAbout())->toBe('This is the event description.');
});

it('getSocialLinks returns social links array', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'social_links' => [
                'twitter' => 'https://x.com/test',
                'discord' => 'https://discord.gg/test',
            ],
        ],
    ]);

    $links = $list->getSocialLinks();

    expect($links)->toBeArray();
    expect($links['twitter'])->toBe('https://x.com/test');
    expect($links['discord'])->toBe('https://discord.gg/test');
});

it('getVideoUrl returns video URL', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://www.youtube.com/watch?v=abc123',
        ],
    ]);

    expect($list->getVideoUrl())->toBe('https://www.youtube.com/watch?v=abc123');
});

it('hasVideo returns true when video URL is set', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://www.youtube.com/watch?v=abc123',
        ],
    ]);

    expect($list->hasVideo())->toBeTrue();
});

it('hasVideo returns false when no video URL', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => null,
    ]);

    expect($list->hasVideo())->toBeFalse();
});

it('hasSocialLinks returns true when social links exist', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'social_links' => [
                'twitter' => 'https://x.com/test',
            ],
        ],
    ]);

    expect($list->hasSocialLinks())->toBeTrue();
});

it('hasSocialLinks returns false when no social links', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'social_links' => [],
        ],
    ]);

    expect($list->hasSocialLinks())->toBeFalse();
});

it('getVideoEmbedUrl converts YouTube URL to embed format', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ],
    ]);

    expect($list->getVideoEmbedUrl())->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

it('getVideoEmbedUrl converts YouTube short URL to embed format', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ],
    ]);

    expect($list->getVideoEmbedUrl())->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

it('getVideoEmbedUrl converts Twitch VOD URL to embed format', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://www.twitch.tv/videos/123456789',
        ],
    ]);

    $embedUrl = $list->getVideoEmbedUrl();

    expect($embedUrl)->toContain('player.twitch.tv');
    expect($embedUrl)->toContain('video=123456789');
});

it('getVideoEmbedUrl converts Twitch channel URL to embed format', function () {
    $list = GameList::factory()->events()->system()->create([
        'event_data' => [
            'video_url' => 'https://www.twitch.tv/twitchchannel',
        ],
    ]);

    $embedUrl = $list->getVideoEmbedUrl();

    expect($embedUrl)->toContain('player.twitch.tv');
    expect($embedUrl)->toContain('channel=twitchchannel');
});

// Public Event Page Tests

it('shows event hero section with video when event has video', function () {
    $list = GameList::factory()->events()->system()->create([
        'name' => 'Test Event',
        'slug' => 'test-event',
        'is_public' => true,
        'is_active' => true,
        'event_data' => [
            'event_time' => now()->subHour()->format('Y-m-d\TH:i'),
            'event_timezone' => 'UTC',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ],
    ]);

    $response = $this->get('/list/events/test-event');

    $response->assertStatus(200);
    $response->assertSee('youtube.com/embed/dQw4w9WgXcQ');
});

it('shows event start time and social links in hero section', function () {
    $list = GameList::factory()->events()->system()->create([
        'name' => 'Test Event',
        'slug' => 'test-event',
        'is_public' => true,
        'is_active' => true,
        'event_data' => [
            'event_time' => now()->subHour()->format('Y-m-d\TH:i'),
            'event_timezone' => 'America/New_York',
            'social_links' => [
                'twitter' => 'https://x.com/testevent',
            ],
        ],
    ]);

    $response = $this->get('/list/events/test-event');

    $response->assertStatus(200);
    $response->assertSee('x.com/testevent');
});

it('shows games list when event has started', function () {
    $list = GameList::factory()->events()->system()->create([
        'name' => 'Started Event',
        'slug' => 'started-event',
        'is_public' => true,
        'is_active' => true,
        'event_data' => [
            'event_time' => now()->subHour()->format('Y-m-d\TH:i'),
            'event_timezone' => 'UTC',
        ],
    ]);

    $game = Game::factory()->create(['name' => 'Test Game']);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->get('/list/events/started-event');

    $response->assertStatus(200);
    $response->assertSee('Showing');
    $response->assertSee('1');
    $response->assertSee('game');
});

it('shows placeholder when event has not started yet', function () {
    $list = GameList::factory()->events()->system()->create([
        'name' => 'Future Event',
        'slug' => 'future-event',
        'is_public' => true,
        'is_active' => true,
        'event_data' => [
            'event_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'event_timezone' => 'UTC',
        ],
    ]);

    $game = Game::factory()->create(['name' => 'Hidden Game']);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->get('/list/events/future-event');

    $response->assertStatus(200);
    $response->assertSee('The Event Hasn', false);
    $response->assertSee('t Started Yet', false);
    $response->assertSee('The games will be revealed when the event begins');
    $response->assertSee('Your time:');
    $response->assertDontSee('Showing');
});

it('shows games when event has no event time set', function () {
    $list = GameList::factory()->events()->system()->create([
        'name' => 'No Time Event',
        'slug' => 'no-time-event',
        'is_public' => true,
        'is_active' => true,
        'event_data' => null,
    ]);

    $game = Game::factory()->create(['name' => 'Visible Game']);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->get('/list/events/no-time-event');

    $response->assertStatus(200);
    $response->assertSee('1');
    $response->assertSee('game');
    $response->assertDontSee("The Event Hasn't Started Yet");
});

it('admin edit page shows event detail fields for events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Edit Test Event',
        'slug' => 'edit-test-event',
    ]);

    $response = $this->actingAs($admin)->get('/admin/system-lists/events/edit-test-event/edit');

    $response->assertStatus(200);
    $response->assertSee('Event Details');
    $response->assertSee('Event Time');
    $response->assertSee('Timezone');
    $response->assertSee('Video URL');
    $response->assertSee('About');
    $response->assertSee('Social Links');
    $response->assertSee('Twitter / X');
    $response->assertSee('YouTube');
    $response->assertSee('Twitch');
    $response->assertSee('Discord');
});

it('admin edit page does not show event fields for non-events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->yearly()->system()->create([
        'name' => 'Yearly List',
        'slug' => 'yearly-list',
        'start_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/system-lists/yearly/yearly-list/edit');

    $response->assertStatus(200);
    $response->assertDontSee('Event Details');
    $response->assertDontSee('Event Time');
    $response->assertDontSee('social_twitter');
});

it('admin edit page shows the IGDB event id field for events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->events()->system()->create([
        'name' => 'IGDB Field Event',
        'slug' => 'igdb-field-event',
    ]);

    $response = $this->actingAs($admin)->get('/admin/system-lists/events/igdb-field-event/edit');

    $response->assertStatus(200);
    $response->assertSee('IGDB Event ID');
    $response->assertSee('data-vue-component="igdb-event-search"', false);
    $response->assertSee(route('admin.system-lists.igdb-events.search'), false);
});

it('shows the Sync from IGDB button on every events list, enabled only when linked', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->events()->system()->create(['slug' => 'linked-ev', 'igdb_event_id' => 137]);
    GameList::factory()->events()->system()->create(['slug' => 'unlinked-ev', 'igdb_event_id' => null]);

    // Linked: button present and active (carries the sync url, not disabled).
    $this->actingAs($admin)->get('/admin/system-lists/events/linked-ev/edit')
        ->assertSee('Sync from IGDB')
        ->assertSee(route('admin.system-lists.sync-igdb', ['events', 'linked-ev']), false);

    // Unlinked: button still visible but disabled with a hint.
    $this->actingAs($admin)->get('/admin/system-lists/events/unlinked-ev/edit')
        ->assertSee('Sync from IGDB')
        ->assertSee('Set and save an IGDB Event ID first', false);
});

it('does not show the Sync from IGDB button on non-events lists', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->yearly()->system()->create(['slug' => 'yearly-2026', 'start_at' => now()]);

    $this->actingAs($admin)->get('/admin/system-lists/yearly/yearly-2026/edit')
        ->assertDontSee('Sync from IGDB');
});

it('can save the igdb_event_id when updating an events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Link Event',
        'slug' => 'link-event',
        'igdb_event_id' => null,
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/link-event', [
            'name' => 'Link Event',
            'igdb_event_id' => 251,
        ]);

    $response->assertRedirect();

    expect($list->refresh()->igdb_event_id)->toBe(251);
});

it('can clear the igdb_event_id by submitting it empty', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Unlink Event',
        'slug' => 'unlink-event',
        'igdb_event_id' => 999,
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/unlink-event', [
            'name' => 'Unlink Event',
            'igdb_event_id' => '',
        ]);

    $response->assertRedirect();

    expect($list->refresh()->igdb_event_id)->toBeNull();
});

it('persists the youtube channel url when updating an events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create(['slug' => 'channel-ev', 'name' => 'Channel Event']);

    $this->actingAs($admin)->patch('/admin/system-lists/events/channel-ev', [
        'name' => 'Channel Event',
        'youtube_channel_url' => 'https://www.youtube.com/@TheEvent/videos',
    ])->assertRedirect();

    expect($list->refresh()->event_data['youtube_channel_url'])->toBe('https://www.youtube.com/@TheEvent/videos');
});

it('marks a trailer as manual when an admin sets a game pivot video_url', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create(['slug' => 'pivot-ev']);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => null, 'video_url_manual' => false]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->patch("/admin/system-lists/events/pivot-ev/games/{$game->id}/pivot", [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertOk();

    $pivot = $list->games()->first()->pivot;
    expect((bool) $pivot->video_url_manual)->toBeTrue()
        ->and($pivot->video_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('persists the igdb_slug into event_data when updating an events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Slug Event',
        'slug' => 'slug-event',
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/slug-event', [
            'name' => 'Slug Event',
            'igdb_event_id' => 251,
            'igdb_slug' => 'future-games-show',
        ]);

    $response->assertRedirect();

    expect($list->refresh()->event_data['igdb_slug'])->toBe('future-games-show');
});

it('rejects an igdb_event_id already used by another events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->events()->system()->create([
        'name' => 'Owner Event',
        'slug' => 'owner-event',
        'igdb_event_id' => 251,
    ]);
    $other = GameList::factory()->events()->system()->create([
        'name' => 'Other Event',
        'slug' => 'other-event',
        'igdb_event_id' => null,
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/other-event', [
            'name' => 'Other Event',
            'igdb_event_id' => 251,
        ]);

    $response->assertSessionHasErrors('igdb_event_id');
    expect($other->refresh()->igdb_event_id)->toBeNull();
});
