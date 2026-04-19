<?php

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->yearlyList = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->datedGame = Game::factory()->create(['name' => 'Dated Release Alpha']);
    $this->tbaGame = Game::factory()->create(['name' => 'TBA Release Omega']);

    $this->yearlyList->games()->attach($this->datedGame->id, [
        'release_date' => '2026-04-15 00:00:00',
        'is_tba' => false,
    ]);

    $this->yearlyList->games()->attach($this->tbaGame->id, [
        'release_date' => null,
        'is_tba' => true,
    ]);
});

it('shows all sections on the unfiltered yearly page', function () {
    $this->get('/releases/2026?all=1')
        ->assertOk()
        ->assertSee('Dated Release Alpha')
        ->assertSee('TBA Release Omega');
});

it('shows only the TBA section when only=tba', function () {
    $this->get('/releases/2026?only=tba')
        ->assertOk()
        ->assertSee('TBA Release Omega')
        ->assertDontSee('Dated Release Alpha');
});

it('returns 404 for an unknown only value', function () {
    $this->get('/releases/2026?only=foo')->assertNotFound();
});

it('returns 404 when only=tba is combined with a month segment', function () {
    $this->get('/releases/2026/4?only=tba')->assertNotFound();
});

it('redirects current year without filters to the current month view', function () {
    $currentYear = now()->year;
    $currentMonth = now()->month;

    $this->get("/releases/{$currentYear}")
        ->assertRedirect("/releases/{$currentYear}/{$currentMonth}");
});

it('does not redirect a past year', function () {
    $pastYear = now()->year - 1;

    GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create($pastYear, 1, 1),
        'end_at' => Carbon::create($pastYear, 12, 31),
    ]);

    $this->get("/releases/{$pastYear}")->assertOk();
});

it('does not redirect when all=1 bypass is set for the current year', function () {
    $currentYear = now()->year;

    $this->get("/releases/{$currentYear}?all=1")->assertOk();
});

it('does not redirect when only=tba is set for the current year', function () {
    $currentYear = now()->year;

    $this->get("/releases/{$currentYear}?only=tba")->assertOk();
});
