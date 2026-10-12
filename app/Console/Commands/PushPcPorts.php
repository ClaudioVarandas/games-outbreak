<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PcPort;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class PushPcPorts extends Command
{
    protected $signature = 'ports:push
                            {csv : Path to the unofficial PC ports tracker CSV}
                            {--covers= : SteamGridDB cover manifest (covers/sources.json); defaults to the one next to the CSV}
                            {--url= : Base URL of the target site; defaults to IMPORT_API_BASE_URL}
                            {--prune : Delete ports on the site that are no longer in the CSV}
                            {--dry-run : Print the payload summary without sending it}';

    protected $description = 'Push the local unofficial PC ports CSV to the site through the token-authenticated ports API';

    public function handle(): int
    {
        $csvPath = (string) $this->argument('csv');

        if (! is_file($csvPath)) {
            $this->error("CSV not found: {$csvPath}");

            return self::FAILURE;
        }

        $covers = $this->loadCovers($this->option('covers') ?: dirname($csvPath).'/covers/sources.json');
        $ports = $this->readCsv($csvPath, $covers);

        if ($ports === []) {
            $this->error('The CSV has no rows.');

            return self::FAILURE;
        }

        $withCover = count(array_filter($ports, fn (array $port): bool => $port['cover_source_url'] !== null));
        $this->info(sprintf('%d ports read (%d with a SteamGridDB cover).', count($ports), $withCover));

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing sent.');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) ($this->option('url') ?: config('services.import.base_url')), '/');
        $token = (string) config('services.import.token');

        if ($baseUrl === '' || $token === '') {
            $this->error('Set IMPORT_API_BASE_URL and IMPORT_API_TOKEN (or pass --url).');

            return self::FAILURE;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(60)
                ->post($baseUrl.'/api/v1/ports/sync', [
                    'ports' => $ports,
                    'prune' => (bool) $this->option('prune'),
                ]);
        } catch (Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Sync failed ({$response->status()}): ".$response->body());

            return self::FAILURE;
        }

        $summary = $response->json('summary', []);
        $this->table(['created', 'updated', 'unchanged', 'deleted'], [[
            $summary['created'] ?? 0,
            $summary['updated'] ?? 0,
            $summary['unchanged'] ?? 0,
            $summary['deleted'] ?? 0,
        ]]);
        $this->line('Page: '.$response->json('page_url'));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> game title => SteamGridDB image URL
     */
    private function loadCovers(string $path): array
    {
        if (! is_file($path)) {
            $this->warn("No cover manifest at {$path}; covers will be searched on SteamGridDB by title.");

            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        $urls = collect(is_array($manifest) ? $manifest : [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['url'] ?? null))
            ->map(fn (array $entry): string => $entry['url']);

        // The site only uses SteamGridDB art; other covers fall back to a SteamGridDB title search.
        $steamGridDb = $urls->filter(fn (string $url): bool => PcPort::isSteamGridDbUrl($url));

        if ($skipped = $urls->count() - $steamGridDb->count()) {
            $this->warn("{$skipped} non-SteamGridDB covers ignored; the site will search SteamGridDB by title for those.");
        }

        return $steamGridDb->all();
    }

    /**
     * @param  array<string, string>  $covers
     * @return list<array<string, string|null>>
     */
    private function readCsv(string $path, array $covers): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');
        $header = array_map(fn (string $column): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', $column)), $header ?: []);
        $ports = [];

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) {
                continue;
            }

            $row = array_combine($header, array_pad($values, count($header), ''));
            $row = array_map(fn (string $value): ?string => trim($value) === '' ? null : trim($value), $row);
            $row['cover_source_url'] = $covers[$row['game_title'] ?? ''] ?? null;

            $ports[] = $row;
        }

        fclose($handle);

        return $ports;
    }
}
