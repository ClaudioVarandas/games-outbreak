<?php

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.import.token' => 'test-import-token']);
});

function sourceApiHeaders(): array
{
    return ['Authorization' => 'Bearer test-import-token'];
}

it('returns 503 when no import token is configured', function () {
    config(['services.import.token' => null]);

    $this->getJson('/api/v1/sources', sourceApiHeaders())->assertStatus(503);
});

it('returns 401 on a wrong token', function () {
    $this->getJson('/api/v1/sources', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

it('lists only active sources with their score', function () {
    $active = DiscoverySource::factory()->create(['items_found_total' => 4, 'items_promoted' => 2]);
    DiscoverySource::factory()->pending()->create();
    DiscoverySource::factory()->create(['status' => DiscoverySourceStatusEnum::Disabled]);
    DiscoverySource::factory()->create(['status' => DiscoverySourceStatusEnum::Rejected]);

    $this->getJson('/api/v1/sources', sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('sources.0.id', $active->id)
        ->assertJsonPath('sources.0.score', 0.5);
});

it('filters by covered purpose: releases includes both', function () {
    DiscoverySource::factory()->create(['purpose' => DiscoverySourcePurposeEnum::Releases]);
    DiscoverySource::factory()->create(['purpose' => DiscoverySourcePurposeEnum::Both]);
    DiscoverySource::factory()->create(['purpose' => DiscoverySourcePurposeEnum::News]);

    $this->getJson('/api/v1/sources?purpose=releases', sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(2, 'sources');

    $this->getJson('/api/v1/sources?purpose=news', sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(2, 'sources');
});

it('filters by kind', function () {
    DiscoverySource::factory()->create(['kind' => DiscoverySourceKindEnum::Youtube, 'locator' => '@one']);
    DiscoverySource::factory()->create();

    $this->getJson('/api/v1/sources?kind=youtube', sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('sources.0.kind', 'youtube');
});

it('rejects an invalid purpose filter', function () {
    $this->getJson('/api/v1/sources?purpose=bogus', sourceApiHeaders())->assertUnprocessable();
});

it('creates pending agent proposals and reports duplicates', function () {
    DiscoverySource::factory()->create(['locator' => 'eurogamer.net']);

    $this->postJson('/api/v1/sources/propose', [
        'sources' => [
            ['url' => 'https://www.youtube.com/@NewChannel', 'purpose' => 'releases', 'confidence' => 'high', 'evidence' => 'Covered two games the sweep missed.'],
            ['url' => 'https://www.eurogamer.net'],
            ['name' => 'No url or kind'],
        ],
    ], sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'created')
        ->assertJsonPath('results.0.locator', '@newchannel')
        ->assertJsonPath('results.1.status', 'duplicate')
        ->assertJsonPath('results.2.status', 'invalid');

    $created = DiscoverySource::where('locator', '@newchannel')->first();

    expect($created->status)->toBe(DiscoverySourceStatusEnum::Pending)
        ->and($created->origin)->toBe(DiscoverySourceOriginEnum::Agent)
        ->and($created->purpose)->toBe(DiscoverySourcePurposeEnum::Releases)
        ->and($created->evidence)->toContain('sweep missed');
});

it('validates proposal payloads', function () {
    $this->postJson('/api/v1/sources/propose', ['sources' => []], sourceApiHeaders())->assertUnprocessable();

    $this->postJson('/api/v1/sources/propose', [
        'sources' => [['url' => 'not-a-url']],
    ], sourceApiHeaders())->assertUnprocessable();
});

it('stores discovery source attribution when staging import items', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'releases-2026',
        'start_at' => '2026-01-01',
        'end_at' => '2026-12-31',
    ]);
    $game = Game::factory()->create(['igdb_id' => 600]);
    $source = DiscoverySource::factory()->create();

    $this->postJson('/api/v1/import/list-items', [
        'list_slug' => $target->slug,
        'items' => [
            ['igdb_id' => 600, 'release_date' => '2026-08-10', 'source_ids' => [$source->id]],
        ],
    ], sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'attached');

    $staging = $target->importStagingList()->first();
    $pivot = $staging->games()->where('games.id', $game->id)->first()->pivot;

    expect(json_decode((string) $pivot->import_source_ids, true))->toBe([$source->id]);
});

it('applies run reports and skips unknown source ids', function () {
    $source = DiscoverySource::factory()->create();

    $this->postJson('/api/v1/sources/run-report', [
        'window' => '2026-08',
        'sources' => [
            ['id' => $source->id, 'items_found' => 7, 'fetch_ok' => true],
            ['id' => 999999, 'items_found' => 3, 'fetch_ok' => true],
        ],
    ], sourceApiHeaders())
        ->assertSuccessful()
        ->assertJsonPath('updated', 1);

    expect($source->refresh()->items_found_total)->toBe(7)
        ->and($source->runs_count)->toBe(1);
});
