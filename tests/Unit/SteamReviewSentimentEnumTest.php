<?php

use App\Enums\SteamReviewSentimentEnum;

it('maps the Steam description label back to the enum', function () {
    expect(SteamReviewSentimentEnum::fromLabel('Very Positive'))
        ->toBe(SteamReviewSentimentEnum::VeryPositive);
});

it('returns null for an unknown label', function () {
    expect(SteamReviewSentimentEnum::fromLabel('Spicy'))->toBeNull();
});

it('returns null for a null label', function () {
    expect(SteamReviewSentimentEnum::fromLabel(null))->toBeNull();
});

it('exposes the label as the Steam description string', function () {
    expect(SteamReviewSentimentEnum::OverwhelminglyPositive->label())
        ->toBe('Overwhelmingly Positive');
});

it('colors positive sentiments green', function () {
    expect(SteamReviewSentimentEnum::VeryPositive->colorClass())->toContain('text-green-400');
});

it('colors mixed sentiment yellow', function () {
    expect(SteamReviewSentimentEnum::Mixed->colorClass())->toContain('text-yellow-400');
});

it('colors negative sentiments red', function () {
    expect(SteamReviewSentimentEnum::Negative->colorClass())->toContain('text-red-400');
});
