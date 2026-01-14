<?php

namespace App\Console\Commands;

use App\Models\ExternalGameSource;
use App\Services\IgdbService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncExternalGameSources extends Command
{
    protected $signature = 'igdb:sync-sources';

    protected $description = 'Sync external game source definitions from IGDB';

    public function handle(IgdbService $igdbService): int
    {
        $this->info('Fetching external game sources from IGDB...');

        try {
            $query = 'fields id, name; limit 500;';

            $response = Http::igdb()
                ->withBody($query, 'text/plain')
                ->post('https://api.igdb.com/v4/external_game_sources');

            if ($response->failed()) {
                $this->error('Failed to fetch external game sources from IGDB');
                $this->error('Response: '.$response->body());

                return self::FAILURE;
            }

            $sources = $response->json();

            if (empty($sources)) {
                $this->warn('No external game sources returned from IGDB');

                return self::SUCCESS;
            }

            $this->info('Found '.count($sources).' external game sources');

            $bar = $this->output->createProgressBar(count($sources));
            $bar->start();

            $created = 0;
            $updated = 0;

            foreach ($sources as $source) {
                $igdbId = $source['id'] ?? null;
                $name = $source['name'] ?? null;

                if (! $igdbId || ! $name) {
                    $bar->advance();

                    continue;
                }

                $existingSource = ExternalGameSource::where('igdb_id', $igdbId)->first();

                if ($existingSource) {
                    $existingSource->update([
                        'name' => $name,
                        'slug' => str()->slug($name),
                    ]);
                    $updated++;
                } else {
                    ExternalGameSource::create([
                        'igdb_id' => $igdbId,
                        'name' => $name,
                        'slug' => str()->slug($name),
                    ]);
                    $created++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("Sync complete: {$created} created, {$updated} updated");

            // Display some key sources
            $this->table(
                ['IGDB ID', 'Name', 'Slug'],
                ExternalGameSource::query()
                    ->whereIn('igdb_id', [1, 5, 11, 26, 36]) // Steam, GOG, Xbox, Epic, PlayStation
                    ->get(['igdb_id', 'name', 'slug'])
                    ->toArray()
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error syncing external game sources: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
