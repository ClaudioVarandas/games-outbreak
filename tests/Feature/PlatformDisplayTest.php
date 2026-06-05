<?php

use App\Enums\PlatformDisplayGroupEnum;
use App\Enums\PlatformEnum;

it('orders the platform picker by display group', function () {
    $groups = PlatformEnum::displayList()->pluck('group')->unique()->values()->all();

    expect($groups)->toBe(['computer', 'current_gen', 'mobile', 'last_gen']);
});

it('flags PC, PS5, Xbox X/S and Switch 2 as default selections', function () {
    $defaults = PlatformEnum::displayList()->where('default', true)->pluck('id')->all();

    expect($defaults)->toEqualCanonicalizing([
        PlatformEnum::PC->value,
        PlatformEnum::PS5->value,
        PlatformEnum::XBOX_SX->value,
        PlatformEnum::SWITCH2->value,
    ]);
});

it('tags each platform entry with id, label, color, group and default', function () {
    expect(PlatformEnum::displayList()->first())
        ->toHaveKeys(['id', 'label', 'color', 'group', 'default']);
});

it('maps platforms to their display groups', function () {
    expect(PlatformEnum::PC->group())->toBe(PlatformDisplayGroupEnum::Computer)
        ->and(PlatformEnum::LINUX->group())->toBe(PlatformDisplayGroupEnum::Computer)
        ->and(PlatformEnum::MACOS->group())->toBe(PlatformDisplayGroupEnum::Computer)
        ->and(PlatformEnum::PS5->group())->toBe(PlatformDisplayGroupEnum::CurrentGen)
        ->and(PlatformEnum::XBOX_SX->group())->toBe(PlatformDisplayGroupEnum::CurrentGen)
        ->and(PlatformEnum::SWITCH2->group())->toBe(PlatformDisplayGroupEnum::CurrentGen)
        ->and(PlatformEnum::ANDROID->group())->toBe(PlatformDisplayGroupEnum::Mobile)
        ->and(PlatformEnum::IOS->group())->toBe(PlatformDisplayGroupEnum::Mobile)
        ->and(PlatformEnum::PS4->group())->toBe(PlatformDisplayGroupEnum::LastGen)
        ->and(PlatformEnum::SWITCH->group())->toBe(PlatformDisplayGroupEnum::LastGen);
});
