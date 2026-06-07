<?php

use App\Support\AdminCliReference;

it('exposes tiers with complete command entries', function () {
    $tiers = AdminCliReference::tiers();

    expect($tiers)->not->toBeEmpty();

    foreach ($tiers as $tier) {
        expect($tier)->toHaveKeys(['title', 'summary', 'commands'])
            ->and($tier['commands'])->not->toBeEmpty();

        foreach ($tier['commands'] as $command) {
            expect($command)->toHaveKeys(['name', 'flags', 'does', 'writes'])
                ->and($command['name'])->toBeString()->not->toBeEmpty()
                ->and($command['flags'])->toBeArray();
        }
    }
});

it('exposes operating rules and a mental model', function () {
    expect(AdminCliReference::rules())->not->toBeEmpty()
        ->and(AdminCliReference::mentalModel())->toBeString()->not->toBeEmpty();
});
