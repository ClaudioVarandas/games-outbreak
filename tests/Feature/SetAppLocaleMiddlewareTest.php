<?php

use App\Enums\NewsLocaleEnum;

it('sets locale to pt-PT from session on any page', function () {
    $this->withSession(['locale' => 'pt-pt'])
        ->get(route('homepage'))
        ->assertOk();

    expect(app()->getLocale())->toBe('pt-PT');
});

it('sets locale to pt-BR from session', function () {
    $this->withSession(['locale' => 'pt-br'])
        ->get(route('homepage'))
        ->assertOk();

    expect(app()->getLocale())->toBe('pt-BR');
});

it('sets locale to en from session', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('homepage'))
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('falls back to browser Accept-Language when no session', function () {
    $this->get(route('homepage'), ['Accept-Language' => 'pt-PT,pt;q=0.9'])
        ->assertOk();

    expect(app()->getLocale())->toBe('pt-PT');
});

it('falls back to pt-BR from browser header', function () {
    $this->get(route('homepage'), ['Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8'])
        ->assertOk();

    expect(app()->getLocale())->toBe('pt-BR');
});

it('falls back to app locale when no session and no header', function () {
    $this->get(route('homepage'))
        ->assertOk();

    expect(app()->getLocale())->toBe(NewsLocaleEnum::fromAppLocale()->value);
});
