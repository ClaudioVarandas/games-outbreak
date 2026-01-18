<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    RateLimiter::clear('register:'.$this->app['request']->ip());
    RateLimiter::clear('password-reset:'.$this->app['request']->ip());
});

it('allows registration when honeypot field is empty', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website_url' => '',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('rejects registration when honeypot field is filled', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website_url' => 'http://spam-bot.com',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('website_url');
});

it('allows login when honeypot field is empty', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'website_url' => '',
    ]);

    $this->assertAuthenticatedAs($user);
});

it('rejects login when honeypot field is filled', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'website_url' => 'http://spam-bot.com',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('website_url');
});

it('allows password reset request when honeypot field is empty', function () {
    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
        'website_url' => '',
    ]);

    $response->assertSessionHasNoErrors();
});

it('rejects password reset request when honeypot field is filled', function () {
    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
        'website_url' => 'http://spam-bot.com',
    ]);

    $response->assertSessionHasErrors('website_url');
});

it('rate limits registration attempts', function () {
    RateLimiter::hit('register:127.0.0.1', 60);
    RateLimiter::hit('register:127.0.0.1', 60);
    RateLimiter::hit('register:127.0.0.1', 60);
    RateLimiter::hit('register:127.0.0.1', 60);
    RateLimiter::hit('register:127.0.0.1', 60);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser6',
        'email' => 'test6@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

it('rate limits password reset attempts', function () {
    $user = User::factory()->create();

    RateLimiter::hit('password-reset:127.0.0.1', 60);
    RateLimiter::hit('password-reset:127.0.0.1', 60);
    RateLimiter::hit('password-reset:127.0.0.1', 60);

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('email');
});

it('validates username format on registration', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'Invalid User Name!',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});

it('validates username uniqueness on registration', function () {
    User::factory()->create(['username' => 'existinguser']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'existinguser',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});
