<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.import.token' => 'push-token', 'services.import.base_url' => 'https://site.test']);

    $this->dir = sys_get_temp_dir().'/ports-push-'.uniqid();
    mkdir($this->dir.'/covers', 0777, true);
    file_put_contents($this->dir.'/ports.csv', implode("\n", [
        "\u{FEFF}slug,game_title,original_platform,technique,status,notes,category,native_pc_port",
        'banjo-kazooie,Banjo-Kazooie,Nintendo 64,static_recompilation,released,"Port, with comma",pc_port,yes',
        'zelda-radar,Zelda Radar,GameCube,decompilation,discovered,,radar,no',
        '',
    ]));
    file_put_contents($this->dir.'/covers/sources.json', json_encode([
        'Banjo-Kazooie' => ['path' => 'covers/banjo.jpg', 'url' => 'https://cdn2.steamgriddb.com/grid/banjo.png'],
        'Zelda Radar' => ['path' => 'covers/zelda.jpg', 'url' => 'https://upload.wikimedia.org/zelda.jpg'],
    ]));
});

afterEach(function () {
    collect([$this->dir.'/covers/sources.json', $this->dir.'/ports.csv'])->each(fn ($file) => @unlink($file));
    @rmdir($this->dir.'/covers');
    @rmdir($this->dir);
});

it('posts the CSV rows with their SteamGridDB cover urls', function () {
    Http::fake(['site.test/*' => Http::response([
        'page_url' => 'https://site.test/ports',
        'summary' => ['created' => 2, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0],
    ])]);

    $this->artisan('ports:push', ['csv' => $this->dir.'/ports.csv'])
        ->expectsOutputToContain('1 non-SteamGridDB covers ignored')
        ->expectsOutputToContain('2 ports read (1 with a SteamGridDB cover).')
        ->assertSuccessful();

    Http::assertSent(function (Request $request) {
        $ports = $request['ports'];

        return $request->url() === 'https://site.test/api/v1/ports/sync'
            && $request->hasHeader('Authorization', 'Bearer push-token')
            && $request['prune'] === false
            && count($ports) === 2
            && $ports[0]['slug'] === 'banjo-kazooie'
            && $ports[0]['notes'] === 'Port, with comma'
            && $ports[0]['cover_source_url'] === 'https://cdn2.steamgriddb.com/grid/banjo.png'
            && $ports[1]['notes'] === null
            && $ports[1]['cover_source_url'] === null;
    });
});

it('sends prune and honours an explicit url', function () {
    Http::fake(['other.test/*' => Http::response(['summary' => []])]);

    $this->artisan('ports:push', ['csv' => $this->dir.'/ports.csv', '--prune' => true, '--url' => 'https://other.test/'])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://other.test/api/v1/ports/sync' && $request['prune'] === true);
});

it('sends nothing on a dry run', function () {
    Http::fake();

    $this->artisan('ports:push', ['csv' => $this->dir.'/ports.csv', '--dry-run' => true])->assertSuccessful();

    Http::assertNothingSent();
});

it('fails when the API rejects the payload', function () {
    Http::fake(['site.test/*' => Http::response(['message' => 'invalid'], 422)]);

    $this->artisan('ports:push', ['csv' => $this->dir.'/ports.csv'])->assertFailed();
});

it('fails on a missing CSV', function () {
    $this->artisan('ports:push', ['csv' => $this->dir.'/missing.csv'])->assertFailed();
});
