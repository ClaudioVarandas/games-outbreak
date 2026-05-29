<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('writes a valid prometheus textfile atomically with 0644 perms', function () {
    $this->freezeTime();
    $now = now()->getTimestamp();

    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => null, 'available_at' => $now - 60, 'created_at' => $now,
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'low',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
    ]);

    $path = sys_get_temp_dir().'/queue_metrics_'.Str::random(8).'.prom';
    config(['metrics.textfile_path' => $path]);

    $this->artisan('metrics:export-queues')->assertSuccessful();

    expect(file_exists($path))->toBeTrue()
        ->and(file_exists($path.'.tmp'))->toBeFalse()
        ->and(substr(sprintf('%o', fileperms($path)), -4))->toBe('0644');

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('laravel_queue_pending{queue="default"} 1')
        ->toContain('laravel_queue_pending{queue="low"} 0')
        ->toContain('laravel_queue_oldest_pending_seconds{queue="default"} 60')
        ->toContain('laravel_queue_failed_jobs{queue="low"} 1')
        ->toContain('# TYPE laravel_queue_metrics_last_run_timestamp gauge')
        ->toContain('laravel_queue_metrics_last_run_timestamp '.$now);

    @unlink($path);
});
