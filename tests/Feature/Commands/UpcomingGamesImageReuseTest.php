<?php

use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->originalStoragePath = storage_path();
    $this->temporaryStoragePath = sys_get_temp_dir().'/image-reuse-'.uniqid();
    File::makeDirectory($this->temporaryStoragePath.'/app/public/covers', 0755, true);
    app()->useStoragePath($this->temporaryStoragePath);
});

afterEach(function () {
    app()->useStoragePath($this->originalStoragePath);
    File::deleteDirectory($this->temporaryStoragePath);
});

function mockUpcomingGame(callable $configure, array $igdbGame = ['id' => 500, 'name' => 'Upcoming Game']): void
{
    test()->partialMock(IgdbService::class, function ($mock) use ($configure, $igdbGame) {
        $mock->shouldReceive('fetchUpcomingGames')->andReturn([$igdbGame]);
        $mock->shouldReceive('enrichWithSteamData')->andReturnArg(0);
        $configure($mock);
    });
}

it('does not download anything from SteamGridDB when IGDB has a cover', function () {
    mockUpcomingGame(
        fn ($mock) => $mock->shouldNotReceive('fetchImageFromSteamGridDb'),
        ['id' => 500, 'name' => 'Upcoming Game', 'cover' => ['image_id' => 'coabc1']],
    );

    $this->artisan('igdb:upcoming:update --days=7 --limit=1')->assertSuccessful();

    expect(Game::where('igdb_id', 500)->first())
        ->cover_image_id->toBe('coabc1')
        ->hero_image_id->toBe('coabc1');
});

it('reuses images that already exist instead of downloading them again', function () {
    foreach (['500_1.jpg', '500_2.png'] as $file) {
        File::put(storage_path('app/public/covers/'.$file), 'image');
    }
    $game = Game::factory()->create([
        'igdb_id' => 500,
        'cover_image_id' => '500_1.jpg',
        'hero_image_id' => '500_2.png',
    ]);

    mockUpcomingGame(fn ($mock) => $mock->shouldNotReceive('fetchImageFromSteamGridDb'));

    $this->artisan('igdb:upcoming:update --days=7 --limit=1')->assertSuccessful();

    expect($game->fresh())
        ->cover_image_id->toBe('500_1.jpg')
        ->hero_image_id->toBe('500_2.png');
});

it('downloads an image when its file is missing', function () {
    $game = Game::factory()->create(['igdb_id' => 500, 'cover_image_id' => '500_missing.png']);

    mockUpcomingGame(function ($mock) {
        $mock->shouldReceive('fetchImageFromSteamGridDb')->andReturnUsing(
            fn (string $name, string $type) => "500_{$type}.png"
        );
    });

    $this->artisan('igdb:upcoming:update --days=7 --limit=1')->assertSuccessful();

    expect($game->fresh()->cover_image_id)->toBe('500_cover.png');
});

it('keeps the current value when the download fails', function () {
    $game = Game::factory()->create(['igdb_id' => 500, 'cover_image_id' => '500_missing.png']);

    mockUpcomingGame(fn ($mock) => $mock->shouldReceive('fetchImageFromSteamGridDb')->andReturnNull());

    $this->artisan('igdb:upcoming:update --days=7 --limit=1')->assertSuccessful();

    expect($game->fresh()->cover_image_id)->toBe('500_missing.png');
});
