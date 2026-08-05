<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::forget('igdb_access_token');
    config(['metrics.igdb_textfile_path' => sys_get_temp_dir().'/igdb_health_'.Str::random(8).'.prom']);
});

afterEach(function () {
    @unlink(config('metrics.igdb_textfile_path'));
    @unlink(config('metrics.igdb_textfile_path').'.tmp');
});

it('reports healthy and writes igdb_up 1 when the probe succeeds', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/v4/games' => Http::response([['id' => 1]], 200),
    ]);

    $this->artisan('igdb:health')->assertSuccessful();

    $path = config('metrics.igdb_textfile_path');
    $contents = file_get_contents($path);

    expect($contents)->toContain('igdb_up 1')
        ->and($contents)->toContain('igdb_health_last_run_timestamp')
        ->and(substr(sprintf('%o', fileperms($path)), -4))->toBe('0644')
        ->and(file_exists($path.'.tmp'))->toBeFalse();
});

it('reports unhealthy and logs when the API errors', function () {
    Log::spy();
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/v4/games' => Http::response('server error', 500),
    ]);

    $this->artisan('igdb:health')->assertFailed();

    expect(file_get_contents(config('metrics.igdb_textfile_path')))->toContain('igdb_up 0');
    Log::shouldHaveReceived('error')->once();
});

it('reports unhealthy when the token fetch fails (broken credentials)', function () {
    Log::spy();
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['status' => 403, 'message' => 'invalid client secret'], 403),
    ]);

    $this->artisan('igdb:health')->assertFailed();

    expect(file_get_contents(config('metrics.igdb_textfile_path')))->toContain('igdb_up 0');
    Log::shouldHaveReceived('error')->once();
});
