<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the CLI reference page to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin/cli-reference')
        ->assertSuccessful()
        ->assertSee('CLI Reference')
        ->assertSee('Tier 1 — Refresh game records')
        ->assertSee('igdb:gamelist:sync-pivot')
        ->assertSee('igdb:events:import');
});

it('forbids non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/cli-reference')->assertForbidden();
});

it('redirects guests', function () {
    $this->get('/admin/cli-reference')->assertRedirect();
});
