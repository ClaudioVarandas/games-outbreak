<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReleaseDateStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncReleaseDateStatuses extends Command
{
    protected $signature = 'igdb:sync-release-date-statuses';
    protected $description = 'Sync release date statuses from IGDB API';

    private array $abbreviations = [
        'Early Access' => 'EA',
        'Advanced Access' => 'Adv. Access',
        'Digital Compatibility Release' => 'Digital Comp.',
        'Next-Gen Optimization Patch Release' => 'Next-Gen Patch',
    ];

    public function handle(): int
    {
        $this->info('Fetching release date statuses from IGDB...');

        try {
            $query = 'fields id, name, description; limit 500;';

            $response = Http::igdb()
                ->withBody($query, 'text/plain')
                ->post('https://api.igdb.com/v4/release_date_statuses');

            if ($response->failed()) {
                $this->error('Failed to fetch from IGDB: ' . $response->status());
                return self::FAILURE;
            }

            $statuses = $response->json();

            if (empty($statuses)) {
                $this->warn('No statuses returned from IGDB.');
                return self::FAILURE;
            }

            $this->info('Found ' . count($statuses) . ' statuses. Syncing...');

            $progressBar = $this->output->createProgressBar(count($statuses));

            foreach ($statuses as $statusData) {
                $name = $statusData['name'];
                $abbreviation = $this->abbreviations[$name] ?? $name;

                ReleaseDateStatus::updateOrCreate(
                    ['igdb_id' => $statusData['id']],
                    [
                        'name' => $name,
                        'abbreviation' => $abbreviation,
                        'description' => $statusData['description'] ?? null,
                    ]
                );

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Clear cache
            ReleaseDateStatus::clearCache();

            $this->info('✓ Successfully synced ' . count($statuses) . ' release date statuses.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
