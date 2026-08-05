<?php

declare(strict_types=1);

namespace App\Console\Commands\Metrics;

use App\Support\Metrics\PrometheusTextfileWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class IgdbHealthCheck extends Command
{
    protected $signature = 'igdb:health';

    protected $description = 'Probe the IGDB API (including token refresh) and export an igdb_up gauge to the node_exporter textfile collector';

    public function handle(PrometheusTextfileWriter $writer): int
    {
        $healthy = false;

        try {
            $response = Http::igdb()
                ->withBody('fields id; limit 1;', 'text/plain')
                ->post('https://api.igdb.com/v4/games');

            $healthy = $response->successful() && $response->json() !== [];

            if (! $healthy) {
                \Log::error('IGDB health check failed', [
                    'reason' => $response->successful() ? 'empty response' : 'http error',
                    'status' => $response->status(),
                    'body_excerpt' => str($response->body())->limit(300)->toString(),
                ]);
            }
        } catch (\Throwable $exception) {
            \Log::error('IGDB health check failed', [
                'reason' => 'exception (token fetch or transport)',
                'message' => $exception->getMessage(),
            ]);
        }

        $registry = new CollectorRegistry(new InMemory, false);
        $registry->getOrRegisterGauge('igdb', 'up', 'Whether the last IGDB API probe (with auth) succeeded')
            ->set($healthy ? 1.0 : 0.0);
        $registry->getOrRegisterGauge('igdb', 'health_last_run_timestamp', 'Unix timestamp of the last IGDB health probe')
            ->set((float) now()->getTimestamp());

        $writer->write(config('metrics.igdb_textfile_path'), (new RenderTextFormat)->render($registry->getMetricFamilySamples()));

        if ($healthy) {
            $this->info('IGDB healthy.');

            return self::SUCCESS;
        }

        $this->error('IGDB health check failed - see log for details.');

        return self::FAILURE;
    }
}
