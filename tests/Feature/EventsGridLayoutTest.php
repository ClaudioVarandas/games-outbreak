<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function upcomingBanners(int $count): array
{
    $upcoming = [];
    for ($i = 1; $i <= $count; $i++) {
        $upcoming[] = [
            'alt' => "Event {$i}",
            'status' => 'upcoming',
            'image' => '',
            'link' => '#',
            'date' => 'Jun 07, 2026',
        ];
    }

    return ['upcoming' => $upcoming, 'past' => []];
}

it('renders a single upcoming event full width (no split grid)', function () {
    $html = (string) $this->blade('<x-homepage.events-grid :banners="$banners" />', [
        'banners' => upcomingBanners(1),
    ]);

    expect($html)->toContain('Event 1')
        ->not->toContain('lg:grid-cols-2')
        ->not->toContain('lg:grid-cols-5');
});

it('renders two upcoming events as a 50/50 split', function () {
    $html = (string) $this->blade('<x-homepage.events-grid :banners="$banners" />', [
        'banners' => upcomingBanners(2),
    ]);

    expect($html)->toContain('lg:grid-cols-2')
        ->toContain('Event 1')
        ->toContain('Event 2')
        ->not->toContain('lg:grid-cols-5');
});

it('renders three upcoming events as featured 60/40 with two stacked', function () {
    $html = (string) $this->blade('<x-homepage.events-grid :banners="$banners" />', [
        'banners' => upcomingBanners(3),
    ]);

    expect($html)->toContain('lg:grid-cols-5')
        ->toContain('lg:col-span-3')
        ->toContain('lg:col-span-2')
        ->toContain('Event 3');
});

it('caps the upcoming row at three events', function () {
    $html = (string) $this->blade('<x-homepage.events-grid :banners="$banners" />', [
        'banners' => upcomingBanners(5),
    ]);

    expect($html)->toContain('lg:grid-cols-5')
        ->toContain('Event 3')
        ->not->toContain('Event 4');
});

it('shows the empty state when there are no events', function () {
    $html = (string) $this->blade('<x-homepage.events-grid :banners="$banners" />', [
        'banners' => ['upcoming' => [], 'past' => []],
    ]);

    expect($html)->toContain('No active events right now.');
});
