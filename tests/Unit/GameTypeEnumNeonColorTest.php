<?php

use App\Enums\GameTypeEnum;

it('returns a neon CSS class name for every game type', function (GameTypeEnum $type, string $expectedClass) {
    expect($type->neonColorClass())->toBe($expectedClass);
})->with([
    'main' => [GameTypeEnum::MAIN,        'neon-type-main'],
    'dlc' => [GameTypeEnum::DLC,         'neon-type-dlc'],
    'expansion' => [GameTypeEnum::EXPANSION,   'neon-type-expansion'],
    'bundle' => [GameTypeEnum::BUNDLE,       'neon-type-bundle'],
    'standalone' => [GameTypeEnum::STANDALONE,  'neon-type-standalone'],
    'mod' => [GameTypeEnum::MOD,         'neon-type-mod'],
    'episode' => [GameTypeEnum::EPISODE,     'neon-type-episode'],
    'season' => [GameTypeEnum::SEASON,      'neon-type-season'],
    'remake' => [GameTypeEnum::REMAKE,      'neon-type-remake'],
    'remaster' => [GameTypeEnum::REMASTER,    'neon-type-remaster'],
]);
