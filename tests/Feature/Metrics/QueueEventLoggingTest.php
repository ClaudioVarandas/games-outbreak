<?php

use App\Support\Metrics\QueueEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

class LoggerProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void {}
}

function captureQueueLog(): TestHandler
{
    $handler = new TestHandler;
    Log::channel('queue')->getLogger()->setHandlers([$handler]);

    return $handler;
}

it('logs structured lifecycle events and omits sensitive data', function () {
    $handler = captureQueueLog();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getQueue')->andReturn('low');
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\DummyJob');
    $job->shouldReceive('getJobId')->andReturn('42');
    $job->shouldReceive('uuid')->andReturn('uuid-123');
    $job->shouldReceive('attempts')->andReturn(1);

    $logger = app(QueueEventLogger::class);
    $logger->processing(new JobProcessing('database', $job));
    $logger->processed(new JobProcessed('database', $job));
    $logger->failed(new JobFailed('database', $job, new RuntimeException('boom secret')));

    $records = $handler->getRecords();

    expect($records)->toHaveCount(3)
        ->and(array_map(fn ($r) => $r->context['status'], $records))->toBe(['processing', 'processed', 'failed']);

    $processed = $records[1]->context;
    expect($processed)->toHaveKeys(['app', 'env', 'queue', 'connection', 'job_class', 'job_id', 'uuid', 'attempts', 'duration_ms', 'status'])
        ->and($processed['queue'])->toBe('low')
        ->and($processed['job_class'])->toBe('App\\Jobs\\DummyJob')
        ->and($processed)->not->toHaveKey('payload');

    $failed = $records[2]->context;
    expect($failed['exception_class'])->toBe('RuntimeException')
        ->and($failed['exception_message'])->toContain('boom secret')
        ->and($failed['status'])->toBe('failed');
});

it('fires the logger through the registered queue hooks', function () {
    $handler = captureQueueLog();

    config(['queue.default' => 'sync']);
    LoggerProbeJob::dispatch();

    $statuses = array_map(fn ($r) => $r->context['status'] ?? null, $handler->getRecords());

    expect($statuses)->toContain('processing')->toContain('processed');
});
