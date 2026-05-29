<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use Illuminate\Support\Facades\DB;

class QueueMetricsCollector
{
    /**
     * Snapshot of queue metrics keyed by queue name.
     *
     * @return array<string, array{pending:int, delayed:int, reserved:int, oldest_pending_seconds:int, failed_jobs:int, failed_jobs_last_hour:int}>
     */
    public function collect(): array
    {
        $now = now()->getTimestamp();
        $metrics = [];

        foreach (config('metrics.queues') as $queue) {
            $metrics[$queue] = $this->forQueue($queue, $now);
        }

        return $metrics;
    }

    /**
     * @return array{pending:int, delayed:int, reserved:int, oldest_pending_seconds:int, failed_jobs:int, failed_jobs_last_hour:int}
     */
    private function forQueue(string $queue, int $now): array
    {
        $available = fn () => DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now);

        $oldestAvailableAt = $available()->min('available_at');

        return [
            'pending' => $available()->count(),
            'delayed' => DB::table('jobs')->where('queue', $queue)->where('available_at', '>', $now)->count(),
            'reserved' => DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count(),
            'oldest_pending_seconds' => $oldestAvailableAt !== null ? max(0, $now - (int) $oldestAvailableAt) : 0,
            'failed_jobs' => DB::table('failed_jobs')->where('queue', $queue)->count(),
            'failed_jobs_last_hour' => DB::table('failed_jobs')
                ->where('queue', $queue)
                ->where('failed_at', '>=', now()->subHour())
                ->count(),
        ];
    }
}
