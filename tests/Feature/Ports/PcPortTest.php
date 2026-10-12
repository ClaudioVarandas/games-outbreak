<?php

use App\Enums\PcPortCategoryEnum;
use App\Enums\PcPortMetricEnum;
use App\Enums\PcPortSectionEnum;
use App\Enums\PcPortStatusEnum;
use App\Enums\PcPortSupportStateEnum;
use App\Enums\PcPortTechniqueEnum;
use App\Models\PcPort;

it('gives every enum case a label and literal classes', function () {
    foreach (PcPortStatusEnum::cases() as $status) {
        expect($status->label())->not->toBeEmpty()->and($status->pillClasses())->toContain('text-');
    }
    foreach (PcPortSupportStateEnum::cases() as $state) {
        expect($state->label())->not->toBeEmpty()->and($state->pillClasses())->toContain('text-');
    }
    foreach (PcPortMetricEnum::cases() as $metric) {
        expect($metric->tileClasses())->toContain('border-')->and($metric->valueClasses())->toContain('text-');
    }
    foreach (PcPortTechniqueEnum::cases() as $technique) {
        expect($technique->label())->not->toBeEmpty();
    }
    foreach (PcPortSectionEnum::cases() as $section) {
        expect($section->label())->not->toBeEmpty()->and($section->description())->not->toBeEmpty();
    }
});

it('only treats finished statuses as playable', function () {
    $playable = collect(PcPortStatusEnum::cases())->filter->isPlayable()->values()->all();

    expect($playable)->toBe([PcPortStatusEnum::Released, PcPortStatusEnum::Playable]);
});

it('normalizes technique spellings', function (?string $input, ?string $expected) {
    expect(PcPortTechniqueEnum::normalize($input))->toBe($expected);
})->with([
    ['static_recompilation', 'static-recompilation'],
    [' Decompilation-Native-Port ', 'decompilation-native-port'],
    ['', null],
    [null, null],
]);

it('maps csv support values to states', function (?string $value, PcPortSupportStateEnum $expected) {
    expect(PcPortSupportStateEnum::fromCsvValue($value))->toBe($expected);
})->with([
    ['true', PcPortSupportStateEnum::Supported],
    ['false', PcPortSupportStateEnum::Unsupported],
    ['', PcPortSupportStateEnum::Unknown],
    [null, PcPortSupportStateEnum::Unknown],
    ['experimental_untested', PcPortSupportStateEnum::Experimental],
]);

it('derives the tracker section like the standalone generator', function (array $attributes, PcPortSectionEnum $expected) {
    $port = new PcPort(['category' => PcPortCategoryEnum::PcPort, 'original_platform' => 'Xbox 360', 'technique' => 'static-recompilation', ...$attributes]);

    expect($port->section())->toBe($expected);
})->with([
    'native port' => [[], PcPortSectionEnum::NativePorts],
    'mod' => [['category' => PcPortCategoryEnum::Mod], PcPortSectionEnum::Mods],
    'radar' => [['category' => PcPortCategoryEnum::Radar], PcPortSectionEnum::Radar],
    'no platform' => [['original_platform' => null], PcPortSectionEnum::Tools],
    'multi platform' => [['original_platform' => 'Multi'], PcPortSectionEnum::Tools],
    'launcher tool' => [['technique' => 'launcher-tool'], PcPortSectionEnum::Tools],
]);

it('splits platforms and drops the PS1 alias', function (?string $platform, array $expected) {
    expect((new PcPort(['original_platform' => $platform]))->platforms())->toBe($expected);
})->with([
    ['PlayStation / PS1', ['PlayStation']],
    ['GameCube, Wii', ['GameCube', 'Wii']],
    ['Xbox 360', ['Xbox 360']],
    [null, []],
]);

it('prefers the public version and the most specific activity date', function () {
    $port = new PcPort(['latest_version' => 'v1.0', 'latest_public_version' => 'v0.9', 'last_checked_at' => '2026-10-01', 'added_at' => '2026-09-01']);

    expect($port->displayVersion())->toBe('v0.9')
        ->and($port->activityDate()->toDateString())->toBe('2026-10-01');
});

it('groups every green status under the Playable filter option', function () {
    expect(array_keys(PcPortStatusEnum::filterOptions()))
        ->toBe(['playable', 'released', 'complete', 'active', 'beta', 'advanced_wip', 'wip', 'discovered']);

    foreach (PcPortStatusEnum::cases() as $status) {
        expect(PcPortStatusEnum::matchesFilter($status, 'playable'))->toBe($status->isPlayable())
            ->and(PcPortStatusEnum::matchesFilter($status, $status->value))->toBeTrue();
    }

    expect(PcPortStatusEnum::matchesFilter(PcPortStatusEnum::Released, 'wip'))->toBeFalse()
        ->and(PcPortStatusEnum::matchesFilter(PcPortStatusEnum::Complete, 'playable'))->toBeFalse()
        ->and(PcPortStatusEnum::Complete->pillClasses())->not->toContain('emerald');
});

it('colours the playable tile like the playable status pills', function () {
    expect(PcPortMetricEnum::Playable->valueClasses())->toContain('emerald')
        ->and(PcPortStatusEnum::Released->pillClasses())->toContain('emerald');
});
