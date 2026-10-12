<?php

use App\Enums\PcPortCategoryEnum;
use App\Enums\PcPortStatusEnum;
use App\Jobs\FetchPcPortCover;
use App\Models\PcPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.import.token' => 'test-import-token']);
    Queue::fake();
});

function portApiHeaders(): array
{
    return ['Authorization' => 'Bearer test-import-token'];
}

function portRow(array $overrides = []): array
{
    return array_merge([
        'slug' => 'banjo-kazooie',
        'game_title' => 'Banjo-Kazooie',
        'original_platform' => 'Nintendo 64',
        'project_name' => 'BanjoRecomp',
        'technique' => 'static_recompilation',
        'status' => 'released',
        'windows_support' => 'true',
        'linux_support' => 'experimental_untested',
        'last_activity' => '2026-09-01',
        'last_checked_at' => '2026-10-11',
        'notes' => 'N64Recomp-based port.',
        'repo_url' => 'https://github.com/example/banjo',
        'cover_source_url' => 'https://cdn2.steamgriddb.com/grid/abc.png',
        'save_support' => 'true',
        'features' => 'Widescreen',
        'category' => 'pc_port',
        'native_pc_port' => 'yes',
    ], $overrides);
}

it('returns 503 when no import token is configured', function () {
    config(['services.import.token' => null]);

    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], portApiHeaders())->assertStatus(503);
});

it('returns 401 on a wrong token', function () {
    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

it('creates ports, normalizes technique and stores long-tail fields as details', function () {
    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], portApiHeaders())
        ->assertSuccessful()
        ->assertJsonPath('summary.created', 1)
        ->assertJsonPath('page_url', route('ports'));

    $port = PcPort::where('slug', 'banjo-kazooie')->sole();

    expect($port->technique)->toBe('static-recompilation')
        ->and($port->status)->toBe(PcPortStatusEnum::Released)
        ->and($port->category)->toBe(PcPortCategoryEnum::PcPort)
        ->and($port->details)->toBe(['save_support' => 'true', 'features' => 'Widescreen'])
        ->and($port->last_activity->toDateString())->toBe('2026-09-01');

    Queue::assertPushed(FetchPcPortCover::class, fn (FetchPcPortCover $job) => $job->pcPortId === $port->id);
});

it('reports unchanged rows and only re-fetches the cover when its url changes', function () {
    $port = PcPort::factory()->create([
        'slug' => 'banjo-kazooie',
        'cover_source_url' => 'https://cdn2.steamgriddb.com/grid/abc.png',
        'cover_path' => 'ports/banjo-kazooie.png',
    ]);
    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], portApiHeaders())->assertJsonPath('summary.updated', 1);
    Queue::assertNothingPushed();

    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], portApiHeaders())->assertJsonPath('summary.unchanged', 1);
    Queue::assertNothingPushed();

    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow(['cover_source_url' => 'https://cdn2.steamgriddb.com/grid/new.png'])]], portApiHeaders())
        ->assertJsonPath('summary.updated', 1);
    Queue::assertPushed(FetchPcPortCover::class, 1);

    expect($port->fresh()->cover_source_url)->toEndWith('new.png');
});

it('turns non-native entries into radar but keeps mods as mods', function () {
    $this->postJson('/api/v1/ports/sync', ['ports' => [
        portRow(['slug' => 'radar-one', 'native_pc_port' => 'no', 'category' => 'pc_port']),
        portRow(['slug' => 'mod-one', 'native_pc_port' => 'no', 'category' => 'mod']),
    ]], portApiHeaders())->assertSuccessful();

    expect(PcPort::where('slug', 'radar-one')->value('category'))->toBe(PcPortCategoryEnum::Radar)
        ->and(PcPort::where('slug', 'mod-one')->value('category'))->toBe(PcPortCategoryEnum::Mod);
});

it('prunes ports missing from the payload only when asked', function () {
    PcPort::factory()->create(['slug' => 'gone']);

    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()]], portApiHeaders())->assertJsonPath('summary.deleted', 0);
    expect(PcPort::where('slug', 'gone')->exists())->toBeTrue();

    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow()], 'prune' => true], portApiHeaders())->assertJsonPath('summary.deleted', 1);
    expect(PcPort::where('slug', 'gone')->exists())->toBeFalse();
});

it('rejects invalid rows', function (array $overrides, string $errorKey) {
    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow($overrides)]], portApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(PcPort::count())->toBe(0);
})->with([
    'missing slug' => [['slug' => null], 'ports.0.slug'],
    'bad slug' => [['slug' => 'Bad Slug'], 'ports.0.slug'],
    'missing title' => [['game_title' => null], 'ports.0.game_title'],
    'unknown status' => [['status' => 'abandoned'], 'ports.0.status'],
    'unknown technique' => [['technique' => 'emulation'], 'ports.0.technique'],
    'bad date' => [['last_activity' => '01/09/2026'], 'ports.0.last_activity'],
    'non steamgriddb cover' => [['cover_source_url' => 'https://example.com/cover.png'], 'ports.0.cover_source_url'],
    'bad repo url' => [['repo_url' => 'not a url'], 'ports.0.repo_url'],
]);

it('rejects duplicated slugs in one payload', function () {
    $this->postJson('/api/v1/ports/sync', ['ports' => [portRow(), portRow()]], portApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ports.0.slug');
});

it('lists synced ports', function () {
    PcPort::factory()->create(['slug' => 'alpha', 'cover_path' => 'ports/alpha.jpg']);

    $this->getJson('/api/v1/ports', portApiHeaders())
        ->assertSuccessful()
        ->assertJsonCount(1, 'ports')
        ->assertJsonPath('ports.0.slug', 'alpha')
        ->assertJsonPath('ports.0.has_cover', true);
});
