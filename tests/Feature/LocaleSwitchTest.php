<?php

it('sets locale session and redirects on valid prefix', function () {
    $this->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'pt-pt');
});

it('sets locale to en', function () {
    $this->get(route('locale.switch', 'en'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'en');
});

it('sets locale to pt-br', function () {
    $this->get(route('locale.switch', 'pt-br'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'pt-br');
});

it('returns 404 for invalid prefix', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('redirects back to previous page', function () {
    $this->from(route('homepage'))
        ->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(route('homepage'));
});

it('falls back to homepage when no referrer', function () {
    $this->get(route('locale.switch', 'pt-pt'))
        ->assertRedirect(route('homepage'));
});
