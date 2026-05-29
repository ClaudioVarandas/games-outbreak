<?php

declare(strict_types=1);

namespace App\Console\Commands\Metrics;

use App\Support\Metrics\QueueMetricsCollector;
use Illuminate\Console\Command;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class ExportQueueMetrics extends Command
{
    protected $signature = 'metrics:export-queues';

    protected $description = 'Render queue metrics in Prometheus exposition format to the node_exporter textfile collector';

    public function handle(QueueMetricsCollector $collector): int
    {
        $registry = new CollectorRegistry(new InMemory, false);

        $gauges = [
            'pending' => $registry->getOrRegisterGauge('laravel_queue', 'pending', 'Jobs available to be processed', ['queue']),
            'delayed' => $registry->getOrRegisterGauge('laravel_queue', 'delayed', 'Jobs scheduled for the future', ['queue']),
            'reserved' => $registry->getOrRegisterGauge('laravel_queue', 'reserved', 'Jobs currently being processed', ['queue']),
            'oldest_pending_seconds' => $registry->getOrRegisterGauge('laravel_queue', 'oldest_pending_seconds', 'Age of the oldest pending job in seconds', ['queue']),
            'failed_jobs' => $registry->getOrRegisterGauge('laravel_queue', 'failed_jobs', 'Failed jobs currently stored', ['queue']),
            'failed_jobs_last_hour' => $registry->getOrRegisterGauge('laravel_queue', 'failed_jobs_last_hour', 'Jobs that failed within the last hour', ['queue']),
        ];

        foreach ($collector->collect() as $queue => $values) {
            foreach ($gauges as $key => $gauge) {
                $gauge->set((float) $values[$key], [$queue]);
            }
        }

        $registry->getOrRegisterGauge('laravel_queue', 'metrics_last_run_timestamp', 'Unix timestamp of the last successful metrics export')
            ->set((float) now()->getTimestamp());

        $this->writeAtomically((new RenderTextFormat)->render($registry->getMetricFamilySamples()));

        return self::SUCCESS;
    }

    private function writeAtomically(string $contents): void
    {
        $path = config('metrics.textfile_path');
        $temp = $path.'.tmp';

        file_put_contents($temp, $contents, LOCK_EX);
        chmod($temp, 0644);
        rename($temp, $path);
    }
}
