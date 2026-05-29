<?php

use App\Support\Metrics\QueueMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function insertJob(string $queue, ?int $reservedAt, int $availableAt): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => $availableAt,
        'created_at' => now()->getTimestamp(),
    ]);
}

it('computes per-queue snapshots including zero-job queues', function () {
    $this->freezeTime();
    $now = now()->getTimestamp();

    insertJob('default', null, $now - 120);   // pending, oldest
    insertJob('default', null, $now - 30);    // pending
    insertJob('default', null, $now + 600);   // delayed
    insertJob('low', $now, $now - 10);        // reserved (in-flight)

    DB::table('failed_jobs')->insert([
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()],
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHours(2)],
    ]);

    $metrics = app(QueueMetricsCollector::class)->collect();

    expect($metrics)->toHaveKeys(['default', 'low']);

    expect($metrics['default'])->toMatchArray([
        'pending' => 2,
        'delayed' => 1,
        'reserved' => 0,
        'oldest_pending_seconds' => 120,
        'failed_jobs' => 2,
        'failed_jobs_last_hour' => 1,
    ]);

    expect($metrics['low'])->toMatchArray([
        'pending' => 0,
        'delayed' => 0,
        'reserved' => 1,
        'oldest_pending_seconds' => 0,
        'failed_jobs' => 0,
        'failed_jobs_last_hour' => 0,
    ]);
});
