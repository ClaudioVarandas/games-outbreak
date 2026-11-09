<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->originalStoragePath = storage_path();
    $this->temporaryStoragePath = sys_get_temp_dir().'/prune-images-'.uniqid();
    File::makeDirectory($this->temporaryStoragePath.'/app/public/covers', 0755, true);
    app()->useStoragePath($this->temporaryStoragePath);

    $this->createCover = function (string $name, int $ageDays = 10): string {
        $path = storage_path('app/public/covers/'.$name);
        File::put($path, 'image');
        touch($path, now()->subDays($ageDays)->getTimestamp());

        return $path;
    };
});

afterEach(function () {
    app()->useStoragePath($this->originalStoragePath);
    File::deleteDirectory($this->temporaryStoragePath);
});

it('reports orphans without deleting them by default', function () {
    Game::factory()->create(['hero_image_id' => '100_1700000001.png']);
    $orphan = ($this->createCover)('100_1700000000.png');
    ($this->createCover)('100_1700000001.png');

    $this->artisan('images:prune-orphans')
        ->expectsOutputToContain('Orphaned: 1')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(File::exists($orphan))->toBeTrue();
});

it('lets --dry-run win over --force and names the files', function () {
    Game::factory()->create(['hero_image_id' => '100_1700000001.png']);
    $orphan = ($this->createCover)('100_1700000000.png');

    $this->artisan('images:prune-orphans --dry-run --force')
        ->expectsOutputToContain('100_1700000000.png')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(File::exists($orphan))->toBeTrue();
});

it('limits the dry run listing to a sample', function () {
    Game::factory()->create(['hero_image_id' => '100_1700000001.png']);
    foreach (range(1, 25) as $number) {
        ($this->createCover)("200_17000000{$number}.png");
    }

    $this->artisan('images:prune-orphans --dry-run')
        ->expectsOutputToContain('... and 5 more.')
        ->assertSuccessful();
});

it('deletes only unreferenced files when forced', function () {
    Game::factory()->create([
        'cover_image_id' => '100_1700000001.jpg',
        'hero_image_id' => '100_1700000002.png',
    ]);
    $orphan = ($this->createCover)('100_1700000000.png');
    $cover = ($this->createCover)('100_1700000001.jpg');
    $hero = ($this->createCover)('100_1700000002.png');

    $this->artisan('images:prune-orphans --force')->assertSuccessful();

    expect(File::exists($orphan))->toBeFalse()
        ->and(File::exists($cover))->toBeTrue()
        ->and(File::exists($hero))->toBeTrue();
});

it('keeps files referenced by a path in another table', function () {
    Game::factory()->create(['hero_image_id' => '100_1700000001.png']);
    GameList::factory()->create(['og_image_path' => 'covers/200_1700000000.png']);
    $file = ($this->createCover)('200_1700000000.png');

    $this->artisan('images:prune-orphans --force')->assertSuccessful();

    expect(File::exists($file))->toBeTrue();
});

it('keeps recent files and files with an unexpected name', function () {
    Game::factory()->create(['hero_image_id' => '100_1700000001.png']);
    $recent = ($this->createCover)('100_1700000000.png', 1);
    $unexpected = ($this->createCover)('manual-upload.png');

    $this->artisan('images:prune-orphans --force')->assertSuccessful();

    expect(File::exists($recent))->toBeTrue()
        ->and(File::exists($unexpected))->toBeTrue();
});

it('refuses to run when the database has no image references', function () {
    $file = ($this->createCover)('100_1700000000.png');

    $this->artisan('images:prune-orphans --force')->assertFailed();

    expect(File::exists($file))->toBeTrue();
});

it('rejects a minimum age below one day', function () {
    $file = ($this->createCover)('100_1700000000.png');

    $this->artisan('images:prune-orphans --force --min-age-days=0')->assertFailed();

    expect(File::exists($file))->toBeTrue();
});
