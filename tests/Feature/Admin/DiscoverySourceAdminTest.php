<?php

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\GameListImportService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('blocks non-admin users', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/discovery-sources')
        ->assertForbidden();
});

it('renders pending, active and inactive sections', function () {
    DiscoverySource::factory()->agentProposed()->create(['name' => 'Proposed Outlet']);
    DiscoverySource::factory()->create(['name' => 'Active Outlet']);
    DiscoverySource::factory()->create(['name' => 'Old Outlet', 'status' => DiscoverySourceStatusEnum::Disabled]);

    $this->actingAs($this->admin)
        ->get('/admin/discovery-sources')
        ->assertSuccessful()
        ->assertSee('Pending review')
        ->assertSee('Proposed Outlet')
        ->assertSee('Covered three releases')
        ->assertSee('Active Outlet')
        ->assertSee('Disabled &amp; rejected', false)
        ->assertSee('Old Outlet')
        ->assertSee('Throw links here');
});

it('flags low-yield sources on the active table', function () {
    DiscoverySource::factory()->dead()->create(['name' => 'Dead Outlet']);

    $this->actingAs($this->admin)
        ->get('/admin/discovery-sources')
        ->assertSuccessful()
        ->assertSee('Low yield');
});

it('classifies pasted links into pending sources', function () {
    $this->actingAs($this->admin)
        ->post('/admin/discovery-sources/intake', [
            'links' => "https://www.youtube.com/@SomeChannel\nhttps://x.com/SomeAccount\nhttps://www.randomblog.com",
            'purpose' => 'releases',
        ])
        ->assertRedirect(route('admin.discovery-sources.index'));

    $youtube = DiscoverySource::where('locator', '@somechannel')->first();
    $blog = DiscoverySource::where('locator', 'randomblog.com')->first();

    expect(DiscoverySource::count())->toBe(3)
        ->and($youtube->kind)->toBe(DiscoverySourceKindEnum::Youtube)
        ->and($youtube->status)->toBe(DiscoverySourceStatusEnum::Pending)
        ->and($youtube->origin)->toBe(DiscoverySourceOriginEnum::Admin)
        ->and($youtube->purpose)->toBe(DiscoverySourcePurposeEnum::Releases)
        ->and($youtube->needs_enrichment)->toBeFalse()
        ->and($blog->kind)->toBe(DiscoverySourceKindEnum::Press)
        ->and($blog->needs_enrichment)->toBeTrue();
});

it('skips duplicates on intake', function () {
    DiscoverySource::factory()->create(['locator' => '@somechannel']);

    $this->actingAs($this->admin)
        ->post('/admin/discovery-sources/intake', [
            'links' => 'https://www.youtube.com/@SomeChannel',
        ])
        ->assertRedirect();

    expect(DiscoverySource::count())->toBe(1);
});

it('confirms, rejects, disables and enables a source', function () {
    $source = DiscoverySource::factory()->pending()->create();

    $this->actingAs($this->admin)->post("/admin/discovery-sources/{$source->id}/confirm")->assertRedirect();
    expect($source->refresh()->status)->toBe(DiscoverySourceStatusEnum::Active);

    $this->actingAs($this->admin)->post("/admin/discovery-sources/{$source->id}/disable")->assertRedirect();
    expect($source->refresh()->status)->toBe(DiscoverySourceStatusEnum::Disabled);

    $this->actingAs($this->admin)->post("/admin/discovery-sources/{$source->id}/enable")->assertRedirect();
    expect($source->refresh()->status)->toBe(DiscoverySourceStatusEnum::Active);

    $this->actingAs($this->admin)->post("/admin/discovery-sources/{$source->id}/reject")->assertRedirect();
    expect($source->refresh()->status)->toBe(DiscoverySourceStatusEnum::Rejected);
});

it('bulk-confirms and bulk-rejects only pending sources', function () {
    $pendingA = DiscoverySource::factory()->pending()->create();
    $pendingB = DiscoverySource::factory()->pending()->create();
    $active = DiscoverySource::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/discovery-sources/bulk', [
            'action' => 'confirm',
            'source_ids' => [$pendingA->id, $active->id],
        ])
        ->assertRedirect();

    expect($pendingA->refresh()->status)->toBe(DiscoverySourceStatusEnum::Active)
        ->and($pendingB->refresh()->status)->toBe(DiscoverySourceStatusEnum::Pending);

    $this->actingAs($this->admin)
        ->post('/admin/discovery-sources/bulk', [
            'action' => 'reject',
            'source_ids' => [$pendingB->id],
        ])
        ->assertRedirect();

    expect($pendingB->refresh()->status)->toBe(DiscoverySourceStatusEnum::Rejected);
});

it('updates name and purpose', function () {
    $source = DiscoverySource::factory()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/discovery-sources/{$source->id}", [
            'name' => 'Renamed Outlet',
            'purpose' => 'news',
        ])
        ->assertRedirect();

    expect($source->refresh()->name)->toBe('Renamed Outlet')
        ->and($source->purpose)->toBe(DiscoverySourcePurposeEnum::News);
});

it('deletes a source', function () {
    $source = DiscoverySource::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/discovery-sources/{$source->id}")
        ->assertRedirect();

    expect(DiscoverySource::find($source->id))->toBeNull();
});

it('feeds promote and reject decisions back into source yield counters', function () {
    $target = GameList::factory()->yearly()->system()->create([
        'user_id' => $this->admin->id,
        'slug' => 'releases-2026',
        'start_at' => '2026-01-01',
        'end_at' => '2026-12-31',
    ]);
    $staging = app(GameListImportService::class)->stagingListFor($target);

    $sourceA = DiscoverySource::factory()->create();
    $sourceB = DiscoverySource::factory()->create();

    $promotedGame = Game::factory()->create(['igdb_id' => 500, 'name' => 'Promoted Game']);
    $rejectedGame = Game::factory()->create(['igdb_id' => 501, 'name' => 'Rejected Game']);

    $staging->games()->attach($promotedGame->id, [
        'order' => 1,
        'release_date' => '2026-10-15',
        'import_source_ids' => json_encode([$sourceA->id, $sourceB->id]),
    ]);
    $staging->games()->attach($rejectedGame->id, [
        'order' => 2,
        'release_date' => '2026-11-15',
        'import_source_ids' => json_encode([$sourceB->id]),
    ]);

    $this->actingAs($this->admin)
        ->postJson('/admin/system-lists/import/releases-2026-import/games/promote', ['game_ids' => [$promotedGame->id]])
        ->assertSuccessful();

    $this->actingAs($this->admin)
        ->postJson('/admin/system-lists/import/releases-2026-import/games/reject', ['game_ids' => [$rejectedGame->id]])
        ->assertSuccessful();

    expect($sourceA->refresh()->items_promoted)->toBe(1)
        ->and($sourceA->items_rejected)->toBe(0)
        ->and($sourceB->refresh()->items_promoted)->toBe(1)
        ->and($sourceB->items_rejected)->toBe(1);
});
