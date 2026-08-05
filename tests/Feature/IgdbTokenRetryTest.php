<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('forgets the cached token and retries once with a fresh one on 401', function () {
    Cache::put('igdb_access_token', 'stale-token', now()->addHours(23));

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'id.twitch.tv')) {
            return Http::response(['access_token' => 'fresh-token'], 200);
        }

        if ($request->header('Authorization')[0] === 'Bearer stale-token') {
            return Http::response(['message' => 'Authorization Failure'], 401);
        }

        return Http::response([['id' => 1, 'name' => 'Some Game']], 200);
    });

    $response = Http::igdb()
        ->withBody('fields id,name; limit 1;', 'text/plain')
        ->post('https://api.igdb.com/v4/games');

    expect($response->successful())->toBeTrue()
        ->and($response->json(0)['id'])->toBe(1)
        ->and(Cache::get('igdb_access_token'))->toBe('fresh-token');

    $gameRequests = collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn ($request) => str_contains($request->url(), 'api.igdb.com'));

    expect($gameRequests)->toHaveCount(2)
        ->and($gameRequests->first()->header('Authorization')[0])->toBe('Bearer stale-token')
        ->and($gameRequests->last()->header('Authorization')[0])->toBe('Bearer fresh-token');
});

it('does not clear the cached token on non-401 failures and returns the response', function () {
    Cache::put('igdb_access_token', 'stale-token', now()->addHours(23));

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'id.twitch.tv')) {
            return Http::response(['access_token' => 'fresh-token'], 200);
        }

        return Http::response('server error', 500);
    });

    $response = Http::igdb()
        ->withBody('fields id; limit 1;', 'text/plain')
        ->post('https://api.igdb.com/v4/games');

    expect($response->failed())->toBeTrue()
        ->and($response->status())->toBe(500)
        ->and(Cache::get('igdb_access_token'))->toBe('stale-token');
});
