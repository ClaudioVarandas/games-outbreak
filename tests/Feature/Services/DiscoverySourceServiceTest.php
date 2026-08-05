<?php

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;
use App\Services\DiscoverySourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sourceService(): DiscoverySourceService
{
    return app(DiscoverySourceService::class);
}

it('classifies youtube handle urls without enrichment', function () {
    $result = sourceService()->classifyUrl('https://www.youtube.com/@GameranxTV');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::Youtube)
        ->and($result['locator'])->toBe('@gameranxtv')
        ->and($result['needs_enrichment'])->toBeFalse();
});

it('flags youtube channel-id urls for enrichment', function () {
    $result = sourceService()->classifyUrl('https://www.youtube.com/channel/UCNvzD7Z-g64bPXxGzaQaa4g');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::Youtube)
        ->and($result['needs_enrichment'])->toBeTrue();
});

it('classifies x profile urls', function () {
    $result = sourceService()->classifyUrl('https://x.com/Wario64');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::X)
        ->and($result['locator'])->toBe('@wario64')
        ->and($result['needs_enrichment'])->toBeFalse();
});

it('does not treat x feature pages as profiles', function () {
    $result = sourceService()->classifyUrl('https://x.com/search?q=games');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::X)
        ->and($result['locator'])->toBeNull()
        ->and($result['needs_enrichment'])->toBeTrue();
});

it('guesses calendar for release-listing paths, keeping the query in the locator', function () {
    $result = sourceService()->classifyUrl('https://www.pushsquare.com/games/browse?releases=upcoming');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::Calendar)
        ->and($result['locator'])->toBe('pushsquare.com/games/browse?releases=upcoming')
        ->and($result['needs_enrichment'])->toBeTrue();
});

it('guesses press for plain domains', function () {
    $result = sourceService()->classifyUrl('https://www.eurogamer.net');

    expect($result['kind'])->toBe(DiscoverySourceKindEnum::Press)
        ->and($result['locator'])->toBe('eurogamer.net')
        ->and($result['name'])->toBe('Eurogamer')
        ->and($result['needs_enrichment'])->toBeTrue();
});

it('creates a pending source and deduplicates on the normalized locator', function () {
    $first = sourceService()->propose(['url' => 'https://www.youtube.com/@SomeChannel']);
    $second = sourceService()->propose(['url' => 'https://youtube.com/@somechannel']);

    expect($first['status'])->toBe('created')
        ->and($first['source']->status)->toBe(DiscoverySourceStatusEnum::Pending)
        ->and($first['source']->origin)->toBe(DiscoverySourceOriginEnum::Agent)
        ->and($second['status'])->toBe('duplicate')
        ->and($second['source']->id)->toBe($first['source']->id)
        ->and(DiscoverySource::count())->toBe(1);
});

it('rejects proposals with neither kind nor url', function () {
    $result = sourceService()->propose(['name' => 'Mystery']);

    expect($result['status'])->toBe('invalid')
        ->and(DiscoverySource::count())->toBe(0);
});

it('applies run reports: found totals, failure streaks and timestamps', function () {
    $source = DiscoverySource::factory()->create();

    sourceService()->applyRunReport([['id' => $source->id, 'items_found' => 5, 'fetch_ok' => true]]);
    sourceService()->applyRunReport([['id' => $source->id, 'items_found' => 0, 'fetch_ok' => false]]);
    sourceService()->applyRunReport([['id' => $source->id, 'items_found' => 0, 'fetch_ok' => false]]);

    $source->refresh();

    expect($source->runs_count)->toBe(3)
        ->and($source->items_found_total)->toBe(5)
        ->and($source->consecutive_failures)->toBe(2)
        ->and($source->last_run_at)->not->toBeNull()
        ->and($source->last_success_at)->not->toBeNull();

    sourceService()->applyRunReport([['id' => $source->id, 'items_found' => 2, 'fetch_ok' => true]]);

    expect($source->refresh()->consecutive_failures)->toBe(0);
});

it('records review outcomes with per-source multiplicity', function () {
    $a = DiscoverySource::factory()->create();
    $b = DiscoverySource::factory()->create();

    sourceService()->recordReviewOutcome([$a->id, $a->id, $b->id], promoted: true);
    sourceService()->recordReviewOutcome([$b->id], promoted: false);

    expect($a->refresh()->items_promoted)->toBe(2)
        ->and($b->refresh()->items_promoted)->toBe(1)
        ->and($b->items_rejected)->toBe(1);
});

it('computes score and dead flags', function () {
    $fresh = DiscoverySource::factory()->create();
    $productive = DiscoverySource::factory()->create(['items_found_total' => 10, 'items_promoted' => 7]);
    $dead = DiscoverySource::factory()->dead()->create();

    expect($fresh->score())->toBeNull()
        ->and($fresh->looksDead())->toBeFalse()
        ->and($productive->score())->toBe(0.7)
        ->and($dead->looksDead())->toBeTrue();
});
