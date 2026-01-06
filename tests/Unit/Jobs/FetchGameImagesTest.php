<?php

namespace Tests\Unit\Jobs;

use App\Jobs\FetchGameImages;
use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchGameImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_executes_successfully_with_valid_game(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => null,
            'hero_image_id' => null,
            'logo_image_id' => null,
        ]);

        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([
                'data' => [
                    ['id' => 100, 'types' => ['steam']],
                ],
            ], 200),
            'www.steamgriddb.com/api/v2/grids/game/*' => Http::response([
                'data' => [
                    [
                        'url' => 'https://example.com/image.jpg',
                        'style' => 'alternate',
                    ],
                ],
            ], 200),
            'example.com/image.jpg' => Http::response('image content', 200),
        ]);

        Storage::fake('public');

        $job = new FetchGameImages(
            $game->id,
            $game->name,
            123456,
            $game->igdb_id,
            ['cover', 'hero', 'logo']
        );

        $job->handle(app(IgdbService::class));

        $game->refresh();
        // At least one image should be fetched
        $this->assertTrue(
            !empty($game->cover_image_id) ||
            !empty($game->hero_image_id) ||
            !empty($game->logo_image_id)
        );
    }

    public function test_job_handles_missing_game_gracefully(): void
    {
        $nonExistentId = 99999;

        $job = new FetchGameImages(
            $nonExistentId,
            'Test Game',
            null,
            null,
            ['cover']
        );

        // Should not throw exception
        $job->handle(app(IgdbService::class));

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_job_skips_images_that_already_exist(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => 'existing_cover.jpg',
            'hero_image_id' => null,
            'logo_image_id' => null,
        ]);

        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([
                'data' => [
                    ['id' => 100, 'types' => ['steam']],
                ],
            ], 200),
            'www.steamgriddb.com/api/v2/grids/game/*' => Http::response([
                'data' => [
                    [
                        'url' => 'https://example.com/image.jpg',
                        'style' => 'alternate',
                    ],
                ],
            ], 200),
            'example.com/image.jpg' => Http::response('image content', 200),
        ]);

        Storage::fake('public');

        $job = new FetchGameImages(
            $game->id,
            $game->name,
            123456,
            $game->igdb_id,
            ['cover', 'hero', 'logo']
        );

        $job->handle(app(IgdbService::class));

        $game->refresh();
        // Cover should remain unchanged
        $this->assertEquals('existing_cover.jpg', $game->cover_image_id);
    }

    public function test_job_handles_image_fetch_failures_gracefully(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => null,
            'hero_image_id' => null,
            'logo_image_id' => null,
        ]);

        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([], 500),
        ]);

        $job = new FetchGameImages(
            $game->id,
            $game->name,
            null,
            $game->igdb_id,
            ['cover']
        );

        // Should not throw exception
        $job->handle(app(IgdbService::class));

        $game->refresh();
        // Images should remain null
        $this->assertNull($game->cover_image_id);
    }

    public function test_job_updates_game_with_fetched_images(): void
    {
        $game = Game::factory()->create([
            'cover_image_id' => null,
            'hero_image_id' => null,
            'logo_image_id' => null,
        ]);

        Http::fake([
            'www.steamgriddb.com/api/v2/search/autocomplete/*' => Http::response([
                'data' => [
                    ['id' => 100, 'types' => ['steam']],
                ],
            ], 200),
            'www.steamgriddb.com/api/v2/grids/game/*' => Http::response([
                'data' => [
                    [
                        'url' => 'https://example.com/cover.jpg',
                        'style' => 'alternate',
                    ],
                ],
            ], 200),
            'example.com/cover.jpg' => Http::response('image content', 200),
        ]);

        Storage::fake('public');

        $job = new FetchGameImages(
            $game->id,
            $game->name,
            123456,
            $game->igdb_id,
            ['cover']
        );

        $job->handle(app(IgdbService::class));

        $game->refresh();
        // Game should be updated if image was fetched
        $this->assertNotNull($game->cover_image_id);
    }
}
